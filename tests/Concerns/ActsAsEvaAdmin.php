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
 * — lewat NIP, bukan email (sejak 31 Juli 2026; lihat komentar di
 * CurrentActor::admin()). Kalau tesnya sudah menyemai persona itu sendiri,
 * baris yang ada dipakai ulang alih-alih menambah baris kembar yang bisa
 * menggeser hitungan `users` di tes lain.
 *
 * NIP wajib disertakan di sini: `actingAs()` cuma membuat request ini
 * TERAUTENTIKASI sebagai user tersebut — banyak controller di konsol EVA
 * masih memanggil `CurrentActor::admin()` sendiri di dalamnya (mis. untuk
 * mengatribusikan baris audit trail), dan pemanggilan itu mencari lewat NIP
 * tanpa peduli siapa yang sedang login lewat `actingAs()`. Tanpa NIP yang
 * cocok, `CurrentActor::admin()` gagal firstOrFail() dan seluruh route
 * berakhir 404 — persis kelas kerusakan yang memicu perubahan ini di
 * database dev sungguhan.
 */
trait ActsAsEvaAdmin
{
    protected function actingAsEvaAdmin(): User
    {
        $admin = User::firstWhere('nip', '19870114001')
            ?? User::factory()->create([
                'name' => 'Marcell Laforteza',
                'email' => 'marcell.laforteza@adhi.co.id',
                'nip' => '19870114001',
            ]);

        $this->actingAs($admin);

        return $admin;
    }
}
