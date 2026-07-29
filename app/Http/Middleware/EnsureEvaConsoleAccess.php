<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\CurrentActor;
use Closure;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Penjaga konsol EVA — penampung sementara sampai integrasi SSO ADHI ada.
 *
 * Kenapa bukan `middleware('auth')` bawaan Laravel: repo ini belum punya login
 * flow sama sekali. Tidak ada route bernama `login`, dan setiap aksi admin
 * diatribusikan ke persona tunggal `App\Support\CurrentActor`. Middleware
 * `auth` bawaan akan me-redirect tamu ke `route('login')` yang tidak terdaftar
 * — hasilnya RouteNotFoundException alias 500, bukan penolakan yang terbaca.
 *
 * Yang dilakukan di sini: tamu ditolak 401. Titik. Bentuk balasannya (JSON
 * untuk `eva/api/*`, halaman error untuk layar) diurus penangan exception di
 * bootstrap/app.php, jadi tidak perlu dicabang di sini.
 *
 * Saat SSO terpasang, yang berubah hanya isi method ini — identitas diresolusi
 * dari token/header SSO alih-alih guard sesi lokal. Permukaan route tidak ikut
 * berubah, dan tes penjaga di AdversarialAuditTest tetap berlaku apa adanya.
 */
final class EnsureEvaConsoleAccess
{
    private const DENIAL_MESSAGE = 'Konsol EVA butuh identitas pemakai. '
        .'Belum ada integrasi SSO di lingkungan ini, jadi akses ditolak.';

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() !== null) {
            return $next($request);
        }

        if ($this->attachLocalActor()) {
            return $next($request);
        }

        abort(401, self::DENIAL_MESSAGE);
    }

    /**
     * Jembatan khusus mesin pengembang: tanpa SSO tidak ada cara login apa pun,
     * jadi menutup pintu tanpa jalan masuk sama saja mematikan konsol untuk tim
     * yang sedang mengisi Knowledge Base.
     *
     * Hanya aktif kalau `eva.console.local_actor` bernilai true — default-nya
     * mengikuti APP_ENV=local, sehingga `testing` dan `production` tetap
     * menolak tamu. Ini yang PERTAMA harus dicabut begitu SSO tersedia.
     */
    private function attachLocalActor(): bool
    {
        if (! config('eva.console.local_actor')) {
            return false;
        }

        try {
            Auth::setUser(CurrentActor::admin());
        } catch (ModelNotFoundException) {
            // Persona seed-nya belum ada. Menolak lebih jujur daripada
            // meledak 404 di tengah request yang sebenarnya soal identitas.
            return false;
        }

        return true;
    }
}
