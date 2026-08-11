<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\RoleRegistry;
use App\Support\Sso\SsoAuthenticator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Login pengembangan — email ATAU nomor telepon, TANPA password.
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
            'identifier' => ['required', 'string', 'max:255'],
        ], [], ['identifier' => 'email atau nomor telepon']);

        $user = $this->findBy(trim($data['identifier']));

        if (! $user) {
            throw ValidationException::withMessages([
                'identifier' => 'Tidak ada akun dengan email atau nomor telepon itu.',
            ]);
        }

        if (! $user->isActive()) {
            throw ValidationException::withMessages([
                'identifier' => "Akun {$user->name} tidak dapat mengakses Helpdesk: "
                    .lcfirst((string) $user->inactiveReason()).'.',
            ]);
        }

        if ($isAdminPortal && ! $user->roles->contains('name', RoleRegistry::roleNameFor(self::ADMIN_KEY))) {
            throw ValidationException::withMessages([
                'identifier' => "Akun {$user->name} tidak punya role Administrator.",
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
     * Cari akun lewat email ATAU nomor telepon — salah satu saja, satu kolom
     * isian yang sama.
     *
     * Email didahulukan karena semua 29 akun punya email sedangkan hanya
     * sebagian punya telepon, jadi jalur itu yang paling sering kena.
     *
     * Lebih dari satu akun cocok = DITOLAK, bukan diambil yang pertama.
     * Nomor telepon tidak dijamin unik oleh skema mana pun, dan sinkronisasi
     * pegawai bisa saja memasukkan dua orang dengan nomor kantor yang sama.
     * Memilih salah satunya diam-diam berarti kadang-kadang masuk sebagai orang
     * yang salah — persis kelas kekeliruan yang paling sulit disadari saat
     * sedang menguji hak akses.
     */
    private function findBy(string $identifier): ?User
    {
        // Email: dibandingkan case-insensitive lewat LOWER() supaya perilakunya
        // sama di MySQL (collation umumnya sudah case-insensitive) dan SQLite
        // (yang `=`-nya tidak).
        $byEmail = User::whereRaw('LOWER(email) = ?', [Str::lower($identifier)])->get();

        if ($byEmail->count() > 1) {
            $this->rejectAmbiguous($byEmail->count());
        }

        if ($byEmail->count() === 1) {
            return $byEmail->first();
        }

        $variants = self::phoneVariants($identifier);

        if ($variants === []) {
            return null;
        }

        $byPhone = User::whereIn('phone', $variants)->get();

        if ($byPhone->count() > 1) {
            $this->rejectAmbiguous($byPhone->count());
        }

        return $byPhone->first();
    }

    private function rejectAmbiguous(int $jumlah): never
    {
        throw ValidationException::withMessages([
            'identifier' => "Ada {$jumlah} akun dengan data itu, jadi tidak jelas yang mana yang dimaksud. "
                .'Pakai email yang spesifik, atau minta Administrator merapikan datanya.',
        ]);
    }

    /**
     * Bentuk-bentuk penulisan nomor yang sama.
     *
     * Data tersimpan dalam format `+6281…`, tapi yang mengetik hampir selalu
     * menulis `081…`. Menuntut satu bentuk persis akan membuat login by telepon
     * tampak rusak padahal nomornya benar, jadi keempat bentuk yang lazim
     * dicoba sekaligus.
     *
     * @return list<string>
     */
    private static function phoneVariants(string $input): array
    {
        $digits = preg_replace('/\D+/', '', $input) ?? '';

        // Terlalu pendek untuk sebuah nomor: hampir pasti ini email yang tidak
        // ketemu, dan mencocokkannya ke kolom telepon hanya mengundang
        // kecocokan kebetulan.
        if (strlen($digits) < 7) {
            return [];
        }

        $lokal = match (true) {
            str_starts_with($digits, '62') => substr($digits, 2),
            str_starts_with($digits, '0') => substr($digits, 1),
            default => $digits,
        };

        return array_values(array_unique(['+62'.$lokal, '62'.$lokal, '0'.$lokal, $lokal]));
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
