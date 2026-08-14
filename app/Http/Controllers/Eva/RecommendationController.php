<?php

namespace App\Http\Controllers\Eva;

use App\Http\Controllers\Controller;
use App\Models\Knowledge\DismissedQuestion;
use App\Services\Knowledge\CoverageCalculator;
use App\Services\Knowledge\KnowledgeSearch;
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
 * tabel tiket; ia memperlihatkan subject katalog mana yang akan disarankan
 * untuk pertanyaan yang gagal dijawab, dan karena itu subject mana yang paling
 * mendesak untuk ditulisi artikel.
 *
 * Saran TIDAK PERNAH DISIMPAN. Semuanya dihitung ulang tiap layar dibuka:
 * begitu admin memperbaiki sinonim atau katalog bertambah, seluruh riwayat ikut
 * membaik tanpa migrasi apa pun.
 *
 * SATU SUMBER DENGAN UNANSWERED QUESTIONS. Layar ini dulu memakai 40 kandidat
 * mentah, sehingga pertanyaan yang sudah dihapus admin maupun yang sudah
 * terjawab tetap tampil di sini — admin menghapus di satu menu lalu
 * menemukannya lagi di menu sebelah. Sekarang dua saringan yang sama dengan
 * UnansweredController berlaku:
 *
 *   1. `DismissedQuestion::hiddenQuestions()` — yang disingkirkan admin hilang.
 *   2. Pemeriksaan ulang lewat KnowledgeSearch — yang kini bisa dijawab EVA
 *      hilang, tanpa perlu ada yang menandainya selesai.
 *
 * Keduanya biaya nyata (satu pencarian per pertanyaan), dan itu harga yang sama
 * yang sudah dibayar Unanswered Questions. Dua layar yang mengaku membaca hal
 * yang sama tetapi menampilkan isi berbeda lebih mahal daripada itu.
 */
class RecommendationController extends Controller
{
    /** Pertanyaan gagal yang diperiksa ulang. Sejajar dengan Unanswered. */
    private const CANDIDATE_LIMIT = 40;

    /** Calon yang ditampilkan bangku uji. Daftar utama hanya butuh yang teratas. */
    private const BENCH_ALTERNATIVES = 5;

    public function __construct(
        private readonly KnowledgeStats $stats,
        private readonly SubjectSearch $matcher,
        private readonly CoverageCalculator $coverage,
        private readonly KnowledgeSearch $search,
    ) {}

    public function index(): View
    {
        $covered = $this->coverage->coveredSubjectIds()->flip();

        $rows = $this->stats->topUnansweredQuestions(self::CANDIDATE_LIMIT, DismissedQuestion::hiddenQuestions())
            ->filter(fn (array $row) => $this->stillUnanswered($row['question']))
            ->map(fn (array $row) => [...$row, 'candidates' => $this->candidates($row['question'], $covered, 1)])
            ->values();

        $targets = $this->targets($rows);
        $unrouted = $this->unrouted($rows);

        return view('eva.recommendation', [
            'targets' => $targets->all(),
            'unrouted' => $unrouted->all(),
            'thresholds' => [
                'auto_fill' => SubjectSearch::MIN_CONFIDENCE,
                'tie_margin' => SubjectSearch::TIE_MARGIN,
                'suggest' => SubjectSearch::SUGGEST_FLOOR,
            ],
            'stats' => [
                'questions' => $rows->count(),
                'targets' => $targets->count(),
                'without_material' => $targets->where('has_material', false)->count(),
                'unrouted' => $unrouted->count(),
            ],
            'endpoints' => ['test' => route('eva.recommendation.test')],
            'links' => [
                'faq' => route('eva.faq'),
                'unanswered' => route('eva.unanswered'),
                'searchSettings' => route('eva.search-settings'),
            ],
        ]);
    }

    /** Bangku uji: mengetik pertanyaan bebas dan melihat tujuannya. */
    public function test(Request $request): JsonResponse
    {
        $data = $request->validate(['question' => 'required|string|max:500']);

        $covered = $this->coverage->coveredSubjectIds()->flip();

        return response()->json([
            'question' => $data['question'],
            'candidates' => $this->candidates($data['question'], $covered, self::BENCH_ALTERNATIVES),
        ]);
    }

    /**
     * Pertanyaan yang DITANYAKAN ULANG SEKARANG pun tetap tidak terjawab.
     *
     * Aturannya sengaja identik dengan UnansweredController::recheck() —
     * kandidat terbaik di bawah ambang berarti celahnya belum tertutup. Kalau
     * kedua layar memakai ambang berbeda, keduanya akan saling menyalahkan.
     */
    private function stillUnanswered(string $question): bool
    {
        $best = $this->search->cari($question, 1)[0] ?? null;

        return $best === null || $best->confidence < KnowledgeSearch::MIN_CONFIDENCE;
    }

