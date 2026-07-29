<?php

namespace App\Services\Knowledge;

use App\Models\Knowledge\Article;
use App\Models\Knowledge\Document;
use App\Models\Knowledge\Faq;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Pengelola tag bebas pada artikel, FAQ, dan dokumen.
 *
 * Tag SENGAJA tidak dipindahkan ke tabel taksonomi tersendiri. Sumbu resmi
 * pengelompokan sudah dipegang Service Catalog (Issue Category → Layanan →
 * Sub Category → Subject); menambahkan hierarki kedua milik EVA hanya akan
 * mengulang cacat "satu konsep, dua sumber data".
 *
 * Peran tag di sini berbeda dan pelengkap: kata kunci bebas yang ikut dinilai
 * saat pencarian, untuk hal-hal yang tidak punya tempat di katalog
 * ("forticlient", "windows 11", "mfa").
 *
 * Yang selama ini hilang bukan tabelnya, melainkan PENGENDALIANNYA: tanpa
 * layar ini, "SAP", "sap", dan " sap " jadi tiga tag berbeda dan tidak ada
 * yang tahu.
 */
final class TagRegistry
{
    /** Tabel yang punya kolom `tags`. Satu daftar, dipakai baca maupun tulis. */
    private const TAGGABLES = [
        Article::class => 'Artikel',
        Faq::class => 'FAQ',
        Document::class => 'Dokumen',
    ];

    /**
     * Semua tag yang benar-benar terpakai, beserta jumlah pemakaiannya.
     *
     * @return Collection<int,array{tag:string,total:int,by_type:array<string,int>}>
     */
    public function all(): Collection
    {
        $tally = [];

        foreach (self::TAGGABLES as $model => $label) {
            foreach ($this->rowsWithTags($model) as $row) {
                foreach (self::split($row->tags) as $tag) {
                    $tally[$tag]['tag'] = $tag;
                    $tally[$tag]['total'] = ($tally[$tag]['total'] ?? 0) + 1;
                    $tally[$tag]['by_type'][$label] = ($tally[$tag]['by_type'][$label] ?? 0) + 1;
                }
            }
        }

        return collect($tally)
            ->map(fn (array $row) => [...$row, 'by_type' => $row['by_type'] ?? []])
            ->sortByDesc('total')
            ->values();
    }

