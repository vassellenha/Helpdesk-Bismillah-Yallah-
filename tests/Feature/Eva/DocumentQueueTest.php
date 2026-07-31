<?php

namespace Tests\Feature\Eva;

use App\Jobs\Knowledge\IndexDocument;
use App\Models\Knowledge\Document;
use App\Models\User;
use App\Services\Knowledge\PdfTextReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\Concerns\ActsAsEvaAdmin;
use Tests\TestCase;
use ZipArchive;

/**
 * Indexing dokumen berjalan di ANTREAN, bukan di dalam request.
 *
 * Sebelum ini seluruh jalur — termasuk OCR — dikerjakan sinkron di
 * DocumentController::store(). Satu PDF pindai berhalaman banyak sudah cukup
 * untuk menggantung request sampai timeout, dan admin tidak pernah tahu apakah
 * berkasnya masuk atau hilang.
 *
 * Tiga hal yang dikunci di sini:
 *  1. Request selesai cepat: 202 + dokumen berstatus `processing`.
 *  2. Dokumen TIDAK PERNAH tertinggal `processing` selamanya — jalur gagal
 *     apa pun (teks tak terbaca, mesin OCR meledak) berakhir `failed` dengan
 *     alasan yang bisa dibaca admin di layar.
 *  3. Format yang MUSTAHIL terbaca tetap ditolak seketika, bukan diterima lalu
 *     gagal diam-diam beberapa detik kemudian.
 */
final class DocumentQueueTest extends TestCase
{
    use ActsAsEvaAdmin;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        User::factory()->create(['name' => 'Marcell Laforteza', 'email' => 'marcell.laforteza@adhi.co.id', 'nip' => '19870114001']);

