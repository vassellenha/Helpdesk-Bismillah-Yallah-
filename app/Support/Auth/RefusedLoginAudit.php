<?php

declare(strict_types=1);

namespace App\Support\Auth;

use App\Models\AuditTrail;
use App\Models\User;

/**
 * Satu baris audit untuk login yang DITOLAK karena akunnya tidak berhak masuk.
 *
 * Kenapa bukan listener seperti RecordLoginAudit? Karena tidak ada event yang
 * bisa ditumpangi. `Illuminate\Auth\Events\Failed` hanya menyala kalau login
 * lewat Auth::attempt() dengan kredensial, sementara kedua pintu helpdesk
 * memanggil Auth::login() langsung setelah memutuskan sendiri siapa yang
 * boleh masuk. Menyalakan event palsu hanya demi bisa didengarkan justru
 * menambah lapisan tanpa menambah jaminan.
 *
 * Kenapa bukan di dalam User::isActive()? Karena predikat itu dipanggil di
 * mana-mana — menyusun daftar approver, menyaring pilihan penugasan — dan
 * menulis audit dari sana akan membanjiri tabel dengan "penolakan" yang tidak
 * pernah benar-benar terjadi.
 *
 * Jadi tersisa dua pemanggil eksplisit, dan keduanya adalah tempat keputusan
 * "orang ini tidak boleh masuk" benar-benar diambil:
 *   - Auth\DevLoginController (pintu email, hanya hidup di non-produksi)
 *   - SsoAuthenticator::resolve() (dipakai callback SSO maupun entry link)
 *
 * Percobaan memakai identitas yang sama sekali tidak punya akun helpdesk
 * sengaja TIDAK ditulis ke sini: kolom actor_id wajib menunjuk satu user, dan
 * di kasus itu tidak ada orang yang bisa ditunjuk. Percobaan semacam itu sudah
 * tercatat sebagai peringatan di log aplikasi oleh SsoAuthenticator::resolve().
 */
final class RefusedLoginAudit
{
    public static function record(User $user, string $door): void
    {
        $reason = (string) $user->inactiveReason();

        AuditTrail::record($user, [
            'module' => 'auth',
            'action' => 'login_failed',
            'target_type' => 'user',
            'target_id' => $user->id,
            'target_name' => $user->name,
            'new_value' => ['reason' => $reason, 'door' => $door],
            'description' => "Percobaan login {$user->name} ditolak: ".lcfirst($reason).'.',
        ]);
    }
}
