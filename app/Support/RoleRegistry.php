<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Jembatan antara kunci role di URL/tampilan ('support-bpo') dan nama baris di
 * tabel `roles` ('Support BPO').
 *
 * Petanya sendiri hidup di config/helpdesk.php, bukan di sini — kelas ini hanya
 * satu-satunya PEMBACA-nya. Tiga hal berbeda butuh peta itu (middleware `role:`,
 * CurrentActor saat menentukan aktor, dan tombol switch role saat menyusun
 * daftar), dan sebelum ada kelas ini masing-masing akan menuliskan sendiri
 * pasangan 'support-bpo' => 'Support BPO'. Tiga salinan berarti tiga peluang
 * mereka berbeda pendapat, dan yang paling mungkin salah adalah yang paling
 * jarang dibaca orang.
 */
final class RoleRegistry
{
    /** @return array<string, array<string, mixed>> */
    public static function all(): array
    {
        return (array) config('helpdesk.roles', []);
    }

    /**
     * Nama role di basis data untuk sebuah kunci.
     *
     * Melempar, bukan mengembalikan null: pemanggilnya adalah middleware yang
     * menjaga rute. Kunci yang salah tulis di daftar rute harus berbunyi keras
     * saat halaman dibuka — kalau ia diam-diam mengembalikan null, penjaganya
     * berubah jadi "tidak ada role yang cocok" dan rutenya jadi terbuka untuk
     * siapa saja. Gagal berisik jauh lebih murah daripada gerbang yang diam.
     */
    public static function roleNameFor(string $key): string
    {
        $name = self::all()[$key]['role'] ?? null;

        if (! is_string($name) || $name === '') {
            throw new \InvalidArgumentException(
                "Kunci role '{$key}' tidak dikenal. Kunci yang tersedia: "
                .implode(', ', array_keys(self::all())).'.'
            );
        }

        return $name;
    }

    /** Kunci untuk sebuah nama role di basis data, null bila tidak dipetakan. */
    public static function keyFor(string $roleName): ?string
    {
        foreach (self::all() as $key => $meta) {
            if (($meta['role'] ?? null) === $roleName) {
                return (string) $key;
            }
        }

        return null;
    }

    /**
     * Role yang BENAR-BENAR dipegang user, siap dipakai tombol switch role.
     *
     * Urutannya mengikuti config, bukan urutan baris di basis data, supaya
     * daftarnya tampil sama untuk semua orang.
     *
     * @return Collection<int, array<string, string>>
     */
    public static function switcherEntriesFor(User $user): Collection
    {
        $held = $user->roles->pluck('name')->all();

        return collect(self::all())
            ->filter(fn (array $meta) => in_array($meta['role'] ?? null, $held, true))
            ->map(fn (array $meta) => [
                'key' => (string) $meta['key'],
                'initials' => (string) $meta['initials'],
                'label' => (string) $meta['label'],
                'url' => route(self::landingRouteName((string) $meta['key'])),
            ])
            ->values();
    }

    /** Layar pertama sebuah role — tujuan mendarat setelah login dan setelah pindah role. */
    public static function landingRouteName(string $key): string
    {
        return (string) (self::all()[$key]['links'][0]['route'] ?? 'portal.index');
    }

    /**
     * Ke mana user diantar setelah login.
     *
     * Kunci yang disukai (misalnya 'admin' untuk yang masuk lewat /admin/login)
     * menang bila memang dipegang; selain itu jatuh ke role pertama menurut
     * urutan config. User tanpa satu pun role yang dipetakan diantar ke portal,
     * yang akan menjelaskan sendiri bahwa ia belum punya akses ke mana-mana.
     */
    public static function landingRouteFor(User $user, ?string $preferKey = null): string
    {
        $entries = self::switcherEntriesFor($user);

        if ($preferKey !== null && $entries->contains('key', $preferKey)) {
            return self::landingRouteName($preferKey);
        }

        $first = $entries->first();

        return $first ? self::landingRouteName($first['key']) : 'portal.index';
    }
}
