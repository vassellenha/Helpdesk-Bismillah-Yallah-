<?php

namespace App\Services\Knowledge;

use App\Models\Knowledge\AnswerLog;
use App\Models\Knowledge\AnswerRating;
use Carbon\Carbon;
use App\Support\Eva\LogRetention;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Agregasi statistik artikel/FAQ dari kb_answer_logs & kb_answer_ratings.
 *
 * Sengaja hidup di sini, bukan sebagai kolom di kb_articles: menyalin
 * "helpful" jadi kolom persis cacat mockup yang membuat bintang dari karyawan
 * tidak pernah sampai ke statistik.
 *
 * Semua metode mengembalikan peta ber-key source_id sehingga pemanggilnya bisa
 * memasangkannya ke koleksi apa pun tanpa query per baris (N+1).
 */
final class KnowledgeStats
{
    /**
     * Berapa tanggapan tertulis yang dibaca sekali jalan untuk SELURUH materi.
     *
     * Dibatasi supaya satu layar tidak pernah menarik seluruh riwayat komentar
     * saat pemakaian EVA sudah ramai. Tanggapan berteks memang jarang — sebagian
     * besar penilai hanya memberi bintang — jadi batas ini longgar dalam praktik.
     */
    private const COMMENT_SCAN_LIMIT = 500;

    /** Tanggapan yang ditampilkan per materi. Lebih dari ini tidak terbaca. */
    private const COMMENTS_PER_SOURCE = 10;

    /**
     * Berapa kali tiap sumber dipakai EVA menjawab.
     *
     * @return Collection<int,int> source_id => jumlah
     */
    public function usageBySource(string $sourceType): Collection
    {
        return AnswerLog::query()
            ->where('source_type', $sourceType)
            ->whereNotNull('source_id')
            ->where('outcome', AnswerLog::OUTCOME_ANSWERED)
            ->groupBy('source_id')
            ->orderBy('source_id')
            ->get(['source_id', DB::raw('count(*) as use_count')])
            ->keyBy('source_id')
            ->map(fn ($row) => (int) $row->use_count);
    }

    /**
     * Rata-rata bintang, jumlah penilai, dan persentase "membantu" per sumber.
     *
     * @return Collection<int,array{avg:float,count:int,helpful_percent:int}>
     */
    public function ratingsBySource(string $sourceType): Collection
    {
        $helpfulSum = 'sum(case when kb_answer_ratings.stars >= '.AnswerRating::HELPFUL_THRESHOLD.' then 1 else 0 end) as helpful_count';

        return AnswerRating::query()
            ->join('kb_answer_logs as logs', 'logs.id', '=', 'kb_answer_ratings.answer_log_id')
            ->where('logs.source_type', $sourceType)
            ->whereNotNull('logs.source_id')
            ->groupBy('logs.source_id')
            ->orderBy('logs.source_id')
            ->get([
                'logs.source_id as source_id',
                DB::raw('avg(kb_answer_ratings.stars) as avg_stars'),
                DB::raw('count(*) as rating_count'),
                DB::raw($helpfulSum),
            ])
            ->keyBy('source_id')
            ->map(fn ($row) => [
                'avg' => round((float) $row->avg_stars, 1),
                'count' => (int) $row->rating_count,
                'helpful_percent' => (int) round(100 * $row->helpful_count / max(1, (int) $row->rating_count)),
            ]);
    }

    /**
     * Pertanyaan yang paling sering tidak terjawab.
     *
     * Ini pengganti daftar beku `unanswered` di mockup: isinya selalu apa yang
     * benar-benar ditanyakan karyawan.
     *
     * `$exclude` disaring di SQL, BUKAN sesudah hasilnya diambil: kalau tidak,
     * pertanyaan yang sudah disingkirkan tetap memakan jatah `$limit` dan
     * daftar kerja mengecil sendiri tanpa alasan yang terlihat.
     *
     * @param  iterable<string>  $exclude  teks pertanyaan yang tidak boleh ikut
     */
    public function topUnansweredQuestions(int $limit = 5, iterable $exclude = []): Collection
    {
        $excluded = collect($exclude)->all();

        return AnswerLog::query()->unanswered()
            ->when($excluded !== [], fn ($query) => $query->whereNotIn('question', $excluded))
            ->groupBy('question')
            ->orderByDesc(DB::raw('count(*)'))
            ->orderBy('question')
            ->limit($limit)
            ->get([
                'question',
                DB::raw('count(*) as ask_count'),
                DB::raw('max(created_at) as last_asked_at'),
            ])
            ->map(function ($row) {
                // Hasil agregat MAX() kembali sebagai string mentah, bukan
                // Carbon — tanpa parse eksplisit, layar menampilkan
                // "2026-07-23 03:21:39" alih-alih "2 jam yang lalu".
                $terakhir = Carbon::parse($row->last_asked_at);

                return [
                    'question' => $row->question,
                    'count' => (int) $row->ask_count,
                    'last_asked_at' => $terakhir->diffForHumans(),
                    /*
                     | Hitung mundur dipatok ke penanyaan TERAKHIR, bukan yang
                     | pertama. Penyapu membuang baris satu per satu, jadi baris
                     | tertua hilang lebih dulu dan angka "ditanyakan Nx" ikut
                     | menyusut — tapi pertanyaannya baru benar-benar lenyap
                     | dari layar ini saat baris TERMUDA-nya ikut terhapus.
                     | Memakai yang tertua akan menjanjikan penghapusan yang
                     | tidak terjadi.
                    */
                    'expires_in_days' => LogRetention::daysLeft($terakhir),
                ];
            });
    }

