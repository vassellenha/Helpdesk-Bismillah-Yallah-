<?php

namespace App\Http\Controllers\Eva;

use App\Http\Controllers\Controller;
use App\Models\Knowledge\Synonym;
use App\Services\Knowledge\AnswerReach;
use App\Services\Knowledge\KnowledgeSearch;
use App\Services\Knowledge\SynonymExpander;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Search Settings.
 *
 * Daftar sinonim di sini benar-benar dipakai FulltextKnowledgeSearch — baik
 * saat menyaring kandidat maupun saat memberi skor. Kalau suatu saat pencarian
 * diganti (Meilisearch, embedding), yang wajib dipertahankan adalah sifat ini:
 * apa yang diketik admin di layar ini HARUS mengubah jawaban EVA. Daftar yang
 * tersimpan rapi tapi tidak berpengaruh lebih buruk daripada tidak ada, karena
 * orang akan mengisinya lalu heran kenapa tidak ada yang berubah.
 *
 * Uji langsung disediakan di layar yang sama supaya efeknya bisa dilihat detik
 * itu juga, bukan dipercaya begitu saja.
 */
class SearchSettingsController extends Controller
{
    public function __construct(private readonly KnowledgeSearch $search) {}

    public function index(): View
    {
        return view('eva.search-settings', [
            'synonyms' => Synonym::orderByDesc('updated_at')->get()->map($this->present(...)),
            'threshold' => KnowledgeSearch::MIN_CONFIDENCE,
            'endpoints' => [
                'store' => route('eva.synonyms.store'),
                'test' => route('eva.search.test'),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $synonym = Synonym::create($this->validated($request));
        SynonymExpander::forget();

        return response()->json($this->present($synonym), 201);
    }

    public function update(Request $request, Synonym $synonym): JsonResponse
    {
        $synonym->update($this->validated($request));
        SynonymExpander::forget();

        return response()->json($this->present($synonym->fresh()));
    }

    public function destroy(Synonym $synonym): JsonResponse
    {
        $synonym->delete();
        SynonymExpander::forget();

        return response()->json(['deleted' => true]);
    }

    /**
     * Uji langsung: jalankan pertanyaan lewat pencarian yang sama dengan EVA.
     *
     * Sengaja TIDAK mencatat ke kb_answer_logs. Ini alat admin untuk menyetel
     * sinonim, bukan pertanyaan karyawan — kalau ikut tercatat, Unanswered
     * Questions akan penuh oleh percobaan admin sendiri dan angka coverage
     * jadi bohong.
     */
    public function test(Request $request): JsonResponse
    {
        $data = $request->validate(['question' => 'required|string|max:500']);

        $hits = $this->search->cari($data['question'], 5);

        $rows = collect($hits)->map(fn ($hit) => [
            ...$hit->toArray(),
            'passes_threshold' => $hit->confidence >= KnowledgeSearch::MIN_CONFIDENCE,
            'type' => class_basename($hit->sourceType),
        ]);

        $reach = AnswerReach::for($hits);

        return response()->json([
            'question' => $data['question'],
            'hits' => $rows->all(),
            'reach' => $reach,
            // Dipertahankan untuk klien lama; `reach` yang menyimpan seluruh
            // kebenarannya, termasuk pita rangkuman di bawah ambang.
            'would_answer' => $reach === AnswerReach::ANSWER,
        ]);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'terms' => ['required', 'string', 'max:500'],
            'is_active' => 'required|boolean',
            'note' => 'nullable|string|max:255',
        ]);

        // Satu kata bukan kelompok sinonim. Divalidasi setelah pemecahan,
        // bukan lewat regex di aturan — supaya "password," dan "password, ,"
        // ikut tertolak dengan pesan yang sama.
        if (count(Synonym::splitTerms($data['terms'])) < Synonym::MIN_TERMS) {
            abort(422, 'Isi minimal dua kata yang dipisah koma, misalnya "password, sandi".');
        }

        return $data;
    }

    private function present(Synonym $synonym): array
    {
        return [
            'id' => $synonym->id,
            'terms' => $synonym->terms,
            'term_list' => $synonym->termList(),
            'is_active' => $synonym->is_active,
            'note' => $synonym->note,
            'updated_at' => $synonym->updated_at?->diffForHumans(),
        ];
    }
}
