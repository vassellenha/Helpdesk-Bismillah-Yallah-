<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Auth\RefusedLoginAudit;
use App\Support\RoleRegistry;
use App\Support\Sso\SsoAuthenticator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Login pengembangan — cukup email, TANPA password.
 *
 * ALASAN ADANYA: menguji hak akses. Sebelum ini repo tidak punya login sama
 * sekali — setiap layar role jatuh ke persona tetap (lihat CurrentActor), jadi
 * "user X tidak boleh membuka layar Y" tidak bisa dibuktikan: siapa pun yang
 * mengetik URL-nya langsung menjadi persona role itu. Login nyata membuat
 * pertanyaan itu punya jawaban, dan tanpa password berpindah identitas untuk
 * menguji role berikutnya cuma perlu satu kolom isian.
 *
 * TIDAK ADA LAPISAN KEDUA, dan itu disengaja: siapa pun yang tahu email
 * seseorang bisa MENJADI orang itu. Yang menahannya bukan kredensial melainkan
 * LINGKUNGAN — seluruh rute di sini tidak terdaftar saat helpdesk.dev_login
 * bernilai false, dan defaultnya mati begitu APP_ENV=production. Produksi masuk
 * lewat SSO SINTA. Jangan pernah menyalakan saklar itu di sana.
 *
 * Nama rute `login` tetap terdaftar di SEMUA lingkungan meski isinya dimatikan:
 * middleware `auth` bawaan Laravel mengalihkan tamu ke `route('login')`, dan
 * nama yang tidak terdaftar melempar RouteNotFoundException — error 500 di
 * tempat yang seharusnya menampilkan halaman masuk. Saat dimatikan, halaman itu
 * mengalihkan ke SSO dan jalur POST-nya menolak 404.
 *
 * DUA PINTU, SATU TABEL. /login untuk semua pegawai, /admin/login khusus
 * pemegang role Administrator. Yang berbeda hanya gerbang role setelah orangnya
 * dikenali, dan layar tempat ia mendarat. Marcell (Requester + Administrator)
 * sah lewat dua-duanya.
 */
class DevLoginController extends Controller
{
    /** Kunci role yang boleh lewat pintu admin. */
    private const ADMIN_KEY = 'admin';

    public function showLogin()
    {
        return $this->renderForm(isAdminPortal: false);
    }

    public function showAdminLogin()
    {
        return $this->renderForm(isAdminPortal: true);
    }

    public function login(Request $request): RedirectResponse
    {
        return $this->attempt($request, isAdminPortal: false);
    }

