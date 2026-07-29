<?php

namespace App\Http\Controllers\Eva;

use App\Http\Controllers\Controller;
use App\Models\Knowledge\Article;
use App\Models\Knowledge\Faq;
use App\Services\Knowledge\KnowledgeStats;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Analytics — laporan pemakaian EVA.
 *
 * Semua angka berasal dari kb_answer_logs, yaitu apa yang benar-benar
 * ditanyakan karyawan. Tidak ada satu pun yang berasal dari daftar contoh.
 */
class AnalyticsController extends Controller
{
    private const TOP_LIMIT = 10;

    public function __construct(private readonly KnowledgeStats $stats) {}

    public function index(): View
    {
        return view('eva.analytics', [
            'summary' => $this->stats->answerSummary(),
            'trend' => $this->stats->weeklyTrend(),
            'topQuestions' => $this->stats->topQuestions(self::TOP_LIMIT)->all(),
            'topMaterials' => $this->topMaterials()->all(),
            'links' => [
                'unanswered' => route('eva.unanswered'),
                'ratings' => route('eva.ratings'),
            ],
        ]);
    }

    /** Materi yang paling sering dikutip EVA, artikel dan FAQ digabung. */
    private function topMaterials(): Collection
    {
        return collect()
            ->merge($this->materialRows(Article::class, 'Artikel', fn () => Article::pluck('title', 'id')))
            ->merge($this->materialRows(Faq::class, 'FAQ', fn () => Faq::pluck('question', 'id')))
            ->sortByDesc('eva_uses')
            ->take(self::TOP_LIMIT)
            ->values();
    }

    private function materialRows(string $type, string $label, callable $titles): Collection
    {
        $usage = $this->stats->usageBySource($type);

        if ($usage->isEmpty()) {
            return collect();
        }

        $ratings = $this->stats->ratingsBySource($type);
        $titleMap = $titles();

        return $usage
            ->filter(fn (int $uses, int $id) => $titleMap->has($id))
            ->map(fn (int $uses, int $id) => [
                'id' => $id,
                'type' => $label,
                'title' => $titleMap->get($id),
                'eva_uses' => $uses,
                'rating_avg' => $ratings->get($id)['avg'] ?? null,
                'rating_count' => $ratings->get($id)['count'] ?? 0,
            ])
            ->values();
    }
}
