<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\SupportAgent;
use App\Models\User;

/**
 * Menyelaraskan baris support_agents dengan role yang dipegang seorang user.
 *
 * DUA HAL YANG SELAMA INI TERPISAH. `roles` menentukan siapa boleh MEMBUKA
 * layar Support; `support_agents` adalah identitas KERJANYA — barisan yang
 * dirujuk tickets.assigned_agent_id. Layar Support mencari barisnya dengan
 * firstOrFail(), jadi user yang punya rolenya tapi belum punya barisnya
 * mendapat 404 telanjang.
 *
 * Sebelum kelas ini ada, satu-satunya pembuat baris support_agents adalah
 * seeder. Artinya Administrator bisa memberi role "Support IT" lewat layar
 * admin — tombolnya ada, audit trailnya tercatat, semuanya tampak berhasil —
 * lalu orang itu menemukan layarnya 404. Kegagalan yang tidak menyalahkan
 * siapa pun dan tidak menunjuk ke mana pun.
 *
 * PENCABUTAN TIDAK PERNAH MENGHAPUS BARIS. Tiket menunjuk ke agent lewat
 * assigned_agent_id; menghapusnya akan memutus riwayat tiket yang sudah
 * ditangani orang itu. Yang dilakukan hanya menonaktifkan, sehingga ia hilang
 * dari daftar penugasan tapi jejaknya utuh.
 */
final class SupportAgentSync
{
    /** Nama role → tipe agent di support_agents. */
    private const ROLE_TO_TYPE = [
        'Support IT' => 'it',
        'Support BPO' => 'bpo',
    ];

    public static function reconcile(User $user): void
    {
        $user->loadMissing('roles');
        $roleNames = $user->roles->pluck('name');

        foreach (self::ROLE_TO_TYPE as $role => $type) {
            $punyaRole = $roleNames->contains($role);
            $agent = SupportAgent::where('user_id', $user->id)->where('type', $type)->first();

            if ($punyaRole) {
                self::aktifkan($user, $type, $agent);

                continue;
            }

            $agent?->update(['is_active' => false]);
        }
    }

    private static function aktifkan(User $user, string $type, ?SupportAgent $agent): void
    {
        if ($agent !== null) {
            // Namanya ikut disegarkan: agent yang dulu dinonaktifkan lalu
            // diberi rolenya kembali harus muncul dengan nama terbarunya,
            // bukan nama saat ia pertama didaftarkan.
            $agent->update(['is_active' => true, 'name' => $user->name]);

            return;
        }

        SupportAgent::create([
            'name' => $user->name,
            'type' => $type,
            'is_active' => true,
            'user_id' => $user->id,
        ]);
    }
}