    /**
     * Tag yang hanya berbeda tipis satu sama lain — kandidat untuk digabung.
     *
     * Deteksinya sengaja konservatif: hanya beda spasi, tanda hubung, atau
     * bentuk jamak sederhana. Menyarankan penggabungan yang salah lebih
     * merugikan daripada tidak menyarankan apa pun, karena admin cenderung
     * menuruti saran tanpa memeriksa.
     *
     * @return array<int,array{key:string,tags:array<int,array{tag:string,total:int}>}>
     */
    public function nearDuplicates(): array
    {
        $groups = $this->all()
            ->groupBy(fn (array $row) => $this->normalize($row['tag']))
            ->filter(fn (Collection $rows) => $rows->count() > 1);

        return $groups
            ->map(fn (Collection $rows, string $key) => [
                'key' => $key,
                'tags' => $rows->map(fn (array $r) => ['tag' => $r['tag'], 'total' => $r['total']])->values()->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * Ganti nama sebuah tag di SELURUH tabel sekaligus.
     *
     * Kalau tujuan namanya sudah ada pada baris yang sama, hasilnya digabung —
     * bukan diduplikasi. Inilah yang membuat "gabungkan SAP ke sap" aman
     * dijalankan berkali-kali.
     *
     * @return int jumlah baris yang berubah
     */
    public function rename(string $from, string $to): int
    {
        $from = mb_strtolower(trim($from));
        $to = mb_strtolower(trim($to));

        if ($from === '' || $to === '' || $from === $to) {
            return 0;
        }

        $changed = 0;

        foreach (array_keys(self::TAGGABLES) as $model) {
            foreach ($this->rowsWithTags($model) as $row) {
                $tags = self::split($row->tags);

                if (! in_array($from, $tags, true)) {
                    continue;
                }

                $replaced = array_values(array_unique(array_map(
                    fn (string $tag) => $tag === $from ? $to : $tag,
                    $tags,
                )));

                $row->update(['tags' => implode(', ', $replaced)]);
                $changed++;
            }
        }

        return $changed;
    }

    /**
     * Tag yang dipakai oleh satu jenis materi saja — untuk mengisi dropdown
     * filter di layar yang bersangkutan.
     *
     * Menawarkan tag yang tidak dipakai jenis itu hanya menghasilkan filter
     * yang selalu kosong hasilnya.
     *
     * @return string[]
     */
    public function tagsFor(string $model): array
    {
        $tags = [];

        foreach ($this->rowsWithTags($model) as $row) {
            foreach (self::split($row->tags) as $tag) {
                $tags[$tag] = true;
            }
        }

        $names = array_keys($tags);
        sort($names);

        return $names;
    }

    /**
     * Seluruh materi yang memakai sebuah tag, dikelompokkan per jenis.
     *
     * Ini yang membuat tag di layar Category & Taxonomy bisa diklik: sebelum
     * ada ini, tag hanya bisa dilihat jumlahnya tanpa cara melihat isinya.
     *
     * @return array{articles:array,faqs:array,documents:array}
     */
    public function materials(string $tag): array
    {
        $tag = mb_strtolower(trim($tag));

        return [
            'articles' => $this->matching(Article::class, $tag, 'title'),
            'faqs' => $this->matching(Faq::class, $tag, 'question'),
            'documents' => $this->matching(Document::class, $tag, 'name'),
        ];
    }

    /**
     * Penyaringan akhir dilakukan di PHP, bukan LIKE '%tag%'.
     *
     * LIKE akan menganggap "sap" cocok dengan tag "sapa" dan "wasap" — dan
     * kesalahan seperti itu tidak terlihat sampai ada yang memeriksa satu per
     * satu. LIKE tetap dipakai untuk mempersempit baris yang ditarik.
     */
    private function matching(string $model, string $tag, string $titleColumn): array
    {
        return $model::query()
            ->where('tags', 'like', '%'.$tag.'%')
            ->orderBy('id')
            ->get(['id', $titleColumn.' as title', 'tags'])
            ->filter(fn ($row) => in_array($tag, self::split($row->tags), true))
            ->map(fn ($row) => ['id' => $row->id, 'title' => $row->title])
            ->values()
            ->all();
    }

    /** Hapus sebuah tag dari seluruh materi. @return int baris yang berubah */
    public function remove(string $tag): int
    {
        $tag = mb_strtolower(trim($tag));
        $changed = 0;

        foreach (array_keys(self::TAGGABLES) as $model) {
            foreach ($this->rowsWithTags($model) as $row) {
                $tags = self::split($row->tags);

                if (! in_array($tag, $tags, true)) {
                    continue;
                }

                $row->update(['tags' => implode(', ', array_diff($tags, [$tag]))]);
                $changed++;
            }
        }

        return $changed;
    }

    /**
     * Pemecah tunggal untuk seluruh aplikasi. Huruf dikecilkan dan spasi
     * dipangkas di sini — itulah sebabnya "SAP" dan " sap " tidak pernah jadi
     * dua tag berbeda begitu melewati fungsi ini.
     *
     * @return string[]
     */
    public static function split(?string $tags): array
    {
        $parts = array_map(
            fn (string $tag) => mb_strtolower(trim($tag)),
            explode(',', (string) $tags),
        );

        return array_values(array_unique(array_filter($parts, fn (string $t) => $t !== '')));
    }

    /** @return Collection<int,Model> */
    private function rowsWithTags(string $model): Collection
    {
        return $model::query()
            ->whereNotNull('tags')
            ->where('tags', '!=', '')
            ->orderBy('id')
            ->get(['id', 'tags']);
    }

    /** Bentuk paling telanjang sebuah tag, untuk mendeteksi kembar. */
    private function normalize(string $tag): string
    {
        return preg_replace('/[^a-z0-9]/', '', mb_strtolower($tag)) ?? $tag;
    }
}
