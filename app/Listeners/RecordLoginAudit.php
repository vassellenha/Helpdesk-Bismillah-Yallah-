<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\AuditTrail;
use App\Models\User;
use Illuminate\Auth\Events\Login;

/**
 * Satu baris audit untuk SETIAP login, dari pintu mana pun.
 *
 * Dulu pencatatan ini duduk di dalam SsoAuthenticator::login(), dengan komentar
 * yang menyebutnya "satu-satunya tempat sebuah login benar-benar terjadi".
 * Premis itu benar selama SSO memang satu-satunya pintu — dan patah diam-diam
 * begitu login pengembangan ditambahkan: jalur baru itu memanggil Auth::login()
 * sendiri, tidak lewat SsoAuthenticator, sehingga tidak ada yang tercatat.
 * Tidak ada error, tidak ada yang gagal; audit trail-nya hanya kosong, dan itu
 * baru ketahuan saat seseorang mencarinya.
 *
 * Dipindahkan ke listener supaya sifat "satu tempat" itu dijamin oleh MEKANISME,
 * bukan oleh ingatan: apa pun yang memanggil Auth::login() memicu event ini,
 * termasuk pintu masuk yang ditulis besok.
 *
 * TIDAK didaftarkan manual di AppServiceProvider. Laravel menemukan sendiri
 * listener di app/Listeners lewat tipe argumen handle(); mendaftarkannya sekali
 * lagi secara eksplisit membuatnya terpasang DUA kali, dan setiap login
 * menghasilkan dua baris audit yang identik. (Ditemukan begitu tes di
 * LoginAuditTest menghitung barisnya.)
 *
 * `actingAs()` di tes TIDAK memicunya — ia memasang user langsung ke guard tanpa
 * melewati SessionGuard::login() — jadi ratusan tes yang sekadar butuh identitas
 * tidak ikut menghasilkan baris audit palsu.
 */
final class RecordLoginAudit
{
    public function handle(Login $event): void
    {
        $user = $event->user;

        if (! $user instanceof User) {
            return;
        }

        AuditTrail::record($user, [
            'module' => 'auth',
            'action' => 'login',
            'target_type' => 'user',
            'target_id' => $user->id,
            'target_name' => $user->name,
            'new_value' => ['roles' => $user->roles->pluck('name')->values()->all()],
            'description' => "{$user->name} login ke helpdesk.",
        ]);
    }
}
