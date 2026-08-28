<?php

declare(strict_types=1);

namespace Tests\Feature\Eva;

use App\Models\Knowledge\Article;
use App\Models\Knowledge\Document;
use App\Models\Knowledge\Faq;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\ActsAsRole;
use Tests\TestCase;

/**
 * Membuka materi rujukan dari gelembung jawaban EVA.
 *
 * Selama ini judul sumber di bawah jawaban hanya teks mati: karyawan tahu
 * jawabannya berasal dari "Reset Password SAP" tapi tidak punya jalan untuk
 * membaca panduan utuhnya. Endpoint ini yang membukanya.
 *
 * Gerbangnya WAJIB sama persis dengan gerbang menjawab — scopeAnswerable().
 * Kalau tidak, endpoint ini berubah menjadi pintu belakang untuk membaca
 * artikel draf dan materi yang sengaja disembunyikan dari EVA: cukup menebak
 * nomornya, tanpa perlu jadi admin. Karena itu setiap materi yang tidak boleh
 * dipakai menjawab harus membalas 404 di sini, bukan 200 dengan isinya.
 */
final class AssistantMaterialTest extends TestCase
{
    use ActsAsRole;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->seedCatalog();
        $this->actingAsRole('requester');
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

    private function article(array $attributes = []): Article
    {
        return Article::create(array_merge([
            'title' => 'Reset Password SAP',
            'summary' => 'Ringkasan langkah reset password SAP.',
            'body' => "Langkah 1: buka SAP GUI.\nLangkah 2: pilih menu ubah password.",
            'catalog_subject_id' => 1,
            'status' => Article::STATUS_PUBLISHED,
            'is_eva_visible' => true,
        ], $attributes));
    }

    /**
     * Dokumen sumber berikut berkas aslinya di disk. `withFile: false` untuk
     * dokumen yang isinya diketik admin — bentuk yang paling banyak ada di data
     * lama, dan justru itu yang jalur cadangannya harus benar.
     */
    private function document(array $attributes = [], bool $withFile = true): Document
    {
        $document = Document::create(array_merge([
            'name' => 'SOP Reset Password SAP',
            'original_filename' => 'sop-reset-password-sap.pdf',
            'extension' => 'PDF',
            'size_bytes' => 2048,
            'page_count' => 3,
            'extracted_text' => 'Isi dokumen asli: buka Portal SSO lalu pilih Ubah Password.',
            'storage_path' => $withFile ? 'kb-documents/sop.pdf' : null,
            'status' => Document::STATUS_INDEXED,
            'is_eva_visible' => true,
        ], $attributes));

        if ($document->storage_path !== null) {
            Storage::disk('local')->put($document->storage_path, '%PDF-1.4 berkas asli');
        }

        return $document;
    }

    private function faq(array $attributes = []): Faq
    {
        return Faq::create(array_merge([
            'question' => 'Bagaimana cara reset password SAP?',
            'answer' => 'Buka SAP GUI lalu pilih menu ubah password.',
            'catalog_subject_id' => 1,
            'is_eva_visible' => true,
        ], $attributes));
    }

    private function open(string $type, int|string $id)
    {
        return $this->getJson("/assistant/api/material/{$type}/{$id}");
    }

    // ---- jalan yang dituju -------------------------------------------------

    public function test_artikel_terbit_bisa_dibuka_dari_rujukan(): void
    {
        $article = $this->article();

        $this->open('article', $article->id)
            ->assertOk()
            ->assertJsonPath('type', 'article')
            ->assertJsonPath('id', $article->id)
            ->assertJsonPath('title', 'Reset Password SAP')
            ->assertJsonPath('body', "Langkah 1: buka SAP GUI.\nLangkah 2: pilih menu ubah password.");
    }

    public function test_faq_bisa_dibuka_dari_rujukan(): void
    {
        $faq = $this->faq();

        $this->open('faq', $faq->id)
            ->assertOk()
            ->assertJsonPath('type', 'faq')
            ->assertJsonPath('title', 'Bagaimana cara reset password SAP?')
            ->assertJsonPath('body', 'Buka SAP GUI lalu pilih menu ubah password.');
    }

    /** Supaya popup bisa menyebut materi ini melayani layanan/subject apa. */
    public function test_materi_membawa_subject_katalognya(): void
    {
        $article = $this->article();

        $this->open('article', $article->id)
            ->assertOk()
            ->assertJsonPath('subject.subject', 'Reset Password')
            ->assertJsonPath('subject.service', 'SAP');
    }

