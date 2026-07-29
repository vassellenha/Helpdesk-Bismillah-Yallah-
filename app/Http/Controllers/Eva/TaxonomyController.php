<?php

namespace App\Http\Controllers\Eva;

use App\Http\Controllers\Controller;
use App\Services\Knowledge\CoverageCalculator;
use App\Services\Knowledge\TagRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Category & Taxonomy.
 *
 * Dua hal yang sengaja dipisahkan tegas di layar ini:
 *
 *  1. STRUKTUR (Issue Category → Layanan → Sub Category → Subject) milik
 *     Service Catalog. Di sini hanya DIBACA dan dihitung. Tidak ada tombol
 *     tambah/ubah/hapus — menambah kategori dari dua tempat adalah cara
 *     tercepat membuat katalog dan Knowledge Base diam-diam berbeda isi.
 *
 *  2. TAG milik EVA. Ini kata kunci bebas untuk hal yang tidak punya tempat di
 *     katalog. Di sinilah pengelolaan sesungguhnya terjadi: mengganti nama,
 *     menggabungkan yang kembar, membuang yang tidak terpakai.
 */
class TaxonomyController extends Controller
{
    public function __construct(
        private readonly CoverageCalculator $coverage,
        private readonly TagRegistry $tags,
    ) {}

    public function index(): View
    {
        $tree = $this->coverage->taxonomyTree();
        $summary = $this->coverage->summary();
        $tags = $this->tags->all();

        return view('eva.taxonomy', [
            'tree' => $tree,
            'tags' => $tags->all(),
            'duplicates' => $this->tags->nearDuplicates(),
            'stats' => [
                'issue_categories' => count($tree),
                // DISTINCT, bukan jumlah per cabang. Satu layanan bisa muncul di
                // lebih dari satu Issue Category — SAP ada di Incident, Service
                // Request, dan Access Request sekaligus. Menjumlahkan tiap cabang
                // membuat layar ini melaporkan 15 layanan sementara Apps & Systems
                // melaporkan 14, untuk katalog yang sama persis.
                'services' => $this->distinctCount($tree, fn (array $service) => $service['name']),
                'subcategories' => $this->distinctCount(
                    $tree,
                    fn (array $service, array $sub) => $service['name'].' › '.$sub['name'],
                    withSubcategory: true,
                ),
                'subjects' => $summary['total_subjects'],
                'tags' => $tags->count(),
            ],
            'catalogUrl' => route('admin.service-catalog'),
        ]);
    }

    /**
     * Menghitung simpul unik di seluruh pohon.
     *
     * Kuncinya menyertakan nama layanan, bukan hanya nama sub category —
     * "AKUN APLIKASI" ada di bawah SAP maupun Umum, dan keduanya memang sub
     * category yang berbeda. Ini definisi yang sama dengan
     * CoverageCalculator::bySubcategory(), supaya angkanya tidak pernah
     * berselisih dengan Coverage Dashboard.
     */
    private function distinctCount(array $tree, callable $key, bool $withSubcategory = false): int
    {
        $seen = [];

        foreach ($tree as $issueCategory) {
            foreach ($issueCategory['services'] as $service) {
                if (! $withSubcategory) {
                    $seen[$key($service)] = true;

                    continue;
                }

                foreach ($service['subcategories'] as $subcategory) {
                    $seen[$key($service, $subcategory)] = true;
                }
            }
        }

        return count($seen);
    }

    /** Materi yang memakai sebuah tag — isi panel saat tag diklik. */
    public function tagMaterials(Request $request): JsonResponse
    {
        $data = $request->validate(['tag' => 'required|string|max:100']);

        return response()->json([
            'tag' => $data['tag'],
            'materials' => $this->tags->materials($data['tag']),
            'links' => [
                'articles' => route('eva.articles', ['tag' => $data['tag']]),
                'faqs' => route('eva.faq', ['tag' => $data['tag']]),
                'documents' => route('eva.documents', ['tag' => $data['tag']]),
            ],
        ]);
    }

    public function renameTag(Request $request): JsonResponse
    {
        $data = $request->validate([
            'from' => 'required|string|max:100',
            'to' => 'required|string|max:100',
        ]);

        $changed = $this->tags->rename($data['from'], $data['to']);

        return response()->json([
            'changed' => $changed,
            'tags' => $this->tags->all()->all(),
            'duplicates' => $this->tags->nearDuplicates(),
        ]);
    }

    public function deleteTag(Request $request): JsonResponse
    {
        $data = $request->validate(['tag' => 'required|string|max:100']);

        $changed = $this->tags->remove($data['tag']);

        return response()->json([
            'changed' => $changed,
            'tags' => $this->tags->all()->all(),
            'duplicates' => $this->tags->nearDuplicates(),
        ]);
    }
}