    public function adminLogin(Request $request): RedirectResponse
    {
        return $this->attempt($request, isAdminPortal: true);
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        // Sesi SSO hidup di kunci sesinya sendiri, bukan di guard Laravel.
        // Tanpa baris ini "keluar" hanya melepas separuh identitas: guard-nya
        // kosong tapi CurrentActor masih menemukan orang yang sama lewat
        // SsoAuthenticator, dan layar role tetap terbuka setelah logout.
        $request->session()->forget([
            SsoAuthenticator::SESSION_KEY,
            SsoAuthenticator::SESSION_NAME,
        ]);

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    /** Halaman masuk — atau pengalihan ke SSO bila login pengembangan dimatikan. */
    private function renderForm(bool $isAdminPortal)
    {
        if (! self::enabled()) {
            return redirect()->route('sso.login');
        }

        if (Auth::check()) {
            return redirect()->route(
                RoleRegistry::landingRouteFor(Auth::user(), $isAdminPortal ? self::ADMIN_KEY : null)
            );
        }

        // Halaman ini tidak menautkan ke pintu lain: /admin/login dicapai dengan
        // mengetiknya langsung, dan SSO SINTA tidak ditawarkan dari sini karena
        // di lingkungan tempat halaman ini hidup, SSO-nya memang belum nyata.
        return view('auth.dev-login', [
            'isAdminPortal' => $isAdminPortal,
            'action' => $isAdminPortal ? route('admin.login.attempt') : route('login.attempt'),
        ]);
    }

    private function attempt(Request $request, bool $isAdminPortal): RedirectResponse
    {
        abort_unless(self::enabled(), 404);

        $data = $request->validate([
            // `email` sebagai aturan, bukan sekadar `string`: salah ketik yang
            // jelas-jelas bukan alamat ditolak di muka dengan pesan yang tepat,
            // alih-alih diteruskan ke basis data lalu kembali sebagai "akun
            // tidak ditemukan" yang menyesatkan.
            'email' => ['required', 'string', 'email', 'max:255'],
        ]);

        $user = $this->findByEmail(trim($data['email']));

        if (! $user) {
            throw ValidationException::withMessages([
                'email' => 'Tidak ada akun dengan email itu.',
            ]);
        }

        if (! $user->isActive()) {
            RefusedLoginAudit::record($user, $isAdminPortal ? 'admin_login' : 'login');

            throw ValidationException::withMessages([
                'email' => "Akun {$user->name} tidak dapat mengakses Helpdesk: "
                    .lcfirst((string) $user->inactiveReason()).'.',
            ]);
        }

        if ($isAdminPortal && ! $user->roles->contains('name', RoleRegistry::roleNameFor(self::ADMIN_KEY))) {
            throw ValidationException::withMessages([
                'email' => "Akun {$user->name} tidak punya role Administrator.",
            ]);
        }

        // Wajib SEBELUM apa pun ditulis ke sesi: menahan session fixation —
        // id sesi yang sempat ditanam sebelum login tidak ikut terbawa ke sesi
        // yang sudah terautentikasi.
        $request->session()->regenerate();

        Auth::login($user, $request->boolean('remember'));

        $user->forceFill(['last_login_at' => now()])->save();

        $landing = route(RoleRegistry::landingRouteFor($user, $isAdminPortal ? self::ADMIN_KEY : null));

        return redirect()->to($this->intendedUrlFor($user) ?? $landing);
    }

    /**
     * Cari akun lewat email.
     *
     * Sempat menerima nomor telepon juga sebagai alternatif; itu dicabut atas
     * permintaan — satu jalur masuk lebih sedikit untuk salah paham, dan kolom
     * `phone` memang tidak dijamin unik oleh skema mana pun.
     *
     * LOWER() di kedua sisi supaya perilakunya sama di MySQL (collation-nya
     * umumnya sudah case-insensitive) dan SQLite (yang `=`-nya tidak) — tanpa
     * ini, "Andi@adhi.co.id" gagal di tes tapi berhasil di server, atau
     * sebaliknya.
     *
     * Lebih dari satu akun cocok = DITOLAK, bukan diambil yang pertama.
     *
     * Ada indeks unik `users_email_unique`, tapi itu TIDAK menutup celahnya:
     * keunikan diperiksa apa adanya, sedangkan pencarian di sini lewat LOWER().
     * Di basis data yang perbandingannya peka huruf besar-kecil, "A@adhi.co.id"
     * dan "a@adhi.co.id" adalah dua baris yang sah menurut indeks — dan
     * keduanya cocok dengan satu pencarian yang sama. Mengambil yang pertama
     * diam-diam berarti kadang masuk sebagai orang yang salah, persis kekeliruan
     * yang paling sulit disadari saat sedang menguji hak akses.
     */
    private function findByEmail(string $email): ?User
    {
        $cocok = User::whereRaw('LOWER(email) = ?', [Str::lower($email)])->get();

        if ($cocok->count() > 1) {
            throw ValidationException::withMessages([
                'email' => "Ada {$cocok->count()} akun dengan email itu, jadi tidak jelas yang mana yang dimaksud. "
                    .'Minta Administrator merapikan datanya terlebih dahulu.',
            ]);
        }

        return $cocok->first();
    }

    /**
     * URL yang tadi hendak dibuka — TAPI hanya kalau orang ini memang boleh
     * membukanya.
     *
     * `redirect()->intended()` polos mengembalikan URL apa adanya, dan itu
     * mengantar orang tepat ke layar penolakan: seorang Requester yang membuka
     * tautan /dashboard/support diantar ke halaman masuk, berhasil masuk, lalu
     * disambut 403 — seolah-olah login-nya yang gagal. Gerbang role-nya sendiri
     * bekerja benar; yang salah adalah menyuruh orang berjalan ke tembok.
     *
     * Rutenya dicocokkan ulang untuk membaca middleware `role:` yang menjaganya,
     * jadi aturan yang dipakai di sini persis aturan yang sama dengan yang akan
     * menolaknya nanti — bukan salinan kedua yang bisa berbeda pendapat.
     */
    private function intendedUrlFor(User $user): ?string
    {
        $intended = session()->pull('url.intended');

        if (! is_string($intended) || $intended === '') {
            return null;
        }

        try {
            $route = Route::getRoutes()->match(Request::create($intended));
        } catch (\Throwable) {
            // Bukan rute yang dikenal (atau bukan GET) — antar ke layar
            // pendaratan biasa alih-alih menebak.
            return null;
        }

        foreach ($route->gatherMiddleware() as $middleware) {
            if (! is_string($middleware) || ! str_starts_with($middleware, 'role:')) {
                continue;
            }

            $allowed = collect(explode(',', substr($middleware, strlen('role:'))))
                ->map(fn (string $key) => RoleRegistry::roleNameFor(trim($key)));

            if (! $allowed->contains(fn (string $name) => $user->roles->contains('name', $name))) {
                return null;
            }
        }

        return $intended;
    }

    public static function enabled(): bool
    {
        return (bool) config('helpdesk.dev_login');
    }
}
