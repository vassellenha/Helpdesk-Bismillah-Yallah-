<?php

namespace App\Http\Controllers\Eva;

use App\Http\Controllers\Controller;
use App\Models\Knowledge\Faq;
use App\Services\Knowledge\KnowledgeStats;
use App\Services\Knowledge\TagRegistry;
use App\Support\CurrentActor;
use App\Support\Eva\CatalogOptions;
use App\Support\KnowledgeAudit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Manage FAQ.
 *
 * FAQ ditulis admin dan LANGSUNG tayang — tidak ada status draf/review
 * (aturan #2). Karena itu form-nya sengaja pendek: pertanyaan, jawaban,
 * subject katalog. Menambahkan alur persetujuan di sini akan melanggar aturan
 * yang justru membedakan FAQ dari artikel.
 */
class FaqController extends Controller
{
    public function __construct(
        private readonly KnowledgeStats $stats,
        private readonly TagRegistry $tags,
    ) {}

    public function index(): View
    {
        $usage = $this->stats->usageBySource(Faq::class);
        $ratings = $this->stats->ratingsBySource(Faq::class);

        $faqs = Faq::with(['catalogSubject:id,name', 'author:id,name'])
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (Faq $faq) => $this->present($faq, $usage, $ratings));

        return view('eva.faq', [
            'faqs' => $faqs,
            'subjects' => CatalogOptions::all(),
            'services' => CatalogOptions::services(),
            'tags' => $this->tags->tagsFor(Faq::class),
            // Tag aktif datang dari query string supaya tautan dari layar
            // Category & Taxonomy langsung membuka daftar yang sudah tersaring.
            'activeTag' => request()->query('tag'),
            // Dikirim oleh tombol "Tulis FAQ" di Unanswered Questions. Isinya
            // pertanyaan karyawan APA ADANYA — form terbuka dengan kolom
            // pertanyaan sudah terisi, jadi FAQ-nya menjawab kalimat yang
            // benar-benar diajukan, bukan versi yang diketik ulang admin dari
            // ingatan. Panjangnya dipotong sesuai batas kolom `question`.
            'prefillQuestion' => $this->prefillQuestion(),
            'stats' => [
                'total' => $faqs->count(),
                'eva_visible' => $faqs->where('is_eva_visible', true)->count(),
                'unlinked' => $faqs->whereNull('catalog_subject_id')->count(),
            ],
        ]);
    }

    /**
     * Pertanyaan bawaan dari query string, atau null.
     *
     * Dipotong di batas yang sama dengan validasi `question` (500) supaya form
     * tidak terbuka dengan isi yang PASTI ditolak saat disimpan.
     */
    private function prefillQuestion(): ?string
    {
        $question = trim((string) request()->query('question'));

        return $question === '' ? null : mb_substr($question, 0, 500);
    }

    public function store(Request $request): JsonResponse
    {
        $faq = Faq::create([
            ...$this->validated($request),
            'author_id' => CurrentActor::admin()->id,
        ]);

        KnowledgeAudit::record('create', 'faq', $faq->id, $faq->question,
            "menambah FAQ \"{$faq->question}\".", ['tampil_di_eva' => $faq->is_eva_visible]);

        return response()->json($this->presentFresh($faq), 201);
    }

    public function update(Request $request, Faq $faq): JsonResponse
    {
        $sebelum = $faq->question;
        $faq->update($this->validated($request));

        KnowledgeAudit::record('update', 'faq', $faq->id, $faq->question,
            "mengubah FAQ \"{$sebelum}\".");

        return response()->json($this->presentFresh($faq));
    }

    public function toggleVisibility(Faq $faq): JsonResponse
    {
        $faq->update(['is_eva_visible' => ! $faq->is_eva_visible]);

        KnowledgeAudit::record($faq->is_eva_visible ? 'activate' : 'deactivate', 'faq', $faq->id, $faq->question,
            ($faq->is_eva_visible ? 'menampilkan' : 'menyembunyikan')." FAQ \"{$faq->question}\" dari EVA.");

        return response()->json([
            'id' => $faq->id,
            'is_eva_visible' => $faq->is_eva_visible,
        ]);
    }

    public function destroy(Faq $faq): JsonResponse
    {
        // Dicatat sebelum baris FAQ-nya lenyap — sesudahnya tidak ada lagi
        // yang bisa menyebutkan pertanyaan apa yang dihapus.
        KnowledgeAudit::record('delete', 'faq', $faq->id, $faq->question,
            "menghapus FAQ \"{$faq->question}\".", ['jawaban' => $faq->answer]);

        $faq->delete();

        return response()->json(['deleted' => true]);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'question' => 'required|string|max:500',
            'answer' => 'required|string|max:5000',
            'catalog_subject_id' => ['nullable', 'integer', Rule::exists('service_catalog_subjects', 'id')],
            'is_eva_visible' => 'required|boolean',
            'tags' => 'nullable|string|max:255',
        ]);
    }

    private function presentFresh(Faq $faq): array
    {
        return $this->present(
            $faq->fresh(['catalogSubject:id,name', 'author:id,name']),
            $this->stats->usageBySource(Faq::class),
            $this->stats->ratingsBySource(Faq::class),
        );
    }

    private function present(Faq $faq, $usage, $ratings): array
    {
        $rating = $ratings->get($faq->id);

        return [
            'id' => $faq->id,
            'question' => $faq->question,
            'answer' => $faq->answer,
            'is_eva_visible' => $faq->is_eva_visible,
            'tags' => $faq->tags,
            'catalog_subject_id' => $faq->catalog_subject_id,
            'subject_name' => $faq->catalogSubject?->name,
            'author_name' => $faq->author?->name,
            'updated_at' => $faq->updated_at?->diffForHumans(),
            'eva_uses' => $usage->get($faq->id, 0),
            'rating_avg' => $rating['avg'] ?? null,
            'rating_count' => $rating['count'] ?? 0,
            'helpful_percent' => $rating['helpful_percent'] ?? null,
        ];
    }
}
