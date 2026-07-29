<?php

namespace Tests\Feature\Knowledge;

use App\Models\Knowledge\Article;
use App\Models\Knowledge\Document;
use App\Services\Knowledge\CoverageCalculator;
use App\Services\Knowledge\DocumentIndexer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\ActsAsEvaAdmin;
use Tests\TestCase;

/**
 * Satu artikel boleh melayani BANYAK subject.
 *
 * Cacat yang ditutup tes ini: "SOP Unlock Akun SAP" hanya bisa ditautkan ke
 * satu subject, padahal ia menjawab "Aktivasi/Unlock akun" di Access Request
 * DAN "User Locked" di Incident. Subject kedua terhitung kosong, sehingga
 * Coverage, Apps & Systems, dan Ticket Recommendation melaporkan celah materi
 * yang sebenarnya sudah tertutup.
 *
 * Kolom kb_articles.catalog_subject_id tetap ada sebagai subject UTAMA; tabel
 * kb_article_subject menampung tautan tambahan. Yang dijaga di sini adalah
 * gabungan keduanya — dan bahwa gerbang answerable() tetap berlaku untuk
 * tautan tambahan, persis seperti untuk subject utama.
 */
final class ArticleSubjectLinkTest extends TestCase
{
    use ActsAsEvaAdmin;
    use RefreshDatabase;

    private CoverageCalculator $coverage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCatalog();
        $this->coverage = app(CoverageCalculator::class);

