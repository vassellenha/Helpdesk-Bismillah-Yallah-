<?php

namespace App\Http\Controllers\Eva;

use App\Http\Controllers\Controller;
use App\Models\Knowledge\AnswerRating;
use App\Models\Knowledge\Article;
use App\Models\Knowledge\Faq;
use App\Services\Knowledge\KnowledgeStats;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Rating & Feedback.
 *
 * Semua angka di sini diagregasi dari kb_answer_ratings saat request. Inilah
 * yang membuat bintang dari karyawan benar-benar sampai ke statistik — di
 * mockup, "helpful" adalah kolom beku di artikel, sehingga penilaian pengguna
 * tidak pernah mengubah apa pun.
 *
 * Setiap baris materi membawa TIGA hal sekaligus: angkanya, tanggapan tertulis
 * atas materi itu, dan alamat layar tempat materinya bisa diperbaiki. Layar ini
 * hanya berguna kalau ketiganya sampai bersamaan — daftar nilai jelek tanpa
 * alasan dan tanpa jalan keluar cuma bikin admin menebak lalu mencari manual.
 */
class RatingController extends Controller
{
    public function __construct(private readonly KnowledgeStats $stats) {}

    public function index(): View
    {
        $commentsBySource = $this->stats->commentsBySource();

        return view('eva.ratings', [
            'summary' => $this->stats->ratingSummary(),
            'distribution' => $this->stats->ratingDistribution(),
            'sources' => $this->rankedSources($commentsBySource)->all(),
            'comments' => $this->presentComments($this->stats->recentComments()),
            'helpfulThreshold' => AnswerRating::HELPFUL_THRESHOLD,
        ]);
    }

    /** Performa per materi, artikel dan FAQ digabung lalu diurutkan. */
    private function rankedSources(Collection $commentsBySource): Collection
    {
        $rows = collect()
            ->merge($this->sourceRows(
                type: Article::class,
                label: 'Artikel',
                manageUrl: route('eva.articles'),
                titles: fn () => Article::pluck('title', 'id'),
                commentsBySource: $commentsBySource,
            ))
            ->merge($this->sourceRows(
                type: Faq::class,
                label: 'FAQ',
                manageUrl: route('eva.faq'),
                titles: fn () => Faq::pluck('question', 'id'),
                commentsBySource: $commentsBySource,
            ));

        return $rows->sortBy([
            ['avg', 'asc'],
            ['rating_count', 'desc'],
        ])->values();
    }

    /**
     * Diurutkan dari rata-rata TERENDAH lebih dulu (lihat rankedSources).
     * Daftar "terbaik di atas" enak dilihat tapi tidak menghasilkan pekerjaan;
     * yang perlu ditindaklanjuti adalah materi yang dinilai buruk.
     */
    private function sourceRows(
        string $type,
        string $label,
        string $manageUrl,
        callable $titles,
        Collection $commentsBySource,
    ): Collection {
        $ratings = $this->stats->ratingsBySource($type);

        if ($ratings->isEmpty()) {
            return collect();
        }

        $usage = $this->stats->usageBySource($type);
        $titleMap = $titles();

        return $ratings
            ->filter(fn (array $rating, int $id) => $titleMap->has($id))
            ->map(fn (array $rating, int $id) => [
                // Kunci pilihan di layar. Memakai id saja tidak cukup: Artikel #3
                // dan FAQ #3 sama-sama ada, dan memilih satu akan menyorot dua.
                'key' => $label.'-'.$id,
                'id' => $id,
                'type' => $label,
                'title' => $titleMap->get($id),
                'avg' => $rating['avg'],
                'rating_count' => $rating['count'],
                'helpful_percent' => $rating['helpful_percent'],
                'eva_uses' => $usage->get($id, 0),
                'manage_url' => $manageUrl,
                'comments' => $this->presentComments($commentsBySource->get($type.'|'.$id, collect())),
            ])
            ->values();
    }

    /** @param  Collection<int,AnswerRating>  $ratings */
    private function presentComments(Collection $ratings): array
    {
        return $ratings
            ->map(fn (AnswerRating $rating) => [
                'id' => $rating->id,
                'stars' => $rating->stars,
                'reason' => $rating->reason,
                'comment' => $rating->comment,
                'rater_name' => $rating->rater?->name ?? 'Anonim',
                'question' => $rating->answerLog?->question,
                'created_at' => $rating->created_at?->diffForHumans(),
            ])
            ->values()
            ->all();
    }
}
