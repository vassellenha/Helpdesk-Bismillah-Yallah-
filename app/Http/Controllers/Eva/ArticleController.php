<?php

namespace App\Http\Controllers\Eva;

use App\Http\Controllers\Controller;
use App\Models\Knowledge\Article;
use App\Services\Knowledge\DocumentIndexer;
use App\Services\Knowledge\KnowledgeStats;
use App\Services\Knowledge\TagRegistry;
use App\Support\Eva\CatalogOptions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Article Library.
 *
 * Tidak ada aksi "buat artikel baru" — dan itu disengaja. Artikel lahir dari
 * dokumen (aturan #1); yang bisa dilakukan di sini adalah menyunting,
 * menayangkan, dan menautkannya ke subject katalog.
 */
class ArticleController extends Controller
{
    public function __construct(
        private readonly KnowledgeStats $stats,
        private readonly TagRegistry $tags,
        private readonly DocumentIndexer $indexer,
    ) {}

    public function index(): View
    {
        $usage = $this->stats->usageBySource(Article::class);
        $ratings = $this->stats->ratingsBySource(Article::class);

        $articles = Article::with(['sourceDocument:id,name', 'catalogSubject:id,name', 'author:id,name', 'editor:id,name', 'subjects:id,name'])
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (Article $article) => $this->present($article, $usage, $ratings));

        return view('eva.articles', [
            'articles' => $articles,
            'subjects' => CatalogOptions::all(),
            'services' => CatalogOptions::services(),
            'tags' => $this->tags->tagsFor(Article::class),
            // Tag aktif datang dari query string supaya tautan dari layar
            // Category & Taxonomy langsung membuka daftar yang sudah tersaring.
            'activeTag' => request()->query('tag'),
            'stats' => [
                'total' => $articles->count(),
                'published' => $articles->where('status', Article::STATUS_PUBLISHED)->count(),
                'eva_visible' => $articles->where('is_eva_visible', true)->count(),
                // "Belum tertaut" berarti tidak melayani SATU subject pun —
                // artikel yang hanya punya tautan tambahan tetap tertaut.
                'unlinked' => $articles->filter(
                    fn (array $article) => $article['catalog_subject_id'] === null && $article['subject_ids'] === [],
                )->count(),
            ],
        ]);
    }

    public function update(Request $request, Article $article): JsonResponse
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'summary' => 'nullable|string|max:2000',
            'body' => 'nullable|string',
            'status' => ['required', Rule::in(Article::STATUSES)],
            'is_eva_visible' => 'required|boolean',
            'catalog_subject_id' => ['nullable', 'integer', Rule::exists('service_catalog_subjects', 'id')],
            // Subject TAMBAHAN. Satu SOP kerap menjawab beberapa subject
            // sekaligus; tanpa ini subject sisanya terhitung kosong di Coverage.
            'subject_ids' => 'sometimes|array',
            'subject_ids.*' => ['integer', Rule::exists('service_catalog_subjects', 'id')],
            'tags' => 'nullable|string|max:255',
        ]);

        // Artikel lahir dari dokumen (aturan #1), tapi isinya boleh ditimpa
        // seluruhnya di sini. Tanpa jejak penyunting, perubahan yang memelintir
        // jawaban EVA tidak bisa ditelusuri ke siapa pun — `author_id` hanya
        // mencatat asal materinya, bukan tangan yang terakhir menyentuhnya.
        $article->update([
            ...Arr::except($data, 'subject_ids'),
            'updated_by' => $request->user()->id,
        ]);

        if (array_key_exists('subject_ids', $data)) {
            $article->subjects()->sync($this->extraSubjectIds($data['subject_ids'], $article->catalog_subject_id));
        }

        // Isi artikel adalah yang dibaca manusia; potongan dokumen adalah yang
        // dibaca EVA. Keduanya harus bergerak bersama, kalau tidak suntingan
        // di sini tidak pernah sampai ke jawaban.
        if ($article->wasChanged('body')) {
            $this->indexer->syncFromArticle($article);
        }

        return response()->json($this->present(
            $article->fresh(['sourceDocument:id,name', 'catalogSubject:id,name', 'author:id,name', 'editor:id,name', 'subjects:id,name']),
            $this->stats->usageBySource(Article::class),
            $this->stats->ratingsBySource(Article::class),
        ));
    }

    /**
     * Terbitkan / tarik jadi draf — satu klik dari daftar.
     *
     * Sengaja TIDAK lewat update(): itu penyimpan seluruh isi artikel, dan
     * memakainya di sini berarti setiap penerbitan ikut menulis ulang judul,
     * ringkasan, dan body dengan apa pun yang kebetulan ada di layar.
     *
     * Ini gerbang KEDUA, terpisah dari is_eva_visible. Artikel baru bisa
     * dipakai menjawab kalau lolos keduanya (lihat Article::scopeAnswerable),
     * jadi menerbitkan artikel yang disembunyikan tidak diam-diam
     * memunculkannya.
     */
    public function publish(Article $article): JsonResponse
    {
        $article->update([
            'status' => $article->status === Article::STATUS_PUBLISHED
                ? Article::STATUS_DRAFT
                : Article::STATUS_PUBLISHED,
        ]);

        return response()->json([
            'id' => $article->id,
            'status' => $article->status,
        ]);
    }

    /** Toggle "Show in EVA" — gerbang yang menentukan artikel ikut dijawab atau tidak. */
    /**
     * Menghapus artikel.
     *
     * `kb_answer_logs` TIDAK ikut terhapus — itu catatan kejadian, dan
     * deflection rate bulan lalu tidak boleh berubah gara-gara materi hari ini
     * dirapikan. Tautan subject tambahan ikut terhapus lewat cascade di
     * database, jadi Coverage langsung benar tanpa perlu dihitung ulang di sini.
     *
     * Catatan yang wajib disampaikan di layar: artikel yang lahir dari dokumen
     * akan LAHIR KEMBALI kalau dokumennya diindeks ulang. Menghapus artikel
     * bukan cara menghapus materinya — untuk itu dokumennya yang dihapus.
     */
    public function destroy(Article $article): JsonResponse
    {
        $article->delete();

        return response()->json(['deleted' => true]);
    }

    public function toggleVisibility(Article $article): JsonResponse
    {
        $article->update(['is_eva_visible' => ! $article->is_eva_visible]);

        return response()->json([
            'id' => $article->id,
            'is_eva_visible' => $article->is_eva_visible,
        ]);
    }

    /**
     * Subject utama tidak boleh ikut tersimpan sebagai tautan tambahan: kalau
     * dibiarkan, artikel yang sama muncul dua kali untuk satu subject dan
     * pengguna melihat dua tempat berbeda yang menyimpan fakta yang sama.
     *
     * @param  array<int,int>  $requested
     * @return array<int,int>
     */
    private function extraSubjectIds(array $requested, ?int $primaryId): array
    {
        return array_values(array_diff(array_unique($requested), array_filter([$primaryId])));
    }

    private function present(Article $article, $usage, $ratings): array
    {
        $rating = $ratings->get($article->id);

        return [
            'id' => $article->id,
            'title' => $article->title,
            'summary' => $article->summary,
            'body' => $article->body,
            'status' => $article->status,
            'is_eva_visible' => $article->is_eva_visible,
            'tags' => $article->tags,
            'catalog_subject_id' => $article->catalog_subject_id,
            'subject_name' => $article->catalogSubject?->name,
            'subject_ids' => $article->subjects->pluck('id')->all(),
            'subject_names' => $article->subjects->pluck('name')->all(),
            'source_document_id' => $article->source_document_id,
            'source_document_name' => $article->sourceDocument?->name,
            'author_name' => $article->author?->name,
            // Null selama isinya belum pernah disunting tangan manusia.
            'updated_by_name' => $article->editor?->name,
            'updated_at' => $article->updated_at?->diffForHumans(),
            // Angka di bawah ini diagregasi dari kb_answer_logs & kb_answer_ratings.
            // Jangan pernah menyimpannya balik sebagai kolom di kb_articles.
            'eva_uses' => $usage->get($article->id, 0),
            'rating_avg' => $rating['avg'] ?? null,
            'rating_count' => $rating['count'] ?? 0,
            'helpful_percent' => $rating['helpful_percent'] ?? null,
        ];
    }
}
