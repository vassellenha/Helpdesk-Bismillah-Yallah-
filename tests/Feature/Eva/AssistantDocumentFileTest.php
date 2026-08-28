<?php

declare(strict_types=1);

namespace Tests\Feature\Eva;

use App\Models\Knowledge\Article;
use App\Models\Knowledge\Document;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\ActsAsRole;
use Tests\TestCase;

/**
 * Membuka BERKAS ASLI dokumen dari kutipan jawaban EVA.
 *
 * Sebelum ini rujukan di bawah jawaban membuka artikel hasil ekstraksi —
 * salinan teks yang boleh disunting admin. Yang dipercaya karyawan adalah SOP
 * atau surat edarannya sendiri, lengkap dengan kop dan tanda tangan, dan
 * endpoint inilah satu-satunya jalan berkas itu keluar: unggahannya tersimpan
 * di disk privat, di luar document root.
 *
 * Karena itu gerbangnya harus lengkap, dan setiap penolakan berbunyi 404 yang
 * sama — dokumen yang ada tapi tidak boleh dibuka tidak boleh terbedakan dari
 * dokumen yang memang tidak ada, sebab itulah satu-satunya hal yang ingin
 * diketahui penebak nomor.
 */
final class AssistantDocumentFileTest extends TestCase
{
    use ActsAsRole;
    use RefreshDatabase;

    private const ISI_BERKAS = '%PDF-1.4 ini berkas aslinya';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->actingAsRole('requester');
    }

    private function document(array $attributes = [], bool $withFile = true): Document
    {
        $document = Document::create(array_merge([
            'name' => 'SOP Reset Password SAP',
            'original_filename' => 'sop-reset-password-sap.pdf',
            'extension' => 'PDF',
            'size_bytes' => 2048,
            'extracted_text' => 'Buka Portal SSO lalu pilih Ubah Password.',
            'storage_path' => $withFile ? 'kb-documents/sop.pdf' : null,
            'status' => Document::STATUS_INDEXED,
            'is_eva_visible' => true,
        ], $attributes));

        if ($document->storage_path !== null) {
            Storage::disk('local')->put($document->storage_path, self::ISI_BERKAS);
        }

        return $document;
    }

    private function article(Document $document, array $attributes = []): Article
    {
        return Article::create(array_merge([
            'title' => 'SOP Reset Password SAP',
            'body' => 'Buka Portal SSO lalu pilih Ubah Password.',
            'source_document_id' => $document->id,
            'status' => Article::STATUS_PUBLISHED,
            'is_eva_visible' => true,
        ], $attributes));
    }

    private function open(Document|int $document)
    {
        $id = $document instanceof Document ? $document->id : $document;

        return $this->get("/assistant/api/document/{$id}/file");
    }

    // ---- jalan yang dituju -------------------------------------------------

    public function test_berkas_dokumen_yang_dikutip_bisa_dibuka(): void
    {
        $document = $this->document();
        $this->article($document);

        $response = $this->open($document)->assertOk();

        $this->assertSame(self::ISI_BERKAS, $response->streamedContent());
    }

    /**
     * `inline`, bukan `attachment`: PDF dibaca langsung di dalam popup. Berubah
     * jadi attachment, setiap rujukan yang ditekan berakhir sebagai berkas di
     * folder Unduhan — dan tidak ada yang terbaca di tempat.
     */
    public function test_berkas_disajikan_untuk_dibaca_di_tempat_bukan_diunduh(): void
    {
        $document = $this->document();
        $this->article($document);

        $response = $this->open($document)->assertOk();

        $this->assertStringStartsWith('inline;', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('sop-reset-password-sap.pdf', $response->headers->get('Content-Disposition'));
    }

    /**
     * Tautan ini sudah diperiksa per permintaan. Tersimpan di cache bersama
     * (proxy kantor), orang berikutnya bisa menerima berkas yang bukan haknya
     * tanpa pernah menyentuh gerbang di atas.
     */
    public function test_berkas_tidak_boleh_tersimpan_di_cache_bersama(): void
    {
        $document = $this->document();
        $this->article($document);

        $cacheControl = $this->open($document)->assertOk()->headers->get('Cache-Control');

        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertStringContainsString('private', $cacheControl);
    }

    /** Nama berkas untuk dokumen yang tidak pernah diunggah sebagai berkas. */
    public function test_nama_unduhan_disusun_dari_nama_dokumen_bila_nama_berkas_asli_kosong(): void
    {
        $document = $this->document(['original_filename' => null]);
        $this->article($document);

        $response = $this->open($document)->assertOk();

        $this->assertStringContainsString('SOP Reset Password SAP.pdf', $response->headers->get('Content-Disposition'));
    }

    // ---- gerbang -----------------------------------------------------------

    /**
     * Inti gerbangnya: berkas hanya boleh keluar kalau artikel turunannya
     * memang boleh dipakai EVA menjawab. Tanpa ini, SOP internal bisa diunduh
     * cukup dengan menebak nomor dokumen — termasuk yang artikelnya masih draf.
     */
    public function test_berkas_dokumen_yang_artikelnya_masih_draf_ditolak(): void
    {
        $document = $this->document();
        $this->article($document, ['status' => Article::STATUS_DRAFT]);

        $this->open($document)->assertNotFound();
    }

    public function test_berkas_dokumen_yang_artikelnya_disembunyikan_dari_eva_ditolak(): void
    {
        $document = $this->document();
        $this->article($document, ['is_eva_visible' => false]);

        $this->open($document)->assertNotFound();
    }

    /** Saklar dokumen sendiri, terpisah dari saklar artikelnya. */
    public function test_berkas_dokumen_yang_disembunyikan_dari_eva_ditolak(): void
    {
        $document = $this->document(['is_eva_visible' => false]);
        $this->article($document);

        $this->open($document)->assertNotFound();
    }

    /** Dokumen yang belum pernah melahirkan artikel — EVA tak pernah mengutipnya. */
    public function test_berkas_dokumen_tanpa_artikel_ditolak(): void
    {
        $document = $this->document();

        $this->open($document)->assertNotFound();
    }

    public function test_dokumen_tanpa_berkas_membalas_404(): void
    {
        $document = $this->document(withFile: false);
        $this->article($document);

        $this->open($document)->assertNotFound();
    }

    /** Barisnya menyebut berkas yang sudah tidak ada di disk. */
    public function test_berkas_yang_hilang_dari_disk_membalas_404(): void
    {
        $document = $this->document();
        $this->article($document);
        Storage::disk('local')->delete($document->storage_path);

        $this->open($document)->assertNotFound();
    }

    public function test_dokumen_yang_tidak_ada_membalas_404(): void
    {
        $this->open(4321)->assertNotFound();
    }

    public function test_tamu_ditolak(): void
    {
        $document = $this->document();
        $this->article($document);

        auth()->logout();

        $this->open($document)->assertUnauthorized();
    }
}