    /** Total pertanyaan tak terjawab — volume kerja yang menunggu. */
    public function unansweredVolume(): int
    {
        return AnswerLog::query()->unanswered()->count();
    }

    /**
     * Ringkasan penilaian seluruh jawaban EVA.
     *
     * @return array{total:int,avg:float,helpful_percent:int}
     */
    public function ratingSummary(): array
    {
        $row = AnswerRating::query()
            ->get([
                DB::raw('count(*) as rating_count'),
                DB::raw('avg(stars) as avg_stars'),
                DB::raw('sum(case when stars >= '.AnswerRating::HELPFUL_THRESHOLD.' then 1 else 0 end) as helpful_count'),
            ])
            ->first();

        $total = (int) ($row->rating_count ?? 0);

        return [
            'total' => $total,
            'avg' => $total > 0 ? round((float) $row->avg_stars, 1) : 0.0,
            'helpful_percent' => $total > 0 ? (int) round(100 * $row->helpful_count / $total) : 0,
        ];
    }

    /**
     * Sebaran bintang 1–5. Selalu mengembalikan kelima baris, termasuk yang
     * nol — grafik sebaran yang kehilangan batangnya diam-diam menyesatkan.
     *
     * @return array<int,array{stars:int,count:int,percent:int}>
     */
    public function ratingDistribution(): array
    {
        $counts = AnswerRating::query()
            ->groupBy('stars')
            ->orderBy('stars')
            ->get(['stars', DB::raw('count(*) as rating_count')])
            ->keyBy('stars')
            ->map(fn ($row) => (int) $row->rating_count);

        $total = max(1, $counts->sum());

        return collect(range(AnswerRating::MAX_STARS, AnswerRating::MIN_STARS))
            ->map(fn (int $stars) => [
                'stars' => $stars,
                'count' => $counts->get($stars, 0),
                'percent' => (int) round(100 * $counts->get($stars, 0) / $total),
            ])
            ->all();
    }

    /**
     * Ringkasan seluruh pertanyaan yang pernah masuk ke EVA.
     *
     * `deflection_percent` = porsi pertanyaan yang selesai di EVA tanpa jadi
     * tiket. Ini angka yang paling sering diminta manajemen, dan paling mudah
     * dibuat bohong — karena itu penyebutnya SELURUH pertanyaan, termasuk yang
     * dijawab dengan bertanya balik lalu ditinggalkan.
     *
     * @return array{total:int,answered:int,clarify:int,unanswered:int,deflection_percent:int,avg_confidence:int}
     */
    public function answerSummary(): array
    {
        $byOutcome = AnswerLog::query()
            ->groupBy('outcome')
            ->orderBy('outcome')
            ->get(['outcome', DB::raw('count(*) as log_count')])
            ->keyBy('outcome')
            ->map(fn ($row) => (int) $row->log_count);

        $total = $byOutcome->sum();
        $answered = $byOutcome->get(AnswerLog::OUTCOME_ANSWERED, 0);

        $avgConfidence = AnswerLog::query()
            ->where('outcome', AnswerLog::OUTCOME_ANSWERED)
            ->avg('confidence');

        return [
            'total' => $total,
            'answered' => $answered,
            'clarify' => $byOutcome->get(AnswerLog::OUTCOME_CLARIFY, 0),
            'unanswered' => $byOutcome->get(AnswerLog::OUTCOME_NO_ANSWER, 0)
                + $byOutcome->get(AnswerLog::OUTCOME_TICKET_DRAFT, 0),
            'deflection_percent' => $total > 0 ? (int) round(100 * $answered / $total) : 0,
            'avg_confidence' => (int) round((float) $avgConfidence),
        ];
    }

