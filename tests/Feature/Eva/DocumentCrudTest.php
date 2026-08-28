<?php

namespace Tests\Feature\Eva;

use App\Models\Knowledge\Article;
use App\Models\Knowledge\Chunk;
use App\Models\Knowledge\Document;
use App\Models\User;
use App\Services\Knowledge\DocumentTextExtractor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
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
    // ---- menyunting ISI dokumen -------------------------------------------

    /**
     * Kolom yang selama ini hilang.
     *
     * Saat OCR gagal membaca sebuah berkas, dokumennya ditandai `failed` dengan
     * kalimat "salin teksnya ke kolom isi, lalu indeks ulang" — kalimat yang
     * menunjuk ke kolom yang tidak pernah ada. Satu-satunya jalan keluar dulu
     * adalah menghapus dokumennya lalu mengunggah ulang, membuang berkas
     * aslinya sekalian.
     */
    public function test_isi_dokumen_bisa_disunting_dan_ikut_terindeks_ulang(): void
    {
        $document = $this->indexedDocument('Teks lama yang salah baca.');

        $this->putJson("/eva/api/documents/{$document->id}", $this->payload([
            'extracted_text' => 'Buka Portal SSO, pilih Akun Saya, lalu klik Ubah Password.',
        ]))->assertStatus(202);

        $document->refresh();

        $this->assertSame('Buka Portal SSO, pilih Akun Saya, lalu klik Ubah Password.', $document->extracted_text);
        $this->assertTrue($document->isIndexed(), 'dokumen harus selesai diindeks ulang');
        // Potongan pencarian lahir dari teks ini — dibiarkan tertinggal, EVA
        // mencari di kalimat yang sudah tidak ada lagi di dokumennya.
        $this->assertStringContainsString('Portal SSO', Chunk::where('document_id', $document->id)->value('content'));
    }

    /**
     * Badan ARTIKEL sengaja TIDAK ikut tertimpa.
     *
     * Aturannya milik DocumentIndexer dan sudah lama berlaku: judul, ringkasan,
     * dan badan artikel hanya diisi saat artikel pertama kali lahir; sesudah itu
     * ia milik admin yang menyuntingnya di Article Library. Menyunting dokumen
     * lalu diam-diam membuang pekerjaan itu jauh lebih merugikan daripada
     * artikel yang tertinggal satu versi.
     */
    public function test_menyunting_isi_dokumen_tidak_menimpa_suntingan_artikel(): void
    {
        $document = $this->indexedDocument('Teks lama yang salah baca.');
        $document->article->update(['body' => 'Kalimat yang sudah dirapikan admin.']);

        $this->putJson("/eva/api/documents/{$document->id}", $this->payload([
            'extracted_text' => 'Buka Portal SSO, pilih Akun Saya, lalu klik Ubah Password.',
        ]))->assertStatus(202);

        $this->assertSame('Kalimat yang sudah dirapikan admin.', $document->fresh()->article->body);
    }

    /**
     * Jalur yang paling penting: dokumen GAGAL belum punya artikel sama sekali
     * (DocumentIndexer berhenti sebelum membuatnya), jadi isi yang diketik admin
     * di sinilah yang melahirkannya — dan inilah yang membuat foto tak terbaca
     * tetap bisa jadi rujukan.
     */
    public function test_dokumen_gagal_yang_isinya_diketik_melahirkan_artikel(): void
    {
        $this->postJson('/eva/api/documents', [
            'file' => UploadedFile::fake()->createWithContent('foto-sop.png', 'bukan gambar sungguhan'),
            'extracted_text' => ' ',
        ])->assertStatus(202);

        $document = Document::sole();
        $this->assertSame(Document::STATUS_FAILED, $document->status, 'berkas ini memang tidak terbaca');
        $this->assertNull($document->article);

        $this->putJson("/eva/api/documents/{$document->id}", $this->payload([
            'name' => 'foto-sop',
            'extracted_text' => 'Isi surat edaran yang diketik ulang admin.',
        ]))->assertStatus(202);

        $document->refresh();

        $this->assertTrue($document->isIndexed());
        $this->assertNotNull($document->article, 'artikelnya baru lahir sekarang');
        $this->assertStringContainsString('diketik ulang admin', $document->article->body);
    }

    /**
     * Isi yang tidak ikut dikirim TIDAK boleh menyentuh apa pun — form
     * keterangan yang lupa membawa kolom isi tidak boleh diam-diam mengosongkan
     * dokumen, dan tidak boleh membangunkan antrean tanpa alasan.
     */
    public function test_menyunting_keterangan_tidak_mengindeks_ulang(): void
    {
        $document = $this->indexedDocument();
        Queue::fake();

        $this->putJson("/eva/api/documents/{$document->id}", $this->payload(['name' => 'Nama baru']))
            ->assertOk();

        Queue::assertNothingPushed();
        $this->assertTrue($document->fresh()->isIndexed());
    }

    /** Isi yang dikirim SAMA PERSIS juga bukan perubahan. */
    public function test_mengirim_isi_yang_sama_tidak_mengindeks_ulang(): void
    {
        $document = $this->indexedDocument('Teks yang tidak berubah.');
        Queue::fake();

        $this->putJson("/eva/api/documents/{$document->id}", $this->payload([
            'extracted_text' => 'Teks yang tidak berubah.',
        ]))->assertOk();

        Queue::assertNothingPushed();
    }

    /**
     * Mengosongkan kolom isi berarti "baca ulang berkasnya", BUKAN "dokumen ini
     * tanpa isi" — jalan kembali bagi admin yang terlanjur menempel teks salah.
     */
    public function test_mengosongkan_isi_membaca_ulang_berkas_aslinya(): void
    {
        $this->postJson('/eva/api/documents', [
            'file' => UploadedFile::fake()->createWithContent('sop.txt', 'Isi asli dari berkas yang tersimpan.'),
        ])->assertStatus(202);

        $document = Document::sole();

        $this->putJson("/eva/api/documents/{$document->id}", $this->payload([
            'name' => 'sop',
            'extracted_text' => 'Teks tempel yang salah.',
        ]))->assertStatus(202);
        $this->assertSame('Teks tempel yang salah.', $document->fresh()->extracted_text);

        $this->putJson("/eva/api/documents/{$document->id}", $this->payload([
            'name' => 'sop',
            'extracted_text' => '',
        ]))->assertStatus(202);

        $this->assertSame('Isi asli dari berkas yang tersimpan.', $document->fresh()->extracted_text);
    }

    // ---- pratinjau ---------------------------------------------------------

    public function test_isi_dokumen_bisa_dibaca_untuk_pratinjau(): void
    {
        $document = $this->indexedDocument('Isi yang akan dibaca admin.');

        $this->getJson("/eva/api/documents/{$document->id}/content")
            ->assertOk()
            ->assertJsonPath('document.id', $document->id)
            ->assertJsonPath('document.text', 'Isi yang akan dibaca admin.')
            // Dokumen ini lahir dari teks tempel — tidak ada berkas untuk
            // ditampilkan, dan layar harus tahu itu supaya tidak memasang
            // bingkai pratinjau yang kosong.
            ->assertJsonPath('document.has_file', false)
            ->assertJsonPath('document.preview_as', null);
    }

    /**
     * Berkasnya bisa dibuka admin MESKIPUN artikelnya belum siap-jawab.
     *
     * Ini bedanya dengan endpoint kembarannya untuk karyawan, dan bedanya
     * disengaja: dokumen yang artikelnya masih draf — atau yang indexing-nya
     * gagal — justru yang paling perlu dibuka, karena admin membukanya untuk
     * mencari tahu kenapa.
     */
    public function test_berkas_dokumen_yang_artikelnya_masih_draf_tetap_bisa_dipratinjau_admin(): void
    {
        $this->postJson('/eva/api/documents', [
            'file' => UploadedFile::fake()->createWithContent('sop.txt', 'Isi berkas asli.'),
        ])->assertStatus(202);

        $document = Document::sole();
        $this->assertSame(Article::STATUS_DRAFT, $document->article->status, 'artikel memang lahir sebagai draf');

        $response = $this->get("/eva/api/documents/{$document->id}/file")->assertOk();

        $this->assertSame('Isi berkas asli.', $response->streamedContent());
        $this->assertStringStartsWith('inline;', $response->headers->get('Content-Disposition'));
    }

    public function test_pratinjau_dokumen_tanpa_berkas_membalas_404(): void
    {
        $document = $this->indexedDocument();

        $this->get("/eva/api/documents/{$document->id}/file")->assertNotFound();
    }

    public function test_berkas_dokumen_tidak_bisa_dibuka_tanpa_role_eva(): void
    {
        $this->postJson('/eva/api/documents', [
            'file' => UploadedFile::fake()->createWithContent('sop.txt', 'Isi berkas asli.'),
        ])->assertStatus(202);

        $document = Document::sole();

        $this->actingAsRole('requester');

        $this->get("/eva/api/documents/{$document->id}/file")->assertForbidden();
        $this->getJson("/eva/api/documents/{$document->id}/content")->assertForbidden();
    }

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