    public function test_materi_tanpa_subject_tetap_bisa_dibuka(): void
    {
        $faq = $this->faq(['catalog_subject_id' => null]);

        $this->open('faq', $faq->id)
            ->assertOk()
            ->assertJsonPath('subject', null);
    }

    // ---- dokumen asli di balik kutipan -------------------------------------

    /**
     * Yang dibuka karyawan saat menekan rujukan adalah DOKUMENNYA, bukan
     * artikel hasil ekstraksinya — jadi keterangan berkasnya wajib ikut.
     */
    public function test_artikel_membawa_dokumen_sumbernya(): void
    {
        $document = $this->document();
        $article = $this->article(['source_document_id' => $document->id]);

        $this->open('article', $article->id)
            ->assertOk()
            ->assertJsonPath('document.id', $document->id)
            ->assertJsonPath('document.filename', 'sop-reset-password-sap.pdf')
            ->assertJsonPath('document.extension', 'PDF')
            ->assertJsonPath('document.page_count', 3)
            ->assertJsonPath('document.has_file', true)
            ->assertJsonPath('document.is_previewable', true)
            ->assertJsonPath('document.file_url', route('eva.assistant.document-file', ['document' => $document->id]))
            ->assertJsonPath('document.text', 'Isi dokumen asli: buka Portal SSO lalu pilih Ubah Password.');
    }

    /**
     * Gambar adalah satu-satunya format yang browser tampilkan tanpa usaha
     * apa pun, jadi rujukan berupa foto surat edaran muncul apa adanya di
     * popup — bukan sebagai tombol unduh.
     *
     * `preview_as` ikut dikirim karena caranya berbeda: PDF butuh bingkai,
     * gambar butuh <img>. Gambar yang dijejalkan ke dalam bingkai jadi halaman
     * abu-abu berisi satu gambar mentah.
     */
    public function test_gambar_ditampilkan_apa_adanya_sebagai_rujukan(): void
    {
        foreach (['PNG' => 'edaran.png', 'JPG' => 'edaran.jpg', 'JPEG' => 'edaran.jpeg'] as $extension => $filename) {
            $document = $this->document([
                'extension' => $extension,
                'original_filename' => $filename,
                'storage_path' => 'kb-documents/'.$filename,
            ]);
            $article = $this->article(['source_document_id' => $document->id]);

            $this->open('article', $article->id)
                ->assertOk()
                ->assertJsonPath('document.is_previewable', true)
                ->assertJsonPath('document.preview_as', 'image');
        }
    }

    public function test_pdf_ditandai_dibuka_sebagai_bingkai_bukan_gambar(): void
    {
        $document = $this->document();
        $article = $this->article(['source_document_id' => $document->id]);

        $this->open('article', $article->id)
            ->assertOk()
            ->assertJsonPath('document.preview_as', 'pdf');
    }

    /** Berkasnya hilang: tidak ada yang bisa dipratinjau, apa pun formatnya. */
    public function test_gambar_yang_berkasnya_hilang_tidak_ditandai_bisa_dipratinjau(): void
    {
        $document = $this->document([
            'extension' => 'PNG',
            'original_filename' => 'edaran.png',
        ], withFile: false);
        $article = $this->article(['source_document_id' => $document->id]);

        $this->open('article', $article->id)
            ->assertOk()
            ->assertJsonPath('document.is_previewable', false)
            ->assertJsonPath('document.preview_as', null);
    }

    /**
     * DOCX tidak bisa dirender browser mana pun. Menandainya bisa dipratinjau
     * berarti popup memasang bingkai kosong dan karyawan menyimpulkan
     * dokumennya rusak — padahal berkasnya baik-baik saja, hanya perlu diunduh.
     */
    public function test_docx_ditandai_tidak_bisa_dipratinjau_tapi_tetap_bisa_diunduh(): void
    {
        $document = $this->document([
            'extension' => 'DOCX',
            'original_filename' => 'panduan-vpn.docx',
            'storage_path' => 'kb-documents/panduan.docx',
        ]);
        $article = $this->article(['source_document_id' => $document->id]);

        $this->open('article', $article->id)
            ->assertOk()
            ->assertJsonPath('document.has_file', true)
            ->assertJsonPath('document.is_previewable', false)
            ->assertJsonPath('document.preview_as', null)
            ->assertJsonPath('document.file_url', route('eva.assistant.document-file', ['document' => $document->id]));
    }

