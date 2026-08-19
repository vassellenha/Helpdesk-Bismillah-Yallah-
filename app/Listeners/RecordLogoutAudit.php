<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\AuditTrail;
use App\Models\User;
use Illuminate\Auth\Events\Logout;

/**
 * Satu baris audit untuk SETIAP logout, dari pintu mana pun.
 *
 * Pasangan dari RecordLoginAudit, dan dipasang pada event dengan alasan yang
 * sama: helpdesk punya dua pintu keluar — DevLoginController::logout() untuk
 * login pengembangan dan SsoController::logout() lewat SsoAuthenticator —
 * keduanya memanggil Auth::logout(). Menuliskan pencatatannya di salah satu
 * controller berarti pintu yang lain diam, dan pintu ketiga yang ditulis besok
 * ikut diam tanpa ada yang sadar.
 *
 * Sama seperti kembarannya, listener ini TIDAK didaftarkan manual di
 * AppServiceProvider — Laravel menemukannya sendiri lewat tipe argumen
 * handle(). Mendaftarkan ulang membuatnya terpasang dua kali dan setiap logout
 * menghasilkan dua baris kembar.
 */
final class RecordLogoutAudit
{
    public function handle(Logout $event): void
    {
        $user = $event->user;

        // SessionGuard::logout() melempar event ini apa adanya, termasuk ketika
        // guard-nya sudah kosong — menekan "keluar" dua kali, atau membuka URL
        // logout sebagai tamu, sampai ke sini dengan user null. Tanpa penjagaan
        // ini audit trail terisi baris tanpa pelaku.
        if (! $user instanceof User) {
            return;
        }

        AuditTrail::record($user, [
            'module' => 'auth',
            'action' => 'logout',
            'target_type' => 'user',
            'target_id' => $user->id,
            'target_name' => $user->name,
            'description' => "{$user->name} keluar dari helpdesk.",
        ]);
    }
}
