<?php

namespace App\Services\Knowledge;

use App\Models\Knowledge\Article;
use App\Models\Knowledge\CoverageSnapshot;
use App\Models\Knowledge\Faq;
use App\Models\ServiceCatalogSubject;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Menghitung kesiapan EVA: berapa persen subject katalog yang sudah punya
 * artikel atau FAQ aktif.
 *
 * Angkanya DIHITUNG, tidak pernah disimpan sebagai kolom `coverage` seperti di
 * mockup. Penyebutnya juga dihitung (ServiceCatalogSubject::count()), tidak
 * pernah ditulis 139/140 di mana pun — katalog milik tim dan bisa berubah.
 *
 * kb_coverage_snapshots hanya dipakai untuk GARIS TREN masa lalu; titik
 * terakhir grafik selalu angka nyata hasil hitungan hari ini.
 */
final class CoverageCalculator
{
    /** Banyak titik riwayat yang ditampilkan di grafik tren. */
    private const TREND_POINTS = 5;

    public function summary(): array
    {
        $subjects = $this->subjects();
        $coveredByArticle = $this->subjectIdsWithArticles();
        $coveredByFaq = $this->subjectIdsWithFaqs();
        $covered = $coveredByArticle->merge($coveredByFaq)->unique();

        $total = $subjects->count();

        return [
            'total_subjects' => $total,
            'covered_subjects' => $covered->count(),
            'percent' => $this->percent($covered->count(), $total),
            'article_only_subjects' => $coveredByArticle->count(),
            'faq_only_subjects' => $coveredByFaq->diff($coveredByArticle)->count(),
            'uncovered_subjects' => $total - $covered->count(),
        ];
    }

    /**
     * Rincian per sub category untuk bar bertumpuk: porsi yang ditutup artikel
     * dan tambahan yang hanya berasal dari FAQ. Pemisahan itu yang menjelaskan
     * kontribusi tiap sumber, bukan sekadar satu angka gabungan.
     */
    public function bySubcategory(): array
    {
        $coveredByArticle = $this->subjectIdsWithArticles();
        $coveredByFaq = $this->subjectIdsWithFaqs();

        $groups = $this->subjects()
            ->groupBy(fn ($subject) => $subject->service_name.' › '.$subject->subcategory_name)
            ->map(function (Collection $rows, string $label) use ($coveredByArticle, $coveredByFaq) {
                $ids = $rows->pluck('id');
                $withArticle = $ids->intersect($coveredByArticle)->count();
                $faqOnly = $ids->intersect($coveredByFaq)->diff($coveredByArticle)->count();
                $total = $ids->count();

                return [
                    'label' => $label,
                    'total' => $total,
                    'covered' => $withArticle + $faqOnly,
                    'article_percent' => $this->percent($withArticle, $total),
                    'faq_percent' => $this->percent($faqOnly, $total),
                    'percent' => $this->percent($withArticle + $faqOnly, $total),
                ];
            })
            ->sortByDesc(fn (array $row) => [$row['percent'], $row['total']])
            ->values();

        // SELURUH sub category dikirim, tidak dipotong.
        //
        // Sebelumnya hanya 10 teratas yang dikirim dan 29 sisanya tidak
        // terjangkau dari mana pun — sub category yang kesiapannya paling buruk
        // justru yang paling mudah lenyap, karena urutannya menurun. Pemenggalan
        // sekarang urusan layar (pagination), dan tiap halamannya bisa dibuka.
        return [
            'rows' => $groups->all(),
            'shown' => $groups->count(),
            'total' => $groups->count(),
        ];
    }

