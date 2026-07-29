<?php

namespace App\Http\Controllers\Eva;

use App\Http\Controllers\Controller;
use App\Models\Knowledge\Article;
use App\Models\Knowledge\Document;
use App\Models\Knowledge\Faq;
use App\Services\Knowledge\AnswerSourceSettings;
use App\Services\Knowledge\CoverageCalculator;
use App\Services\Knowledge\KnowledgeStats;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Training Overview — apa yang EVA pelajari, dan sumber mana yang ia pakai.
 *
 * Dua hal di satu layar:
 *
 *  1. RINGKASAN KESIAPAN — berapa materi aktif, cakupan katalog, celah yang
 *     belum tertutup. Angkanya DIHITUNG dari sumber yang sama dengan Coverage
 *     Dashboard, tidak disalin, supaya tidak pernah berselisih.
 *
 *  2. SAKELAR SUMBER JAWABAN — artikel dan FAQ bisa dimatikan sebagai sumber.
 *     Sakelar ini nyata: FulltextKnowledgeSearch membacanya tiap pencarian.
 *     Mematikan FAQ benar-benar membuat EVA berhenti menjawab dari FAQ.
 */
class TrainingController extends Controller
{
    public function __construct(
        private readonly CoverageCalculator $coverage,
        private readonly KnowledgeStats $stats,
        private readonly AnswerSourceSettings $sources,
    ) {}

    public function index(): View
    {
        $summary = $this->coverage->summary();
        $sources = $this->sources->all();

        return view('eva.training', [
            'sources' => [
                'articles' => [
                    'enabled' => $sources[AnswerSourceSettings::SOURCE_ARTICLES],
                    'count' => Article::query()->answerable()->count(),
                ],
                'faqs' => [
                    'enabled' => $sources[AnswerSourceSettings::SOURCE_FAQS],
                    'count' => Faq::query()->answerable()->count(),
                ],
            ],
            'readiness' => [
                'coverage_percent' => $summary['percent'],
                'covered_subjects' => $summary['covered_subjects'],
                'total_subjects' => $summary['total_subjects'],
                'uncovered_subjects' => $summary['uncovered_subjects'],
                'documents_indexed' => Document::query()->where('status', Document::STATUS_INDEXED)->count(),
                'open_gaps' => $this->stats->topUnansweredQuestions(100)->count(),
            ],
            'endpoints' => ['toggle' => route('eva.training.toggle')],
            'links' => [
                'articles' => route('eva.articles'),
                'faq' => route('eva.faq'),
                'coverage' => route('eva.coverage'),
                'unanswered' => route('eva.unanswered'),
            ],
        ]);
    }

    public function toggle(Request $request): JsonResponse
    {
        $data = $request->validate([
            'source' => ['required', Rule::in([
                AnswerSourceSettings::SOURCE_ARTICLES,
                AnswerSourceSettings::SOURCE_FAQS,
            ])],
            'enabled' => 'required|boolean',
        ]);

        $this->sources->set($data['source'], $data['enabled']);

        return response()->json([
            'source' => $data['source'],
            'enabled' => $data['enabled'],
            'sources' => $this->sources->all(),
        ]);
    }
}