        $this->actingAsEvaAdmin();
    }

    private function docxFile(string $text): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'kbdocx');

        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('word/document.xml',
            '<?xml version="1.0"?><w:document xmlns:w="http://x"><w:body>'.
            '<w:p><w:r><w:t>'.$text.'</w:t></w:r></w:p></w:body></w:document>');
        $zip->close();

        return new UploadedFile($path, 'SOP Unlock Akun.docx', null, null, true);
    }

    /**
     * Mesin PDF palsu. Dipakai supaya jalur gagal & jalur berhasil bisa diuji
     * PERSIS, tanpa bergantung apakah tesseract/poppler terpasang di mesin yang
     * menjalankan tes — seam yang sama seperti KnowledgeSearch di
     * PreviewControllerTest.
     */
    private function fakePdfReader(?string $text, bool $throws = false): void
    {
        $this->app->instance(PdfTextReader::class, new class($text, $throws) implements PdfTextReader
        {
            public function __construct(private readonly ?string $text, private readonly bool $throws) {}

            public function isAvailable(): bool
            {
                return true;
            }

            public function read(string $pdfPath): ?string
            {
                if ($this->throws) {
                    throw new RuntimeException('tesseract keluar dengan kode 1');
                }

                return $this->text;
            }
        });
    }

    public function test_unggah_membalas_202_dan_dokumen_berstatus_processing(): void
    {
        Queue::fake();

        $this->postJson('/eva/api/documents', ['file' => $this->docxFile('Isi SOP.')])
            ->assertStatus(202)
            ->assertJsonPath('status', Document::STATUS_PROCESSING);

        $this->assertSame(Document::STATUS_PROCESSING, Document::sole()->status);
        Queue::assertPushed(IndexDocument::class);
    }

    /** Request tidak menunggu pembacaan berkas — job yang mengerjakannya. */
    public function test_berkas_belum_dibaca_saat_request_selesai(): void
    {
        Queue::fake();

        $this->postJson('/eva/api/documents', ['file' => $this->docxFile('Isi SOP.')])->assertStatus(202);

        $this->assertNull(Document::sole()->extracted_text);
    }

    public function test_job_mengekstrak_isi_berkas_lalu_mengindeks(): void
    {
        $this->postJson('/eva/api/documents', [
            'file' => $this->docxFile('Akun SAP terkunci setelah lima kali gagal login.'),
        ])->assertStatus(202);

        $document = Document::with('article')->sole();
        $this->assertSame('Akun SAP terkunci setelah lima kali gagal login.', $document->extracted_text);
        $this->assertTrue($document->isIndexed());
        $this->assertNotNull($document->article, 'jalur dokumen → artikel tetap utuh lewat antrean');
    }

    /** OCR yang berhasil melahirkan artikel persis seperti jalur sinkron dulu. */
    public function test_pdf_terbaca_lewat_mesin_ocr_terindeks(): void
    {
        $this->fakePdfReader('Langkah membuka akun SAP yang terkunci.');

        $this->postJson('/eva/api/documents', [
            'file' => UploadedFile::fake()->create('sop.pdf', 12, 'application/pdf'),
        ])->assertStatus(202);

        $document = Document::sole();
        $this->assertTrue($document->isIndexed());
        $this->assertStringContainsString('membuka akun SAP', $document->extracted_text);
        $this->assertNull($document->failure_reason);
    }

    /**
     * Kegagalan baca TIDAK LAGI bisa jadi 422 — mesinnya baru menjawab setelah
     * request selesai. Gantinya wajib ada: status `failed` BESERTA alasannya,
     * kalau tidak admin hanya melihat lencana merah tanpa petunjuk apa pun.
     */
    public function test_pdf_yang_gagal_dibaca_berakhir_failed_dengan_alasan(): void
    {
        $this->fakePdfReader(null);

        $this->postJson('/eva/api/documents', [
            'file' => UploadedFile::fake()->create('sop.pdf', 12, 'application/pdf'),
        ])->assertStatus(202);

        $document = Document::with('article')->sole();
        $this->assertSame(Document::STATUS_FAILED, $document->status);
        $this->assertNotNull($document->failure_reason);
        $this->assertNull($document->article, 'gagal baca tidak boleh melahirkan artikel tanpa isi');
    }

    /**
     * Invarian terpenting: apa pun yang meledak di dalam job, dokumen tidak
     * boleh tertinggal `processing` selamanya. Baris yang macet di situ tidak
     * memunculkan error apa pun — ia cuma diam, dan admin menunggu sesuatu yang
     * tak akan pernah datang.
     */
    public function test_mesin_ocr_yang_meledak_tetap_menandai_dokumen_failed(): void
    {
        $this->fakePdfReader(null, throws: true);

        $this->postJson('/eva/api/documents', [
            'file' => UploadedFile::fake()->create('sop.pdf', 12, 'application/pdf'),
        ])->assertStatus(202);

        $document = Document::sole();
        $this->assertSame(Document::STATUS_FAILED, $document->status);
        $this->assertStringContainsString('tesseract', $document->failure_reason);
    }

    /**
     * XLSX tidak akan pernah terbaca sendiri — itu sudah diketahui SEBELUM
     * antrean menyentuhnya. Menerimanya jadi 202 lalu menggagalkannya diam-diam
     * cuma menukar pesan jelas dengan lencana merah.
     */
    public function test_format_yang_mustahil_terbaca_ditolak_seketika(): void
    {
        Queue::fake();

        $this->postJson('/eva/api/documents', [
            'file' => UploadedFile::fake()->create('data.xlsx', 12),
        ])->assertStatus(422)->assertJsonValidationErrors('extracted_text');

        $this->assertSame(0, Document::count());
        Queue::assertNothingPushed();
    }

    /** Teks tempel tidak perlu dibaca dari berkas — tapi tetap lewat antrean. */
    public function test_teks_tempel_tetap_diindeks_lewat_antrean(): void
    {
        $this->postJson('/eva/api/documents', [
            'name' => 'SOP Manual',
            'extension' => 'TXT',
            'extracted_text' => 'Isi SOP yang diketik langsung.',
        ])->assertStatus(202);

        $this->assertTrue(Document::sole()->isIndexed());
    }

    public function test_indeks_ulang_mengantre_dan_kembali_ke_processing(): void
    {
        $this->postJson('/eva/api/documents', [
            'name' => 'SOP Manual', 'extension' => 'TXT', 'extracted_text' => 'Isi SOP.',
        ])->assertStatus(202);

        $document = Document::sole();
        Queue::fake();

        $this->postJson("/eva/api/documents/{$document->id}/reindex")
            ->assertStatus(202)
            ->assertJsonPath('status', Document::STATUS_PROCESSING);

        $this->assertSame(Document::STATUS_PROCESSING, $document->fresh()->status);
        Queue::assertPushed(IndexDocument::class);
    }

    /** Layar Documents menanyakan status lewat endpoint ini sampai selesai. */
    public function test_status_dokumen_bisa_ditanyakan_untuk_polling(): void
    {
        $this->postJson('/eva/api/documents', [
            'name' => 'SOP Manual', 'extension' => 'TXT', 'extracted_text' => 'Isi SOP.',
        ])->assertStatus(202);

        $document = Document::sole();

        $this->getJson("/eva/api/documents/{$document->id}")
            ->assertOk()
            ->assertJsonPath('id', $document->id)
            ->assertJsonPath('status', Document::STATUS_INDEXED);
    }

    /** Dokumen yang isinya kosong tetap gagal — dan alasannya ikut tercatat. */
    public function test_dokumen_tanpa_isi_gagal_dengan_alasan(): void
    {
        $document = Document::create([
            'name' => 'Kosong', 'extension' => 'TXT', 'extracted_text' => '   ',
            'status' => Document::STATUS_PROCESSING,
        ]);

        IndexDocument::dispatch($document->id);

        $document->refresh();
        $this->assertSame(Document::STATUS_FAILED, $document->status);
        $this->assertNotNull($document->failure_reason);
    }
}
