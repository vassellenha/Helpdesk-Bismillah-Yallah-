<?php

namespace Tests\Feature\Eva;

use App\Models\Knowledge\Article;
use App\Models\Knowledge\Chunk;
use App\Models\Knowledge\Document;
use App\Models\User;
use App\Services\Knowledge\DocumentTextExtractor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\ActsAsEvaAdmin;
use Tests\TestCase;

/**
 * Merawat dokumen yang SUDAH masuk: menyunting keterangannya dan mengindeks
 * ulang. Jalur unggah dan antreannya diuji di DocumentUploadTest &
 * DocumentQueueTest — di sini yang dijaga adalah apa yang terjadi setelahnya.
 *
 * Dua invarian yang paling mahal kalau bocor:
 *
 *  1. **Indeks ulang tidak menggandakan.** Artikel dicari lewat
 *     `source_document_id` dan potongan lama dihapus dulu. Kalau ini rusak,
 *     tidak ada error apa pun — hanya artikel kembar dan potongan menumpuk yang
 *     membuat EVA menjawab dengan materi yang sama berkali-kali.
 *  2. **Menyunting keterangan tidak menyentuh isi.** Form ini hanya mengubah
 *     nama/subject/tag; teks hasil OCR dan status indeks bukan miliknya.
 */
final class DocumentCrudTest extends TestCase
{
    use ActsAsEvaAdmin;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        Storage::fake('local');
        User::factory()->create(['name' => 'Marcell Laforteza', 'email' => 'marcell.laforteza@adhi.co.id', 'nip' => '19870114001']);
        $this->seedCatalog();

