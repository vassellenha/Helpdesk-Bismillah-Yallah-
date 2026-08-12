<?php

namespace App\Support;

use App\Models\SlaPolicy;

/**
 * Satu-satunya tempat yang tahu tingkat prioritas apa saja yang ada.
 *
 * Sebelumnya daftar `['Critical','High','Medium','Low']` ditulis ulang di tiga
 * belas tempat — filter, grafik, distribusi, urutan sortir. Selama nilainya
 * dikunci enum di database, duplikasi itu tidak berbahaya. Begitu Admin boleh
 * membuat prioritas baru, tiap salinan yang tertinggal berubah jadi layar yang
 * diam-diam menyembunyikan tiket: "Urgent" tidak ada di daftar filter, jadi
 * tiketnya tidak pernah ikut terhitung, dan tidak ada pesan error apa pun.
 *
 * Sumbernya adalah SLA Policy yang aktif, bukan daftar tetap: prioritas memang
 * lahir dari sana — layar tiket baru pun hanya menawarkan priority yang punya
 * policy aktif.
 */
class PriorityRegistry
{
    /**
     * Dipakai saat belum ada satu pun policy aktif — instalasi baru, atau
     * seluruh policy sedang dinonaktifkan. Tanpa ini, layar yang menghitung
     * distribusi per prioritas akan tampil kosong dan terbaca seperti rusak.
     */
    public const FALLBACK = ['Critical', 'High', 'Medium', 'Low'];

    private const COLORS = [
        'Critical' => '#dc2626',
        'High' => '#d97706',
        'Medium' => '#2563eb',
        'Low' => '#9ca3af',
    ];

    /** Abu-abu netral untuk prioritas buatan Admin yang belum punya warna sendiri. */
    private const DEFAULT_COLOR = '#64748b';

    /** @var array<int,string>|null */
    private static ?array $cache = null;

    /**
     * Semua prioritas aktif, terurut dari yang paling mendesak.
     *
     * Urutannya diturunkan dari target penyelesaian, BUKAN dari daftar hardcoded
     * seperti `field(priority,'Critical','High',…)` yang dipakai sebelumnya.
     * Daftar semacam itu menempatkan setiap prioritas baru di urutan terakhir,
     * berapa pun ketatnya SLA-nya — "Urgent" dengan target 60 menit akan tampil
     * di bawah "Low". Target waktu adalah definisi mendesak itu sendiri, jadi ia
     * mengurutkan dirinya sendiri dan tidak pernah perlu dirawat.
     *
     * @return array<int,string>
     */
    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $priorities = SlaPolicy::query()
            ->where('status', 'active')
            ->groupBy('priority')
            ->orderByRaw('MIN(resolution_time_minutes)')
            ->pluck('priority')
            ->filter()
            ->values()
            ->all();

        return self::$cache = $priorities !== [] ? $priorities : self::FALLBACK;
    }

    /** Warna grafik untuk sebuah prioritas; netral kalau belum dikenal. */
    public static function colorFor(string $priority): string
    {
        return self::COLORS[$priority] ?? self::DEFAULT_COLOR;
    }

    /**
     * Peta prioritas => warna untuk seluruh prioritas aktif.
     *
     * @return array<string,string>
     */
    public static function colors(): array
    {
        $colors = [];

        foreach (self::all() as $priority) {
            $colors[$priority] = self::colorFor($priority);
        }

        return $colors;
    }

    /** Dibuang di antara tes; satu proses HTTP hanya perlu membacanya sekali. */
    public static function flush(): void
    {
        self::$cache = null;
    }
}
