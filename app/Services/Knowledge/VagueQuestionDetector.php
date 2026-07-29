<?php

namespace App\Services\Knowledge;

use App\Models\Knowledge\Article;
use App\Models\Knowledge\Faq;
use App\Models\ServiceCatalogService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Mendeteksi keluhan generik yang tidak menyebut layanan apa pun
 * ("tidak bisa login", "error terus").
 *
 * Untuk pertanyaan seperti itu EVA harus BERTANYA BALIK, bukan menjawab
 * artikel yang kebetulan cocok — menebak layanan yang salah lebih merugikan
 * daripada satu pertanyaan tambahan.
 *
 * Daftar layanannya dibaca dari service_catalog_services, bukan dihardcode:
 * katalog milik role Admin dan bisa bertambah kapan saja (aturan #5 — EVA
 * hanya membaca).
 */
final class VagueQuestionDetector
{
    private const CACHE_KEY = 'eva.catalog-service-names';

    private const ANSWERABLE_CACHE_KEY = 'eva.services-with-material';

    private const CACHE_TTL_SECONDS = 300;

    /** Keluhan yang tidak menunjuk sistem tertentu. */
    private const GENERIC_COMPLAINTS = [
        'tidak bisa login', 'tidak bisa masuk', 'gabisa login', 'gagal login',
        'tidak bisa akses', 'error', 'gagal', 'bermasalah', 'tidak jalan',
        'tidak berfungsi', 'lemot', 'lambat',
    ];

    public function __construct(private readonly QuestionTokenizer $tokenizer) {}

    public function isVague(string $question): bool
    {
        $normalized = mb_strtolower(trim($question));

        if ($normalized === '') {
            return false;
        }

        return $this->hasGenericComplaint($normalized) && ! $this->mentionsService($normalized);
    }

    /**
     * Layanan yang ditawarkan saat EVA bertanya balik.
     *
     * Hanya layanan yang BENAR-BENAR punya materi aktif, diurutkan dari yang
     * materinya paling banyak. Menawarkan layanan yang tidak punya satu pun
     * artikel/FAQ hanya memindahkan kebuntuan satu langkah ke depan: pengguna
     * menjawab, lalu tetap tidak mendapat jawaban.
     *
     * @return string[]
     */
    public function clarifyOptions(int $limit = 4): array
    {
        $ranked = Cache::remember(
            self::ANSWERABLE_CACHE_KEY,
            self::CACHE_TTL_SECONDS,
            fn () => $this->servicesWithMaterial(),
        );

        return array_slice($ranked, 0, $limit);
    }

    /**
     * Layanan yang punya materi aktif, terbanyak lebih dulu.
     *
     * Memakai scope answerable() yang sama dengan pencarian — kalau "aktif"
     * didefinisikan dua kali, cepat atau lambat keduanya akan berbeda dan EVA
     * menawarkan layanan yang ternyata tidak bisa ia jawab.
     *
     * @return string[]
     */
    private function servicesWithMaterial(): array
    {
        $tally = [];

        foreach ([Article::query()->answerable(), Faq::query()->answerable()] as $query) {
            $rows = $query
                ->whereNotNull('catalog_subject_id')
                ->join('service_catalog_subjects as subj', 'subj.id', '=', 'catalog_subject_id')
                ->join('service_catalog_services as svc', 'svc.id', '=', 'subj.service_id')
                ->groupBy('svc.name')
                ->orderBy('svc.name')
                ->get(['svc.name as service', DB::raw('count(*) as material_count')]);

            foreach ($rows as $row) {
                $tally[$row->service] = ($tally[$row->service] ?? 0) + (int) $row->material_count;
            }
        }

        arsort($tally);

        return array_keys($tally);
    }

    private function hasGenericComplaint(string $normalized): bool
    {
        foreach (self::GENERIC_COMPLAINTS as $complaint) {
            if (str_contains($normalized, $complaint)) {
                return true;
            }
        }

        return false;
    }

    private function mentionsService(string $normalized): bool
    {
        $tokens = $this->tokenizer->tokens($normalized);

        foreach ($this->serviceNames() as $name) {
            if (in_array(mb_strtolower($name), $tokens, true)) {
                return true;
            }
        }

        return false;
    }

    /** @return string[] */
    private function serviceNames(): array
    {
        return Cache::remember(
            self::CACHE_KEY,
            self::CACHE_TTL_SECONDS,
            fn () => ServiceCatalogService::orderBy('name')->pluck('name')->all(),
        );
    }
}
