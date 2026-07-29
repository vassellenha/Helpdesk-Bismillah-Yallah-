<?php

namespace Tests\Feature\Eva;

use App\Models\Knowledge\Document;
use App\Models\User;
use App\Services\Knowledge\PdfTextReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\ActsAsEvaAdmin;
use Tests\TestCase;
use ZipArchive;

/**
 * Unggah berkas di layar Documents.
 *
 * Sebelum ini layar "unggah" sebenarnya cuma form tempel-teks: dropdown
 * PDF/DOCX/… hanya LABEL, dan tak ada satu pun berkas yang benar-benar
 * tersimpan. Kolom `original_filename`, `storage_path`, dan `size_bytes` sudah
 * ada di skema sejak awal tapi selalu kosong.
 *
 * Dua hal yang dijaga di sini:
 *  1. Berkas aslinya BENAR-BENAR tersimpan — supaya kelak, saat ekstraksi PDF
 *     disetujui, dokumen lama bisa dibaca ulang tanpa mengunggah lagi.
 *  2. Format yang belum terbaca (PDF/XLSX) DITOLAK dengan alasan yang jelas,
 *     bukan diterima jadi dokumen kosong yang tampak berhasil.
 */
final class DocumentUploadTest extends TestCase
{
    use ActsAsEvaAdmin;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        User::factory()->create(['name' => 'Marcell Laforteza', 'email' => 'marcell.laforteza@adhi.co.id']);

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

    public function test_unggah_txt_menyimpan_berkas_dan_membaca_isinya(): void
    {
        $file = UploadedFile::fake()->createWithContent(
            'panduan-vpn.txt',
            'FortiClient adalah aplikasi VPN resmi untuk mengakses jaringan korporat.',
        );

        $this->postJson('/eva/api/documents', ['file' => $file])->assertStatus(202);

        $document = Document::sole();
        $this->assertSame('panduan-vpn', $document->name, 'nama dokumen diambil dari nama berkas');
        $this->assertSame('TXT', $document->extension);
        $this->assertSame('panduan-vpn.txt', $document->original_filename);
        $this->assertStringContainsString('FortiClient', $document->extracted_text);
        $this->assertGreaterThan(0, $document->size_bytes);

        Storage::disk('local')->assertExists($document->storage_path);
    }

    /** Isi DOCX terbaca tanpa satu pun paket composer baru. */
    public function test_unggah_docx_mengekstrak_teksnya(): void
    {
        $this->postJson('/eva/api/documents', [
            'file' => $this->docxFile('Akun SAP terkunci setelah lima kali gagal login.'),
        ])->assertStatus(202);

        $this->assertSame(
            'Akun SAP terkunci setelah lima kali gagal login.',
            Document::sole()->extracted_text,
        );
    }

    /** Berkas terbaca langsung melahirkan artikel — jalur dokumen → artikel utuh. */
    public function test_berkas_terbaca_langsung_melahirkan_artikel(): void
    {
        $this->postJson('/eva/api/documents', [
            'file' => $this->docxFile('Langkah membuka akun SAP yang terkunci.'),
        ])->assertStatus(202);

        $document = Document::with('article')->sole();
        $this->assertTrue($document->isIndexed());
        $this->assertNotNull($document->article);
    }

    /**
     * Di server yang binari OCR-nya BELUM TERPASANG, PDF tanpa teks tempel
     * ditolak dengan alasan — bukan diterima jadi dokumen kosong yang terlihat
     * berhasil diunggah lalu diam-diam gagal di antrean.
     *
     * Ketiadaan mesin sudah diketahui tanpa membuka berkasnya, jadi jawabannya
     * masih bisa datang seketika. Mesinnya dipalsukan supaya hasil tes tidak
     * bergantung apakah tesseract terpasang di mesin yang menjalankannya.
     *
     * PDF yang mesinnya ADA tapi tetap gagal dibaca (pindaian buram, berkas
     * rusak) berakhir `failed` berikut alasannya — diuji di DocumentQueueTest,
     * karena jawabannya baru datang setelah request selesai.
     */
    public function test_pdf_ditolak_saat_mesin_ocr_belum_terpasang(): void
    {
        $this->app->instance(PdfTextReader::class, new class implements PdfTextReader
        {
            public function isAvailable(): bool
            {
                return false;
            }

            public function read(string $pdfPath): ?string
            {
                return null;
            }
        });

        $this->postJson('/eva/api/documents', [
            'file' => UploadedFile::fake()->create('sop.pdf', 12, 'application/pdf'),
        ])->assertStatus(422)->assertJsonValidationErrors('extracted_text');

        $this->assertSame(0, Document::count());
    }

    /**
     * Teks tempel tetap diterima sebagai jalan keluar untuk PDF yang OCR-nya
     * pun gagal membaca — pindaian buram, tulisan tangan, halaman miring.
     */
    public function test_pdf_diterima_bila_teksnya_ditempel(): void
    {
        $this->postJson('/eva/api/documents', [
            'file' => UploadedFile::fake()->create('sop-printer.pdf', 12, 'application/pdf'),
            'extracted_text' => 'Printer jaringan yang offline biasanya masih hidup.',
        ])->assertStatus(202);

        $document = Document::sole();
        $this->assertSame('PDF', $document->extension);
        $this->assertStringContainsString('Printer jaringan', $document->extracted_text);
        Storage::disk('local')->assertExists($document->storage_path);
    }

    /** Jalur lama — tempel teks tanpa berkas — tetap berjalan. */
    public function test_tempel_teks_tanpa_berkas_tetap_bisa(): void
    {
        $this->postJson('/eva/api/documents', [
            'name' => 'SOP Manual',
            'extension' => 'TXT',
            'extracted_text' => 'Isi SOP yang diketik langsung.',
        ])->assertStatus(202);

        $document = Document::sole();
        $this->assertNull($document->storage_path);
        $this->assertSame('SOP Manual', $document->name);
    }

    public function test_tanpa_berkas_dan_tanpa_teks_ditolak(): void
    {
        $this->postJson('/eva/api/documents', ['name' => 'Kosong'])->assertStatus(422);

        $this->assertSame(0, Document::count());
    }

    public function test_ekstensi_di_luar_daftar_ditolak(): void
    {
        $this->postJson('/eva/api/documents', [
            'file' => UploadedFile::fake()->create('jahat.exe', 4),
        ])->assertStatus(422)->assertJsonValidationErrors('file');

        $this->assertSame(0, Document::count());
    }

    /**
     * Nama berkas kiriman klien TIDAK PERNAH dipakai sebagai lokasi simpan.
     * Nama seperti "../../.env" akan menulis ke luar direktori yang dituju.
     */
    public function test_nama_berkas_kiriman_tidak_dipakai_sebagai_lokasi_simpan(): void
    {
        $file = UploadedFile::fake()->createWithContent('../../rahasia.txt', 'isi apa pun');

        $this->postJson('/eva/api/documents', ['file' => $file])->assertStatus(202);

        $path = Document::sole()->storage_path;
        $this->assertStringNotContainsString('..', $path);
        $this->assertStringNotContainsString('rahasia', $path);
        Storage::disk('local')->assertExists($path);
    }
}
