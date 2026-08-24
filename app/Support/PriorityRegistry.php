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

    /**
     * Tangga warna dari paling mendesak ke paling longgar.
     *
     * Warna TIDAK lagi dipetakan dari nama ('Critical' => merah). Peta nama
     * hanya benar selama namanya persis empat kata bahasa Inggris itu: begitu
     * Admin mengganti "Critical" jadi "Kritikal", namanya tidak ada di peta,
     * badge-nya jatuh ke abu-abu, dan prioritas paling genting di sistem
     * tampil sepucat prioritas paling santai — persis yang terlihat di layar.
     *
     * Yang dipakai sekarang adalah posisinya di antara prioritas lain, diukur
     * dari target penyelesaian. Nama boleh apa saja, dalam bahasa apa saja:
     * yang paling ketat selalu merah, yang paling longgar selalu abu-abu.
     */
    private const COLOR_SCALE = [
        '#dc2626', // merah    — paling mendesak
        '#ea580c', // jingga
        '#d97706', // amber
        '#0d9488', // teal
        '#2563eb', // biru
        '#64748b', // slate
        '#9ca3af', // abu-abu  — paling longgar
    ];

    /** Lencana sejajar tangga warna, dari paling mendesak ke paling longgar. */
    private const GLYPH_SCALE = ['⚠', '!', '=', '≡'];

    /** Dipakai kalau prioritasnya tidak dikenal sama sekali (mis. tiket lama). */
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

    /**
     * Warna grafik untuk sebuah prioritas, diturunkan dari peringkat
     * ketatnya SLA — bukan dari namanya.
     *
     * Dengan empat prioritas bawaan hasilnya sama persis seperti dulu
     * (merah/oranye/biru/abu), jadi layar yang sudah ada tidak berubah rupa.
     */
    public static function colorFor(string $priority): string
    {
        $priorities = self::all();
        $index = array_search($priority, $priorities, true);

        if ($index === false) {
            return self::DEFAULT_COLOR;
        }

        return self::colorAt($index, count($priorities));
    }

    /**
     * Warna pada posisi ke-$index dari $total prioritas.
     *
     * Posisinya dipetakan ke seluruh rentang tangga warna lalu dibulatkan ke
     * anak tangga TERDEKAT — bukan dicampur di antara keduanya. Mencampur
     * warna terdengar lebih halus, tapi tangga ini melompati corak: campuran
     * oranye dan biru di tengah menghasilkan abu-abu lumpur (#7f6d79), yang
     * justru membuat prioritas menengah tampak lebih pucat daripada prioritas
     * paling longgar. Membulatkan menjaga setiap warna tetap warna yang
     * memang dipilih untuk dibaca cepat.
     *
     * Anak tangganya sengaja tujuh, bukan empat. Dengan empat, lima
     * prioritas memaksa dua di antaranya berbagi warna yang sama persis —
     * dua seri tak terbedakan di grafik distribusi. Tujuh anak tangga dengan
     * posisi 0, 2, 4, 6 tetap menghasilkan merah/amber/biru/abu yang sama
     * untuk empat prioritas bawaan, jadi layar lama tidak berubah rupa,
     * sementara lima sampai tujuh prioritas tetap dapat warna masing-masing.
     */
    private static function colorAt(int $index, int $total): string
    {
        $scale = self::COLOR_SCALE;
        $last = count($scale) - 1;

        if ($total <= 1) {
            return $scale[0];
        }

        $position = ($index / ($total - 1)) * $last;

        return $scale[(int) round($position)];
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

    /**
     * Bentuk yang dikirim ke browser: nama, warna, dan lencana, terurut dari
     * yang paling mendesak.
     *
     * Dititipkan sekali di layout supaya sisi React tidak perlu menyalin
     * daftar prioritasnya sendiri. Salinan itulah yang selama ini membuat
     * prioritas buatan Admin tidak pernah muncul di layar tiket baru: server
     * sudah mengirim lima, tapi layarnya menggambar empat nama yang ditulis
     * langsung di dalam komponen.
     *
     * @return array<int,array{name:string,color:string,glyph:string}>
     */
    public static function payload(): array
    {
        $priorities = self::all();
        $total = count($priorities);

        return collect($priorities)
            ->values()
            ->map(fn (string $priority, int $index) => [
                'name' => $priority,
                'color' => self::colorAt($index, $total),
                'glyph' => self::glyphAt($index, $total),
            ])
            ->all();
    }

    /**
     * Lencana ringkas di tombol pemilih prioritas.
     *
     * Sama seperti warna, diambil dari peringkat — bukan dari nama. Tombol
     * "Kritikal" tetap bertanda seru ganda seperti "Critical" sebelumnya.
     */
    private static function glyphAt(int $index, int $total): string
    {
        $glyphs = self::GLYPH_SCALE;
        $last = count($glyphs) - 1;

        if ($total <= 1) {
            return $glyphs[0];
        }

        return $glyphs[(int) round(($index / ($total - 1)) * $last)];
    }

    /** Dibuang di antara tes; satu proses HTTP hanya perlu membacanya sekali. */
    public static function flush(): void
    {
        self::$cache = null;
    }
}
