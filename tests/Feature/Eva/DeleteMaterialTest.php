<?php

namespace Tests\Feature\Eva;

use App\Models\Knowledge\AnswerLog;
use App\Models\Knowledge\Article;
use App\Models\Knowledge\Chunk;
use App\Models\Knowledge\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\ActsAsEvaAdmin;
use Tests\TestCase;

/**
 * Menghapus artikel dan dokumen.
 *
 * FAQ sudah bisa dihapus sejak awal; artikel dan dokumen belum — satu-satunya
 * jalan menyingkirkan materi yang salah adalah menariknya jadi draf, yang
 * membuat daftar terus tumbuh berisi barang yang tak seorang pun berniat pakai.
 *
 * Yang dikunci di sini adalah BATAS penghapusannya, karena di sinilah kejutan
 * paling mahal bersembunyi:
 *
 *  1. **`kb_answer_logs` tidak pernah ikut terhapus** — riwayat adalah catatan
 *     kejadian; angka Analytics bulan lalu tidak boleh berubah gara-gara materi
 *     hari ini dirapikan.
 *  2. **Menghapus dokumen TIDAK menghapus artikelnya** (`nullOnDelete`). Isi
 *     artikel sudah jadi milik admin begitu disunting; menghapus berkas sumber
 *     tidak boleh diam-diam membuang pekerjaan itu.
 *  3. **Menghapus artikel TIDAK menghapus dokumennya** — dan artikel itu akan
 *     LAHIR KEMBALI kalau dokumennya diindeks ulang.
 */
final class DeleteMaterialTest extends TestCase
{
    use ActsAsEvaAdmin;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        Storage::fake('local');
        User::factory()->create(['name' => 'Marcell Laforteza', 'email' => 'marcell.laforteza@adhi.co.id', 'nip' => '19870114001']);

        $this->actingAsEvaAdmin();
    }

    private function document(): Document
    {
        $this->postJson('/eva/api/documents', [
            'name' => 'SOP Reset Password SAP',
            'extension' => 'TXT',
            'extracted_text' => 'Langkah mengatur ulang kata sandi SAP yang kedaluwarsa.',
        ])->assertStatus(202);

        return Document::sole();
    }

    private function log(string $sourceType, int $sourceId): void
    {
        AnswerLog::create([
            'question' => 'cara reset password sap',
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'outcome' => AnswerLog::OUTCOME_ANSWERED,
            'confidence' => 90,
        ]);
    }

    // ---- artikel -----------------------------------------------------------

    public function test_artikel_bisa_dihapus(): void
    {
        $article = $this->document()->article;

        $this->deleteJson("/eva/api/articles/{$article->id}")
            ->assertOk()
            ->assertJsonPath('deleted', true);

        $this->assertSame(0, Article::count());
    }

    public function test_menghapus_artikel_tidak_menghapus_riwayat_jawabannya(): void
    {
        $article = $this->document()->article;
        $this->log(Article::class, $article->id);

        $this->deleteJson("/eva/api/articles/{$article->id}")->assertOk();

        $this->assertSame(1, AnswerLog::count());
    }

    /** Dokumennya tetap ada — dan itu berarti artikelnya bisa dilahirkan lagi. */
    public function test_menghapus_artikel_tidak_menghapus_dokumen_sumbernya(): void
    {
        $document = $this->document();

        $this->deleteJson("/eva/api/articles/{$document->article->id}")->assertOk();

        $this->assertSame(1, Document::count());
        $this->assertSame(0, Article::count());

        $this->postJson("/eva/api/documents/{$document->id}/reindex")->assertStatus(202);

        $this->assertSame(1, Article::count(), 'indeks ulang melahirkan artikelnya kembali');
    }

    /** Tautan subject tambahan ikut terhapus lewat cascade, jadi Coverage benar. */
    public function test_tautan_subject_tambahan_ikut_terhapus(): void
    {
        $this->seedSubject();
        $article = $this->document()->article;
        $article->subjects()->attach(1);

        $this->assertSame(1, DB::table('kb_article_subject')->count());

        $this->deleteJson("/eva/api/articles/{$article->id}")->assertOk();

        $this->assertSame(0, DB::table('kb_article_subject')->count());
    }

    public function test_menghapus_artikel_yang_tidak_ada_membalas_404(): void
    {
        $this->deleteJson('/eva/api/articles/999')->assertNotFound();
    }

    // ---- dokumen -----------------------------------------------------------

    public function test_dokumen_bisa_dihapus_berikut_potongannya(): void
    {
        $document = $this->document();
        $this->assertGreaterThan(0, Chunk::where('document_id', $document->id)->count());

        $this->deleteJson("/eva/api/documents/{$document->id}")
            ->assertOk()
            ->assertJsonPath('deleted', true);

        $this->assertSame(0, Document::count());
        $this->assertSame(0, Chunk::count(), 'potongan ikut terhapus lewat cascade');
    }

    /** Berkas yatim tidak memunculkan error apa pun — ia hanya memenuhi disk. */
    public function test_berkas_aslinya_ikut_terhapus_dari_disk(): void
    {
        $this->postJson('/eva/api/documents', [
            'file' => UploadedFile::fake()->createWithContent('sop.txt', 'Isi SOP.'),
        ])->assertStatus(202);

        $document = Document::sole();
        $path = $document->storage_path;
        Storage::disk('local')->assertExists($path);

        $this->deleteJson("/eva/api/documents/{$document->id}")->assertOk();

        Storage::disk('local')->assertMissing($path);
    }

    /**
     * Batas yang paling mudah mengejutkan: artikelnya TETAP HIDUP dan tetap
     * menjawab, hanya kehilangan jejak asal-usulnya.
     */
    public function test_menghapus_dokumen_tidak_menghapus_artikelnya(): void
    {
        $document = $this->document();
        $articleId = $document->article->id;

        $this->deleteJson("/eva/api/documents/{$document->id}")->assertOk();

        $article = Article::find($articleId);
        $this->assertNotNull($article, 'suntingan admin tidak boleh ikut terbuang');
        $this->assertNull($article->source_document_id);
    }

    public function test_menghapus_dokumen_tidak_menghapus_riwayat_jawaban(): void
    {
        $document = $this->document();
        $this->log(Article::class, $document->article->id);

        $this->deleteJson("/eva/api/documents/{$document->id}")->assertOk();

        $this->assertSame(1, AnswerLog::count());
    }

    public function test_menghapus_dokumen_yang_tidak_ada_membalas_404(): void
    {
        $this->deleteJson('/eva/api/documents/999')->assertNotFound();
    }

    private function seedSubject(): void
    {
        DB::table('issue_categories')->insert(['id' => 1, 'name' => 'Access Request']);
        DB::table('service_catalog_services')->insert(['id' => 1, 'name' => 'SAP']);
        DB::table('service_catalog_subcategories')->insert([['id' => 1, 'service_id' => 1, 'name' => 'LOGIN SAP']]);
        DB::table('service_catalog_subjects')->insert([[
            'id' => 1, 'issue_category_id' => 1, 'service_id' => 1, 'subcategory_id' => 1,
            'name' => 'Reset Password', 'requires_approval' => false, 'support_level' => 1, 'is_active' => true,
        ]]);
    }
}