    /**
     * Daftar kerja layar ini: SUBJECT, bukan pertanyaan.
     *
     * Dibalik dari sebelumnya karena keluaran layar ini adalah satu keputusan —
     * "artikel mana yang saya tulis berikutnya" — dan keputusan itu diambil per
     * subject. Daftar per pertanyaan memaksa admin menyimpulkan sendiri bahwa
     * tujuh pertanyaan berbeda sebetulnya menuju satu artikel yang sama, dan
     * pada 40 pertanyaan kesimpulan itu tidak pernah benar-benar diambil.
     *
     * Satu subject = calon TERATAS dari tiap pertanyaan. Calon kedua dan ketiga
     * tidak dikelompokkan di sini; tempatnya di bangku uji, karena yang menarik
     * dari calon alternatif adalah membandingkannya untuk SATU pertanyaan.
     *
     * @param  Collection<int,array<string,mixed>>  $rows
     * @return Collection<int,array<string,mixed>>
     */
    private function targets(Collection $rows): Collection
    {
        $tally = [];

        foreach ($rows as $row) {
            $top = $row['candidates'][0] ?? null;

            if ($top === null) {
                continue;
            }

            $key = $top['subject_id'];
            $tally[$key] ??= [
                'subject_id' => $key,
                'subject' => $top['subject'],
                'path' => $top['path'],
                'has_material' => $top['has_material'],
                'best_confidence' => 0,
                'volume' => 0,
                'questions' => [],
            ];

            $tally[$key]['questions'][] = [
                'question' => $row['question'],
                'count' => $row['count'],
                'confidence' => $top['confidence'],
                'is_auto_fill' => $top['is_auto_fill'],
            ];
            $tally[$key]['volume'] += $row['count'];
            $tally[$key]['best_confidence'] = max($tally[$key]['best_confidence'], $top['confidence']);
        }

        return collect($tally)
            ->map(fn (array $row) => [...$row, 'total' => count($row['questions'])])
            // Belum bermateri lebih dulu: subject yang artikelnya sudah ada
            // tidak menghasilkan pekerjaan menulis, hanya perlu diperbaiki kata
            // kuncinya. Sesudah itu barulah yang paling sering ditanyakan.
            ->sortBy([
                ['has_material', 'asc'],
                ['volume', 'desc'],
            ])
            ->values();
    }

    /**
     * Pertanyaan yang tidak punya calon subject sama sekali.
     *
     * Dipisah dari daftar subject karena pekerjaannya berbeda: ini bukan soal
     * menulis artikel, melainkan kosakatanya belum dikenali — yang diperbaiki
     * lewat Search Settings.
     *
     * @param  Collection<int,array<string,mixed>>  $rows
     * @return Collection<int,array<string,mixed>>
     */
    private function unrouted(Collection $rows): Collection
    {
        return $rows
            ->filter(fn (array $row) => $row['candidates'] === [])
            ->map(fn (array $row) => ['question' => $row['question'], 'count' => $row['count']])
            ->values();
    }

    /**
     * @param  Collection<int,int>  $covered  id subject → posisi (hasil flip)
     * @return array<int,array<string,mixed>>
     */
    private function candidates(string $question, Collection $covered, int $limit): array
    {
        $matches = $this->matcher->cocokkan($question, $limit);
        $tertinggi = $matches[0]->confidence ?? 0;

        /*
         | Seri = calon kedua menempel di ambang TIE_MARGIN. Saat itu terjadi,
         | terbaik() menolak memilih dan kolom subject dibiarkan kosong.
         |
         | Sebelumnya layar ini menandai SEMUA calon di atas MIN_CONFIDENCE
         | dengan lencana hijau "akan terisi otomatis" — termasuk saat dua di
         | antaranya seri 56 lawan 56. Admin membaca dua janji hijau, lalu
         | mendapati kolomnya kosong, dan tidak ada apa pun di layar yang
         | menjelaskan kenapa. Perbedaan angkanya ada di depan mata, tapi
         | aturannya tidak.
        */
        $seri = count($matches) > 1
            && $matches[0]->confidence >= SubjectSearch::MIN_CONFIDENCE
            && $tertinggi - $matches[1]->confidence <= SubjectSearch::TIE_MARGIN;

        return array_map(
            fn (SubjectMatch $match, int $posisi) => [
                ...$match->toArray(),
                'has_material' => $covered->has($match->subjectId),

                // Hanya calon teratas yang bisa terisi otomatis, dan hanya
                // kalau ia menang telak. Calon kedua ke bawah tidak pernah
                // mengisi kolom apa pun, jadi menandainya hijau menyesatkan.
                'is_auto_fill' => $posisi === 0
                    && ! $seri
                    && $match->confidence >= SubjectSearch::MIN_CONFIDENCE,

                // Seri dengan calon teratas — inilah yang perlu dipilih manusia.
                'is_tied' => $seri
                    && $match->confidence >= SubjectSearch::MIN_CONFIDENCE
                    && $tertinggi - $match->confidence <= SubjectSearch::TIE_MARGIN,
            ],
            $matches,
            array_keys($matches),
        );
    }
}