    /** Dokumen yang isinya diketik admin: tidak ada berkas, dan itu wajar. */
    public function test_dokumen_tanpa_berkas_tetap_membawa_isinya(): void
    {
        $document = $this->document(withFile: false);
        $article = $this->article(['source_document_id' => $document->id]);

        $this->open('article', $article->id)
            ->assertOk()
            ->assertJsonPath('document.has_file', false)
            ->assertJsonPath('document.is_previewable', false)
            ->assertJsonPath('document.file_url', null)
            ->assertJsonPath('document.text', 'Isi dokumen asli: buka Portal SSO lalu pilih Ubah Password.');
    }

    /**
     * Baris dokumennya menyebut sebuah berkas yang sudah tidak ada di disk.
     * Ditawarkan sebagai berkas, popup akan memasang pratinjau berisi 404 —
     * dan itu terbaca sebagai aplikasi yang rusak, bukan berkas yang hilang.
     */
    public function test_berkas_yang_hilang_dari_disk_tidak_ditawarkan(): void
    {
        $document = $this->document();
        Storage::disk('local')->delete($document->storage_path);
        $article = $this->article(['source_document_id' => $document->id]);

        $this->open('article', $article->id)
            ->assertOk()
            ->assertJsonPath('document.has_file', false)
            ->assertJsonPath('document.file_url', null);
    }

    /**
     * Dokumen punya saklar EVA-nya sendiri. Admin boleh menyembunyikan berkas
     * aslinya sambil membiarkan artikelnya tetap menjawab — dan saat itu
     * terjadi, tidak boleh ada satu pun alamat berkas yang bocor ke layar.
     */
    public function test_dokumen_yang_disembunyikan_dari_eva_tidak_ikut_terkirim(): void
    {
        $document = $this->document(['is_eva_visible' => false]);
        $article = $this->article(['source_document_id' => $document->id]);

        $this->open('article', $article->id)
            ->assertOk()
            ->assertJsonPath('document', null);
    }

    /** FAQ ditulis langsung admin — tidak pernah lahir dari berkas. */
    public function test_faq_tidak_punya_dokumen(): void
    {
        $faq = $this->faq();

        $this->open('faq', $faq->id)
            ->assertOk()
            ->assertJsonPath('document', null);
    }

    /** Dokumen sumbernya dihapus; artikelnya sengaja tetap hidup. */
    public function test_artikel_tanpa_dokumen_sumber_tetap_bisa_dibuka(): void
    {
        $article = $this->article();

        $this->open('article', $article->id)
            ->assertOk()
            ->assertJsonPath('document', null)
            ->assertJsonPath('body', "Langkah 1: buka SAP GUI.\nLangkah 2: pilih menu ubah password.");
    }

    // ---- gerbang: sama persis dengan gerbang menjawab -----------------------

    public function test_artikel_draf_tidak_bisa_dibuka(): void
    {
        $article = $this->article(['status' => Article::STATUS_DRAFT]);

        $this->open('article', $article->id)->assertNotFound();
    }

    public function test_artikel_yang_disembunyikan_dari_eva_tidak_bisa_dibuka(): void
    {
        $article = $this->article(['is_eva_visible' => false]);

        $this->open('article', $article->id)->assertNotFound();
    }

    public function test_faq_yang_disembunyikan_dari_eva_tidak_bisa_dibuka(): void
    {
        $faq = $this->faq(['is_eva_visible' => false]);

        $this->open('faq', $faq->id)->assertNotFound();
    }

    public function test_materi_yang_tidak_ada_membalas_404(): void
    {
        $this->open('article', 4321)->assertNotFound();
    }

    /**
     * Nomor artikel dan nomor FAQ berjalan sendiri-sendiri, jadi id yang sama
     * bisa hidup di kedua tabel. Salah jenis harus berakhir 404, bukan
     * memulangkan materi lain yang kebetulan bernomor sama.
     */
    public function test_jenis_yang_salah_tidak_memulangkan_materi_bernomor_sama(): void
    {
        $faq = $this->faq();

        $this->open('article', $faq->id)->assertNotFound();
    }

    public function test_jenis_materi_tak_dikenal_ditolak(): void
    {
        $this->article();

        $this->open('dokumen', 1)->assertNotFound();
    }

    public function test_tamu_ditolak(): void
    {
        $article = $this->article();

        auth()->logout();

        $this->open('article', $article->id)->assertUnauthorized();
    }
}