        $this->actingAsEvaAdmin();
    }

    /**
     * Tiga subject di satu layanan: 1 jadi subject utama artikel, 2 jadi tautan
     * tambahan, 3 sengaja dibiarkan kosong sebagai pembanding.
     */
    private function seedCatalog(): void
    {
        DB::table('issue_categories')->insert([
            ['id' => 1, 'name' => 'Access Request'],
            ['id' => 2, 'name' => 'Incident'],
        ]);

        DB::table('service_catalog_services')->insert(['id' => 1, 'name' => 'SAP']);

        DB::table('service_catalog_subcategories')->insert([
            ['id' => 1, 'service_id' => 1, 'name' => 'AKUN'],
        ]);

        $subject = fn (int $id, int $issueCategory, string $name) => [
            'id' => $id, 'issue_category_id' => $issueCategory, 'service_id' => 1,
            'subcategory_id' => 1, 'name' => $name,
            'requires_approval' => false, 'support_level' => 1, 'is_active' => true,
        ];

        DB::table('service_catalog_subjects')->insert([
            $subject(1, 1, 'Aktivasi/Unlock Akun'),
            $subject(2, 2, 'User Locked'),
            $subject(3, 2, 'Tanpa Materi'),
        ]);
    }

    private function makeArticle(?int $primarySubjectId, string $status = Article::STATUS_PUBLISHED): Article
    {
        return Article::create([
            'title' => 'SOP Unlock Akun SAP',
            'summary' => 'Langkah membuka akun SAP yang terkunci.',
            'body' => 'Hubungi helpdesk lalu verifikasi identitas.',
            'catalog_subject_id' => $primarySubjectId,
            'status' => $status,
            'is_eva_visible' => true,
        ]);
    }

    public function test_tautan_tambahan_menutup_subject_kedua(): void
    {
        $article = $this->makeArticle(1);
        $article->subjects()->sync([2]);

        $covered = $this->coverage->coveredSubjectIds();

        $this->assertEqualsCanonicalizing(
            [1, 2],
            $covered->all(),
            'subject utama DAN tautan tambahan sama-sama terhitung tertutup',
        );
        $this->assertNotContains(3, $covered->all(), 'subject tanpa materi tetap terbuka');
    }

    public function test_persen_coverage_ikut_naik(): void
    {
        $article = $this->makeArticle(1);
        $article->subjects()->sync([2]);

        $summary = $this->coverage->summary();

        $this->assertSame(3, $summary['total_subjects']);
        $this->assertSame(2, $summary['covered_subjects']);
        $this->assertSame(1, $summary['uncovered_subjects']);
        $this->assertSame(67, $summary['percent']);
    }

    /**
     * Subject utama yang juga tercatat di pivot tidak boleh membuat satu artikel
     * dihitung dua kali di pohon taksonomi.
     */
    public function test_subject_utama_yang_terduplikasi_tidak_dihitung_dua_kali(): void
    {
        $article = $this->makeArticle(1);
        $article->subjects()->sync([1, 2]);

        $counts = $this->articleCountsPerSubject();

        $this->assertSame(1, $counts['Aktivasi/Unlock Akun']);
        $this->assertSame(1, $counts['User Locked']);
    }

    /**
     * Gerbang answerable() berlaku untuk tautan tambahan juga. Kalau tidak,
     * artikel draf akan menutup subject di layar Coverage tetapi tidak pernah
     * dipakai EVA menjawab — persis jenis ketidaksesuaian yang paling sulit
     * dilacak dari layar.
     */
    public function test_artikel_draf_tidak_menutup_subject_lewat_tautan_tambahan(): void
    {
        $article = $this->makeArticle(1, Article::STATUS_DRAFT);
        $article->subjects()->sync([2]);

        $this->assertSame([], $this->coverage->coveredSubjectIds()->all());
    }

    /** Artikel tanpa subject utama tetap bisa menutup subject lewat pivot. */
    public function test_artikel_tanpa_subject_utama_masih_bisa_menutup_lewat_pivot(): void
    {
        $article = $this->makeArticle(null);
        $article->subjects()->sync([2]);

        $this->assertSame([2], $this->coverage->coveredSubjectIds()->all());
    }

    public function test_endpoint_update_menyimpan_tautan_tambahan(): void
    {
        $article = $this->makeArticle(1);

        $this->putJson('/eva/api/articles/'.$article->id, [
            'title' => $article->title,
            'summary' => $article->summary,
            'body' => $article->body,
            'status' => Article::STATUS_PUBLISHED,
            'is_eva_visible' => true,
            'catalog_subject_id' => 1,
            'subject_ids' => [2],
        ])->assertOk()
            ->assertJsonPath('catalog_subject_id', 1)
            ->assertJsonPath('subject_ids', [2]);

        $this->assertSame([2], $article->fresh()->subjects()->pluck('service_catalog_subjects.id')->all());
    }

    /** Subject utama tak perlu diulang di subject_ids — controller membuangnya. */
    public function test_endpoint_update_membuang_subject_utama_dari_tautan_tambahan(): void
    {
        $article = $this->makeArticle(1);

        $this->putJson('/eva/api/articles/'.$article->id, [
            'title' => $article->title,
            'status' => Article::STATUS_PUBLISHED,
            'is_eva_visible' => true,
            'catalog_subject_id' => 1,
            'subject_ids' => [1, 2],
        ])->assertOk()->assertJsonPath('subject_ids', [2]);
    }

    public function test_endpoint_update_menolak_subject_tak_dikenal(): void
    {
        $article = $this->makeArticle(1);

        $this->putJson('/eva/api/articles/'.$article->id, [
            'title' => $article->title,
            'status' => Article::STATUS_PUBLISHED,
            'is_eva_visible' => true,
            'subject_ids' => [999],
        ])->assertStatus(422);
    }

    /**
     * Indeks ulang memindahkan subject UTAMA mengikuti dokumennya. Kalau subject
     * tujuan kebetulan sudah ada sebagai tautan tambahan, tautan itu harus
     * lepas — kalau tidak, satu subject tercatat di dua tempat dan daftar
     * artikel menampilkan nama yang sama dua kali.
     */
    public function test_indeks_ulang_melepas_tautan_tambahan_yang_jadi_subject_utama(): void
    {
        $document = Document::create([
            'name' => 'SOP Unlock Akun SAP',
            'catalog_subject_id' => 1,
            'extracted_text' => 'Hubungi helpdesk untuk membuka akun SAP yang terkunci.',
            'status' => Document::STATUS_PROCESSING,
        ]);

        $article = app(DocumentIndexer::class)->index($document)->article;
        $article->subjects()->sync([2]);

        // Admin memindahkan dokumen ke subject yang tadinya tautan tambahan.
        $document->update(['catalog_subject_id' => 2]);
        app(DocumentIndexer::class)->index($document->fresh());

        $article = $article->fresh();

        $this->assertSame(2, $article->catalog_subject_id);
        $this->assertSame([], $article->subjects()->pluck('service_catalog_subjects.id')->all());
        $this->assertSame([2], $article->allSubjectIds()->all(), 'subject tidak boleh tercatat dua kali');
    }

    /** @return array<string,int> nama subject => jumlah artikel di pohon taksonomi */
    private function articleCountsPerSubject(): array
    {
        $counts = [];

        foreach ($this->coverage->taxonomyTree() as $issueCategory) {
            foreach ($issueCategory['services'] as $service) {
                foreach ($service['subcategories'] as $subcategory) {
                    $counts[$issueCategory['name']] = $subcategory['articles'];
                }
            }
        }

        // Pohon dikelompokkan per issue category; subject 1 & 2 ada di kategori
        // berbeda, jadi angkanya bisa dipetakan balik ke nama subject.
        return [
            'Aktivasi/Unlock Akun' => $counts['Access Request'] ?? 0,
            'User Locked' => $counts['Incident'] ?? 0,
        ];
    }
}