    /**
     * Kesiapan per LAYANAN — bahan layar Apps & Systems.
     *
     * Sengaja memakai helper yang sama dengan bySubcategory(). Kalau "subject
     * yang tertutup" didefinisikan dua kali, cepat atau lambat Coverage
     * Dashboard dan Apps & Systems akan menampilkan angka berbeda untuk hal
     * yang sama — dan tidak ada cara memberi tahu mana yang benar.
     *
     * @return array<int,array{service:string,total:int,covered:int,percent:int,subcategories:int}>
     */
    public function byService(): array
    {
        $coveredByArticle = $this->subjectIdsWithArticles();
        $coveredByFaq = $this->subjectIdsWithFaqs();

        return $this->subjects()
            ->groupBy('service_name')
            ->map(function (Collection $rows, string $service) use ($coveredByArticle, $coveredByFaq) {
                $ids = $rows->pluck('id');
                $covered = $ids->intersect($coveredByArticle->merge($coveredByFaq))->count();
                $total = $ids->count();

                return [
                    'service' => $service,
                    'total' => $total,
                    'covered' => $covered,
                    'percent' => $this->percent($covered, $total),
                    'subcategories' => $rows->pluck('subcategory_name')->unique()->count(),
                ];
            })
            ->sortByDesc(fn (array $row) => [$row['total'], $row['percent']])
            ->values()
            ->all();
    }

    /**
     * Riwayat nyata + titik hari ini, satu titik per BULAN.
     *
     * Riwayat HANYA berisi baris yang pernah direkam `eva:snapshot-coverage`;
     * tidak ada satu pun angka yang dikarang. Kalau tabelnya kosong, grafik
     * jujur menampilkan satu titik saja — itu keadaan sebenarnya, dan lebih
     * berguna daripada garis naik yang meyakinkan tapi tidak pernah terjadi.
     *
     * Perekaman dan tampilan sengaja dipisah. Snapshot direkam HARIAN karena
     * hari yang terlewat tidak bisa dibuat ulang, sementara grafiknya bulanan
     * karena kesiapan bergerak seiring pekerjaan menulis materi — lima hari
     * terakhir hampir selalu rata dan tidak memberi tahu apa pun. Kalau nanti
     * resolusi mingguan dibutuhkan, datanya sudah ada; tinggal mengubah
     * pengelompokan di sini.
     *
     * Bulan BERJALAN tidak diambil dari snapshot: bulan ini sudah diwakili
     * titik terakhir, yang selalu dihitung ulang saat ini.
     */
    public function trend(): array
    {
        $history = CoverageSnapshot::where('captured_on', '<', today()->startOfMonth())
            ->orderBy('captured_on')
            ->get()
            // Satu bulan = rekaman TERAKHIR bulan itu. groupBy mempertahankan
            // urutan masuk, jadi last() adalah yang paling baru.
            ->groupBy(fn (CoverageSnapshot $snapshot) => $snapshot->captured_on->format('Y-m'))
            ->map(fn (Collection $rows) => $rows->last())
            ->values()
            ->take(-self::TREND_POINTS)
            ->map(fn (CoverageSnapshot $snapshot) => [
                'label' => $snapshot->captured_on->translatedFormat('M'),
                'percent' => $snapshot->coverage_percent,
            ])
            ->values()
            ->all();

        $history[] = [
            'label' => today()->translatedFormat('M'),
            'percent' => $this->summary()['percent'],
        ];

        return $history;
    }

