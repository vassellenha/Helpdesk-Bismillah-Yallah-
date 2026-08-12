<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Models\User;

/**
 * Konsol EVA dijaga `auth` + `role:eva`: request tanpa identitas ditolak 401,
 * dan identitas tanpa role Knowledge Administrator ditolak 403. Tes yang
 * menguji jalur admin — hampir semuanya — perlu melewati keduanya.
 *
 * TIGA role, bukan satu. Gerbang rutenya memang hanya menuntut 'eva', tapi
 * controller di konsol ini memanggil `CurrentActor::admin()` (untuk
 * mengatribusikan baris audit trail) dan `CurrentActor::requester()` (jalur
 * draf tiket dari widget), dan sejak persona tetap dicabut, kedua panggilan itu
 * menolak siapa pun yang tidak memegang role bersangkutan. Persona yang hanya
 * ber-role EVA akan lolos gerbang rute lalu jatuh 403 di dalam controller —
 * kegagalan yang terbaca seperti bug rute, padahal soal role.
 *
 * NIP-nya dipertahankan supaya baris yang sudah disemai tes lain dipakai ulang,
 * bukan ditambah kembar yang menggeser hitungan `users`.
 */
trait ActsAsEvaAdmin
{
    use ActsAsRole;

    protected function actingAsEvaAdmin(): User
    {
        $admin = User::firstWhere('nip', '19870114001')
            ?? User::factory()->create([
                'name' => 'Marcell Laforteza',
                'email' => 'marcell.laforteza@adhi.co.id',
                'nip' => '19870114001',
                // UserFactory tidak menyetel dua saklar keaktifan ini, dan
                // keduanya jatuh ke non-aktif — persona yang terkunci ditolak
                // CurrentActor::mustBeActive() sebelum layar mana pun terbuka.
                'status' => 'active',
                'helpdesk_access' => 'enabled',
            ]);

        return $this->actingAsUserWithRoles($admin, 'eva', 'admin', 'requester');
    }
}
