<?php

namespace App\Http\Controllers\Eva;

use App\Http\Controllers\Controller;
use App\Models\Knowledge\Article;
use App\Models\Knowledge\Document;
use App\Models\Knowledge\Faq;
use App\Services\Knowledge\CoverageCalculator;
use App\Services\Knowledge\KnowledgeStats;
use Illuminate\View\View;

/**
 * Coverage Dashboard — satu pertanyaan: seberapa siap EVA menjawab katalog
 * layanan yang ada?
 *
 * Semua angka di sini DIHITUNG saat request. Tidak ada satu pun yang dibaca
 * dari kolom hasil salinan.
 */
class CoverageController extends Controller
{
    public function __construct(
        private readonly CoverageCalculator $coverage,
        private readonly KnowledgeStats $stats,
    ) {}

    public function index(): View
    {
        return view('eva.coverage', [
            'summary' => $this->coverage->summary(),
            'bySubcategory' => $this->coverage->bySubcategory(),
            'trend' => $this->coverage->trend(),
            'todo' => $this->stats->topUnansweredQuestions()->all(),
            'todoVolume' => $this->stats->unansweredVolume(),
            'blockers' => $this->blockers(),
            'links' => [
                'documents' => route('eva.documents'),
                'articles' => route('eva.articles'),
                'faq' => route('eva.faq'),
            ],
        ]);
    }

    /**
     * Hal yang menghambat kesiapan, hanya yang benar-benar ada.
     *
     * Daftar kosong berarti tidak ada penghambat — layar menampilkan itu apa
     * adanya, bukan peringatan hiasan.
     */
    private function blockers(): array
    {
        $blockers = [];

        $notIndexed = Document::where('status', '!=', Document::STATUS_INDEXED)->count();
        if ($notIndexed > 0) {
            $blockers[] = [
                'text' => "{$notIndexed} dokumen belum terindeks sehingga isinya belum dapat digunakan EVA.",
                'action' => 'Buka Documents',
                'url' => route('eva.documents'),
            ];
        }

        $hiddenArticles = Article::where('is_eva_visible', false)->count();
        if ($hiddenArticles > 0) {
            $blockers[] = [
                'text' => "{$hiddenArticles} artikel nonaktif di EVA sehingga tidak pernah dikutip pada jawaban.",
                'action' => 'Buka Article Library',
                'url' => route('eva.articles'),
            ];
        }

        $draftArticles = Article::where('status', Article::STATUS_DRAFT)->count();
        if ($draftArticles > 0) {
            $blockers[] = [
                'text' => "{$draftArticles} artikel masih berstatus draf sehingga belum dihitung dalam kesiapan.",
                'action' => 'Buka Article Library',
                'url' => route('eva.articles'),
            ];
        }

        $unlinked = Article::whereNull('catalog_subject_id')->count()
            + Faq::whereNull('catalog_subject_id')->count();
        if ($unlinked > 0) {
            $blockers[] = [
                'text' => "{$unlinked} materi belum ditautkan ke subject katalog sehingga tidak dihitung dalam kesiapan.",
                'action' => 'Buka Article Library',
                'url' => route('eva.articles'),
            ];
        }

        return $blockers;
    }
}
