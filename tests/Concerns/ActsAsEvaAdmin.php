<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Models\User;

/**
 * Konsol EVA dijaga middleware `eva.access`: request tanpa identitas ditolak
 * 401 sebelum sampai ke controller. Tes yang menguji jalur admin — hampir
 * semuanya — perlu membawa identitas itu.
 *
 * Persona yang dipakai sengaja SAMA dengan yang dicari `CurrentActor::admin()`
 * lewat email. Kalau tesnya sudah menyemai persona itu sendiri, baris yang ada
 * dipakai ulang alih-alih menambah baris kembar yang bisa menggeser hitungan
 * `users` di tes lain.
 */
trait ActsAsEvaAdmin
{
    protected function actingAsEvaAdmin(): User
    {
        $admin = User::firstWhere('email', 'marcell.laforteza@adhi.co.id')
            ?? User::factory()->create([
                'name' => 'Marcell Laforteza',
                'email' => 'marcell.laforteza@adhi.co.id',
            ]);

        $this->actingAs($admin);

        return $admin;
    }
}