        $this->actingAsEvaAdmin();
    }

    private function seedCatalog(): void
    {
        DB::table('issue_categories')->insert(['id' => 1, 'name' => 'Access Request']);
        DB::table('service_catalog_services')->insert(['id' => 1, 'name' => 'SAP']);
        DB::table('service_catalog_subcategories')->insert([
            ['id' => 1, 'service_id' => 1, 'name' => 'LOGIN SAP'],
        ]);
        DB::table('service_catalog_subjects')->insert([[
            'id' => 1, 'issue_category_id' => 1, 'service_id' => 1,
            'subcategory_id' => 1, 'name' => 'Reset Password',
            'requires_approval' => false, 'support_level' => 1, 'is_active' => true,
        ]]);
    }

    /** Dokumen yang sudah melewati antrean, lengkap dengan artikel & potongannya. */
    private function indexedDocument(string $text = 'Langkah mengatur ulang kata sandi SAP yang kedaluwarsa.'): Document
    {
        $this->postJson('/eva/api/documents', [
            'name' => 'SOP Reset Password SAP',
            'extension' => 'TXT',
            'extracted_text' => $text,
        ])->assertStatus(202);

        return Document::sole();
    }

    /** @param array<string,mixed> $overrides */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'SOP Reset Password SAP',
            'catalog_subject_id' => 1,
            'is_eva_visible' => true,
            'tags' => 'sap,password',
        ], $overrides);
    }

    // ---- layar -------------------------------------------------------------

    public function test_halaman_documents_tampil(): void
    {
        $this->get('/eva/documents')->assertOk();
    }

    /**
     * Daftar format yang "terbaca otomatis" datang dari DocumentTextExtractor,
     * bukan diketik ulang di layar. Kalau keduanya berpisah, layar menjanjikan
     * pembacaan otomatis yang tidak pernah terjadi.
     */
    public function test_daftar_format_terbaca_ikut_kemampuan_ekstraktor(): void
    {
        $readable = $this->get('/eva/documents')->assertOk()->viewData('readableExtensions');
        $extractor = $this->app->make(DocumentTextExtractor::class);

        foreach ($this->get('/eva/documents')->viewData('extensions') as $extension) {
            $this->assertSame(
                $extractor->canRead($extension),
                in_array($extension, $readable, true),
                "daftar layar dan kemampuan ekstraktor berbeda untuk {$extension}",
            );
        }

        $this->assertNotContains('XLSX', $readable, 'XLSX tidak pernah terbaca sendiri');
    }

    // ---- menyunting keterangan ---------------------------------------------

    public function test_keterangan_dokumen_bisa_disunting(): void
    {
        $document = $this->indexedDocument();

        $this->putJson("/eva/api/documents/{$document->id}", $this->payload([
            'name' => 'SOP Reset Password SAP (revisi 2)',
        ]))
            ->assertOk()
            ->assertJsonPath('name', 'SOP Reset Password SAP (revisi 2)')
            ->assertJsonPath('catalog_subject_id', 1)
            ->assertJsonPath('subject_name', 'Reset Password');

        $this->assertSame('SOP Reset Password SAP (revisi 2)', $document->fresh()->name);
    }

    /** Isi hasil pembacaan dan status indeks bukan milik form keterangan. */
    public function test_menyunting_keterangan_tidak_menyentuh_isi_dokumen(): void
    {
        $document = $this->indexedDocument('Teks hasil pembacaan yang tidak boleh hilang.');

        $this->putJson("/eva/api/documents/{$document->id}", $this->payload())->assertOk();

        $fresh = $document->fresh();
        $this->assertSame('Teks hasil pembacaan yang tidak boleh hilang.', $fresh->extracted_text);
        $this->assertSame(Document::STATUS_INDEXED, $fresh->status);
    }

    public function test_dokumen_bisa_disembunyikan_dari_eva(): void
    {
        $document = $this->indexedDocument();

        $this->putJson("/eva/api/documents/{$document->id}", $this->payload(['is_eva_visible' => false]))
            ->assertOk()
            ->assertJsonPath('is_eva_visible', false);

        $this->assertFalse($document->fresh()->is_eva_visible);
    }

    public function test_nama_wajib_diisi(): void
    {
        $payload = $this->payload();
        unset($payload['name']);

        $this->putJson("/eva/api/documents/{$this->indexedDocument()->id}", $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('name');
    }

    public function test_subject_katalog_asing_ditolak(): void
    {
        $document = $this->indexedDocument();

        $this->putJson("/eva/api/documents/{$document->id}", $this->payload(['catalog_subject_id' => 999]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('catalog_subject_id');

        $this->assertNull($document->fresh()->catalog_subject_id);
    }

    public function test_menyunting_dokumen_yang_tidak_ada_membalas_404(): void
    {
        $this->putJson('/eva/api/documents/999', $this->payload())->assertNotFound();
    }

    public function test_status_dokumen_yang_tidak_ada_membalas_404(): void
    {
        $this->getJson('/eva/api/documents/999')->assertNotFound();
    }

    // ---- indeks ulang ------------------------------------------------------

    /**
     * Inti indeks ulang: dijalankan berkali-kali hasilnya tetap SATU artikel dan
     * jumlah potongan yang sama. Penggandaan di sini tidak memunculkan error —
     * ia hanya membuat EVA menawarkan materi kembar.
     */
    public function test_indeks_ulang_tidak_menggandakan_artikel_maupun_potongan(): void
    {
        $document = $this->indexedDocument();

        $articleId = $document->article->id;
        $chunkCount = Chunk::where('document_id', $document->id)->count();
        $this->assertGreaterThan(0, $chunkCount);

        $this->postJson("/eva/api/documents/{$document->id}/reindex")->assertStatus(202);
        $this->postJson("/eva/api/documents/{$document->id}/reindex")->assertStatus(202);

        $this->assertSame(1, Article::where('source_document_id', $document->id)->count());
        $this->assertSame($articleId, $document->fresh()->article->id, 'artikel yang sama diperbarui, bukan diganti');
        $this->assertSame($chunkCount, Chunk::where('document_id', $document->id)->count());
    }

    /**
     * Dokumen yang gagal dan dicoba ulang harus melepas alasan gagal LAMA.
     * Lencana merah berikut alasan yang sudah tidak berlaku lebih menyesatkan
     * daripada tidak ada keterangan sama sekali.
     */
    public function test_indeks_ulang_menghapus_alasan_gagal_lama(): void
    {
        $document = Document::create([
            'name' => 'SOP Gagal',
            'extension' => 'TXT',
            'extracted_text' => 'Isi yang sebenarnya bisa dibaca.',
            'status' => Document::STATUS_FAILED,
            'failure_reason' => 'Isi dokumen kosong.',
        ]);

        $this->postJson("/eva/api/documents/{$document->id}/reindex")->assertStatus(202);

        $fresh = $document->fresh();
        $this->assertSame(Document::STATUS_INDEXED, $fresh->status);
        $this->assertNull($fresh->failure_reason);
    }

    /** Menyunting subject lalu indeks ulang membawa subject itu ke artikelnya. */
    public function test_indeks_ulang_membawa_subject_terbaru_ke_artikel(): void
    {
        $document = $this->indexedDocument();
        $this->assertNull($document->article->catalog_subject_id);

        $this->putJson("/eva/api/documents/{$document->id}", $this->payload())->assertOk();
        $this->postJson("/eva/api/documents/{$document->id}/reindex")->assertStatus(202);

        $this->assertSame(1, $document->fresh()->article->catalog_subject_id);
    }

    public function test_indeks_ulang_dokumen_yang_tidak_ada_membalas_404(): void
    {
        $this->postJson('/eva/api/documents/999/reindex')->assertNotFound();
    }
}