    /**
     * Tren mingguan: berapa yang masuk, berapa yang terjawab.
     *
     * Pengelompokan dilakukan di PHP, bukan lewat YEARWEEK() di SQL. Fungsi
     * tanggal adalah bagian paling tidak portabel dari SQL, dan seluruh lapisan
     * ini dirancang supaya pindah ke PostgreSQL nanti tidak menyeret perubahan
     * di mana-mana. Volumenya kecil — yang ditarik hanya dua kolom.
     *
     * @return array<int,array{label:string,total:int,answered:int,percent:int}>
     */
    public function weeklyTrend(int $weeks = 8): array
    {
        $since = Carbon::now()->subWeeks($weeks - 1)->startOfWeek();

        $buckets = [];
        for ($i = $weeks - 1; $i >= 0; $i--) {
            $start = Carbon::now()->subWeeks($i)->startOfWeek();
            $buckets[$start->toDateString()] = [
                'label' => $start->translatedFormat('d M'),
                'total' => 0,
                'answered' => 0,
            ];
        }

        AnswerLog::query()
            ->where('created_at', '>=', $since)
            ->orderBy('id')
            ->get(['created_at', 'outcome'])
            ->each(function ($log) use (&$buckets) {
                $key = $log->created_at->startOfWeek()->toDateString();

                if (! isset($buckets[$key])) {
                    return;
                }

                $buckets[$key]['total']++;

                if ($log->outcome === AnswerLog::OUTCOME_ANSWERED) {
                    $buckets[$key]['answered']++;
                }
            });

        return collect($buckets)
            ->map(fn (array $bucket) => [
                ...$bucket,
                'percent' => $bucket['total'] > 0
                    ? (int) round(100 * $bucket['answered'] / $bucket['total'])
                    : 0,
            ])
            ->values()
            ->all();
    }

    /**
     * Pertanyaan yang paling sering masuk, terjawab maupun tidak.
     *
     * Ditampilkan bercampur dengan sengaja: yang menarik justru pertanyaan
     * bervolume tinggi yang HANYA KADANG terjawab — itu tanda materinya ada
     * tapi rapuh, bukan tidak ada.
     */
    public function topQuestions(int $limit = 10): Collection
    {
        $answeredSum = "sum(case when outcome = '".AnswerLog::OUTCOME_ANSWERED."' then 1 else 0 end) as answered_count";

        return AnswerLog::query()
            ->groupBy('question')
            ->orderByDesc(DB::raw('count(*)'))
            ->orderBy('question')
            ->limit($limit)
            ->get(['question', DB::raw('count(*) as ask_count'), DB::raw($answeredSum)])
            ->map(fn ($row) => [
                'question' => $row->question,
                'count' => (int) $row->ask_count,
                'answered_count' => (int) $row->answered_count,
                'answered_percent' => (int) round(100 * $row->answered_count / max(1, (int) $row->ask_count)),
            ]);
    }

    /** Tanggapan tertulis terbaru dari karyawan, untuk dibaca apa adanya. */
    public function recentComments(int $limit = 20): Collection
    {
        return $this->writtenComments($limit);
    }

    /**
     * Tanggapan tertulis dikelompokkan per materi yang dinilai.
     *
     * Angka memberi tahu materi MANA yang buruk; hanya kalimat karyawan yang
     * memberi tahu KENAPA. Selama keduanya hidup sebagai dua daftar terpisah,
     * admin harus mencocokkannya sendiri dengan mata — dan pada 100 materi itu
     * tidak akan dilakukan.
     *
     * Kuncinya memakai `source_type|source_id`, bukan `source_id` saja: Artikel
     * #3 dan FAQ #3 sama-sama ada, dan mencampurnya berarti menempelkan keluhan
     * orang atas materi lain.
     *
     * @return Collection<string,Collection<int,AnswerRating>>
     */
    public function commentsBySource(): Collection
    {
        return $this->writtenComments(self::COMMENT_SCAN_LIMIT)
            ->filter(fn (AnswerRating $rating) => $rating->answerLog?->source_id !== null)
            ->groupBy(fn (AnswerRating $rating) => $rating->answerLog->source_type.'|'.$rating->answerLog->source_id)
            ->map(fn (Collection $group) => $group->take(self::COMMENTS_PER_SOURCE)->values());
    }

    private function writtenComments(int $limit): Collection
    {
        return AnswerRating::query()
            ->whereNotNull('comment')
            ->where('comment', '!=', '')
            ->with(['rater:id,name', 'answerLog:id,question,source_type,source_id'])
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }
}