    /**
     * Pohon taksonomi katalog: Issue Category → Layanan → Sub Category,
     * lengkap dengan jumlah materi dan kesiapan di tiap simpul.
     *
     * Seluruh strukturnya milik Service Catalog dan hanya DIBACA (aturan #5).
     * Yang ditambahkan EVA cuma angkanya.
     *
     * @return array<int,array<string,mixed>>
     */
    public function taxonomyTree(): array
    {
        $articleCounts = Article::answerableCountsBySubject();
        $faqCounts = $this->countsBySubject(Faq::query()->answerable());

        return $this->subjects()
            ->groupBy('issue_category_name')
            ->map(fn (Collection $rows, string $issueCategory) => [
                'name' => $issueCategory,
                ...$this->tally($rows, $articleCounts, $faqCounts),
                'services' => $rows
                    ->groupBy('service_name')
                    ->map(fn (Collection $serviceRows, string $service) => [
                        'name' => $service,
                        ...$this->tally($serviceRows, $articleCounts, $faqCounts),
                        'subcategories' => $serviceRows
                            ->groupBy('subcategory_name')
                            ->map(fn (Collection $subRows, string $subcategory) => [
                                'name' => $subcategory,
                                ...$this->tally($subRows, $articleCounts, $faqCounts),
                            ])
                            ->sortByDesc('subjects')
                            ->values()
                            ->all(),
                    ])
                    ->sortByDesc('subjects')
                    ->values()
                    ->all(),
            ])
            ->sortByDesc('subjects')
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int,int>  $articleCounts
     * @param  Collection<int,int>  $faqCounts
     * @return array{subjects:int,covered:int,percent:int,articles:int,faqs:int}
     */
    private function tally(Collection $rows, Collection $articleCounts, Collection $faqCounts): array
    {
        $ids = $rows->pluck('id');
        $articles = $ids->sum(fn (int $id) => $articleCounts->get($id, 0));
        $faqs = $ids->sum(fn (int $id) => $faqCounts->get($id, 0));
        $covered = $ids->filter(fn (int $id) => $articleCounts->has($id) || $faqCounts->has($id))->count();

        return [
            'subjects' => $ids->count(),
            'covered' => $covered,
            'percent' => $this->percent($covered, $ids->count()),
            'articles' => $articles,
            'faqs' => $faqs,
        ];
    }

    /**
     * Jumlah materi per subject untuk materi yang hanya punya SATU subject.
     * Artikel tidak lagi lewat sini — lihat Article::answerableCountsBySubject().
     *
     * @return Collection<int,int> catalog_subject_id => jumlah materi
     */
    private function countsBySubject($query): Collection
    {
        return $query
            ->whereNotNull('catalog_subject_id')
            ->groupBy('catalog_subject_id')
            ->orderBy('catalog_subject_id')
            ->get(['catalog_subject_id', DB::raw('count(*) as material_count')])
            ->keyBy('catalog_subject_id')
            ->map(fn ($row) => (int) $row->material_count);
    }

    private function subjects(): Collection
    {
        return ServiceCatalogSubject::query()
            ->where('service_catalog_subjects.is_active', true)
            ->join('service_catalog_services as svc', 'svc.id', '=', 'service_catalog_subjects.service_id')
            ->join('service_catalog_subcategories as sub', 'sub.id', '=', 'service_catalog_subjects.subcategory_id')
            ->join('issue_categories as ic', 'ic.id', '=', 'service_catalog_subjects.issue_category_id')
            ->orderBy('service_catalog_subjects.id')
            ->get([
                'service_catalog_subjects.id',
                'svc.name as service_name',
                'sub.name as subcategory_name',
                'ic.name as issue_category_name',
            ]);
    }

    /**
     * Id subject yang sudah punya materi aktif — definisi "tertutup" yang sama
     * dengan seluruh layar Coverage.
     *
     * Dibuka ke publik supaya Ticket Recommendation bisa menandai subject
     * tujuan yang belum punya bahan tanpa menulis ulang definisinya. Menyalin
     * kriteria ini ke controller lain adalah cara paling cepat membuat dua
     * layar melaporkan angka berbeda untuk hal yang sama.
     *
     * @return Collection<int,int>
     */
    public function coveredSubjectIds(): Collection
    {
        return $this->subjectIdsWithArticles()
            ->merge($this->subjectIdsWithFaqs())
            ->unique()
            ->values();
    }

    /**
     * Satu artikel bisa melayani beberapa subject (kb_article_subject), jadi
     * daftar ini diturunkan dari Article::answerableCountsBySubject() —
     * gabungan subject utama dan tautan tambahan. Membaca kolom
     * catalog_subject_id langsung di sini akan membuat layar Coverage kembali
     * melaporkan celah materi yang sebenarnya sudah tertutup.
     */
    private function subjectIdsWithArticles(): Collection
    {
        return Article::answerableCountsBySubject()->keys();
    }

    private function subjectIdsWithFaqs(): Collection
    {
        return Faq::query()->answerable()
            ->whereNotNull('catalog_subject_id')
            ->orderBy('catalog_subject_id')
            ->distinct()
            ->pluck('catalog_subject_id');
    }

    private function percent(int $part, int $whole): int
    {
        return $whole > 0 ? (int) round(100 * $part / $whole) : 0;
    }
}
