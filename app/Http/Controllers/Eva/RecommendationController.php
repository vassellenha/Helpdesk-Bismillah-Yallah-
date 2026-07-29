<?php

namespace App\Http\Controllers\Eva;

use App\Http\Controllers\Controller;
use App\Services\Knowledge\CoverageCalculator;
use App\Services\Knowledge\KnowledgeStats;
use App\Services\Knowledge\SubjectMatch;
use App\Services\Knowledge\SubjectSearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Ticket Recommendation — ke mana tiket akan diarahkan saat EVA menyerah.
 *
 * EVA berhenti di DRAF (aturan #4). Layar ini tidak menulis satu baris pun ke
 * tabel tiket; ia hanya memperlihatkan subject katalog mana yang akan
 * disarankan untuk tiap pertanyaan yang gagal dijawab.
 *
 * Saran TIDAK PERNAH DISIMPAN. Semuanya dihitung ulang tiap kali layar dibuka,
 * dengan alasan yang sama seperti Unanswered Questions: begitu admin
 * memperbaiki sinonim atau katalog bertambah, seluruh riwayat ikut membaik
 * tanpa migrasi apa pun. Menyimpannya di kb_answer_logs.catalog_subject_id juga
 * akan membuat kolom itu berarti dua hal sekaligus — "subject artikel yang
 * menjawab" untuk log terjawab, dan "subject tebakan" untuk log gagal.
 */
class RecommendationController extends Controller
{
    /** Pertanyaan gagal yang diperiksa ulang. Sejajar dengan Unanswered. */
    private const CANDIDATE_LIMIT = 40;

    /** Calon yang ditampilkan per pertanyaan di daftar. */
    private const ALTERNATIVES = 3;

    public function __construct(
        private readonly KnowledgeStats $stats,
        private readonly SubjectSearch $matcher,
        private readonly CoverageCalculator $coverage,
    ) {}

    public function index(): View
    {
        $covered = $this->coverage->coveredSubjectIds()->flip();

        $rows = $this->stats->topUnansweredQuestions(self::CANDIDATE_LIMIT)
            ->map(fn (array $row) => [
                ...$row,
                'candidates' => $this->candidates($row['question'], $covered),
            ])
            ->values();

        return view('eva.recommendation', [
            'rows' => $rows->all(),
            'gaps' => $this->materialGaps($rows)->all(),
            'thresholds' => [
                'auto_fill' => SubjectSearch::MIN_CONFIDENCE,
                'suggest' => SubjectSearch::SUGGEST_FLOOR,
            ],
            'stats' => [
                'questions' => $rows->count(),
                'auto' => $rows->filter(fn (array $r) => $this->topConfidence($r) >= SubjectSearch::MIN_CONFIDENCE)->count(),
                'weak' => $rows->filter(function (array $r) {
                    $top = $this->topConfidence($r);

                    return $top > 0 && $top < SubjectSearch::MIN_CONFIDENCE;
                })->count(),
                'none' => $rows->filter(fn (array $r) => $r['candidates'] === [])->count(),
            ],
            'endpoints' => ['test' => route('eva.recommendation.test')],
            'links' => ['articles' => route('eva.articles'), 'faq' => route('eva.faq')],
        ]);
    }

    /** Bangku uji: mengetik pertanyaan bebas dan melihat tujuannya. */
    public function test(Request $request): JsonResponse
    {
        $data = $request->validate(['question' => 'required|string|max:500']);

        $covered = $this->coverage->coveredSubjectIds()->flip();

        return response()->json([
            'question' => $data['question'],
            'candidates' => $this->candidates($data['question'], $covered, 5),
        ]);
    }

    /**
     * @param  Collection<int,int>  $covered  id subject → posisi (hasil flip)
     */
    private function candidates(string $question, Collection $covered, int $limit = self::ALTERNATIVES): array
    {
        return array_map(
            fn (SubjectMatch $match) => [
                ...$match->toArray(),
                // Subject tujuan yang belum punya materi adalah alasan
                // pertanyaan ini gagal dijawab — sekaligus daftar tugas menulis
                // yang paling terarah yang bisa diberikan layar mana pun.
                'has_material' => $covered->has($match->subjectId),
                'is_auto_fill' => $match->confidence >= SubjectSearch::MIN_CONFIDENCE,
            ],
            $this->matcher->cocokkan($question, $limit),
        );
    }

    /**
     * Subject yang berulang kali jadi tujuan tetapi belum punya materi,
     * diurutkan dari yang paling sering.
     *
     * @param  Collection<int,array<string,mixed>>  $rows
     * @return Collection<int,array<string,mixed>>
     */
    private function materialGaps(Collection $rows): Collection
    {
        $tally = [];

        foreach ($rows as $row) {
            $top = $row['candidates'][0] ?? null;

            if ($top === null || $top['has_material']) {
                continue;
            }

            $key = $top['subject_id'];
            $tally[$key] ??= [
                'subject_id' => $key,
                'subject' => $top['subject'],
                'path' => $top['path'],
                'questions' => [],
            ];
            $tally[$key]['questions'][] = $row['question'];
        }

        return collect($tally)
            ->map(fn (array $row) => [...$row, 'total' => count($row['questions'])])
            ->sortByDesc('total')
            ->values();
    }

    /** @param array<string,mixed> $row */
    private function topConfidence(array $row): int
    {
        return $row['candidates'][0]['confidence'] ?? 0;
    }
}
