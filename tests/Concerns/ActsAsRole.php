<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Models\Role;
use App\Models\User;
use App\Support\RoleRegistry;

/**
 * Masuk sebagai seseorang yang memegang role tertentu.
 *
 * Sejak seluruh rute dijaga `auth` + `role:`, request tanpa identitas ditolak
 * sebelum menyentuh controller — dan identitas tanpa role yang tepat ditolak
 * sesudahnya. Tes yang menguji isi sebuah layar harus melewati keduanya lebih
 * dulu, jadi trait ini mengurus dua-duanya sekaligus.
 *
 * Menerima KUNCI role (kunci config/helpdesk.php: 'admin', 'support-bpo', …),
 * bukan nama barisnya di basis data, sehingga tes memakai kosakata yang sama
 * dengan daftar rutenya sendiri.
 *
 * Baris Role-nya dibuat kalau belum ada: sebagian besar tes memakai
 * RefreshDatabase dengan tabel kosong, dan menuntut setiap tes menyemai role
 * lebih dulu hanya memindahkan pekerjaan yang sama ke lima puluh tempat.
 */
trait ActsAsRole
{
    /**
     * @param  string  ...$keys  kunci role di config/helpdesk.php
     */
    protected function actingAsRole(string ...$keys): User
    {
        // UserFactory tidak menyetel `status` maupun `helpdesk_access`, dan
        // keduanya jatuh ke nilai non-aktif. Aktor yang dibuat di sini harus
        // bisa lewat CurrentActor::mustBeActive(), jadi keaktifannya disebut
        // eksplisit — tes yang justru menguji akun terkunci membuat usernya
        // sendiri lalu memakai actingAsUserWithRoles().
        $user = User::factory()->create([
            'status' => 'active',
            'helpdesk_access' => 'enabled',
        ]);

        return $this->actingAsUserWithRoles($user, ...$keys);
    }

    /** Sematkan role ke user yang sudah ada, lalu masuk sebagai dia. */
    protected function actingAsUserWithRoles(User $user, string ...$keys): User
    {
        $roleIds = collect($keys)
            ->map(fn (string $key) => Role::firstOrCreate(
                ['name' => RoleRegistry::roleNameFor($key)],
                ['type' => 'system', 'status' => 'active'],
            )->id)
            ->all();

        $user->roles()->syncWithoutDetaching($roleIds);

        // Tanpa ini, relasi `roles` yang mungkin sudah termuat sebelumnya tetap
        // memakai isi lamanya — dan pemeriksaan role di middleware membaca
        // relasi itu, bukan tabelnya.
        $user->unsetRelation('roles');

        $this->actingAs($user);

        return $user;
    }
}
