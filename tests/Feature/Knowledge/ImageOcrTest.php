<?php

namespace Tests\Feature\Knowledge;

use App\Models\Knowledge\Document;
use App\Models\User;
use App\Services\Knowledge\DocumentTextExtractor;
use App\Services\Knowledge\ImageTextReader;
use App\Services\Knowledge\OcrBinaries;
use App\Services\Knowledge\TesseractImageReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\ActsAsEvaAdmin;
use Tests\Concerns\BuildsOcrFixtures;
use Tests\TestCase;

/**
 * Membaca tulisan di dalam BERKAS GAMBAR.
 *
 * Foto surat edaran yang dijepret di ponsel adalah bentuk "dokumen" yang paling
 * banyak beredar di grup kerja, dan sebelumnya tidak ada satu pun jalan
 * memasukkannya ke Knowledge Base — unggahannya ditolak di gerbang validasi.
 *
 * Mesinnya bukan barang baru: Tesseract yang sama sudah dipakai membaca halaman
 * PDF pindaian sejak awal. Yang ditambahkan hanyalah jalan masuknya.
 *
 * Fixture-nya DIRENDER dari halaman PDF berlapis teks, jadi ia benar-benar
 * gambar sebuah halaman — bukan berkas yang diberi nama .png. Karena itu tes ini
 * butuh poppler untuk MEMBANGUN bahannya, walau fiturnya sendiri hanya butuh
 * Tesseract.
 */
final class ImageOcrTest extends TestCase
{
    use ActsAsEvaAdmin;
    use BuildsOcrFixtures;
    use RefreshDatabase;

    private ImageTextReader $reader;

    protected function setUp(): void
    {
        parent::setUp();

        $this->reader = $this->app->make(ImageTextReader::class);

        if (! $this->reader->isAvailable() || $this->binary('pdftoppm') === null) {
            $this->markTestSkipped(
                'Binari OCR belum terpasang: '.implode(', ', (new OcrBinaries((array) config('eva.ocr')))->missing())
                .'. Pasang dengan: brew install tesseract tesseract-lang poppler'
            );
        }

        User::factory()->create(['name' => 'Marcell Laforteza', 'email' => 'marcell.laforteza@adhi.co.id', 'nip' => '19870114001']);

        $this->actingAsEvaAdmin();
    }

    /** Gambar polos tanpa satu pun tulisan — bagan alur, tangkapan layar kosong. */
    private function blankPng(): string
    {
        $path = $this->tempPath('.png');

        $canvas = imagecreatetruecolor(600, 400);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
        imagepng($canvas, $path);
        imagedestroy($canvas);

        return $path;
    }

    // ---- pembacaannya ------------------------------------------------------

    public function test_tulisan_di_gambar_dibaca_lewat_ocr(): void
    {
        $png = $this->pageAsPng('SOP Aktivasi VPN', 'Hubungi Helpdesk untuk mengaktifkan VPN.');

        $text = $this->reader->read($png);

        $this->assertNotNull($text, 'gambar berisi tulisan harus terbaca');
        $this->assertStringContainsString('SOP Aktivasi VPN', $text);
        $this->assertStringContainsString('Helpdesk', $text);
    }

    /**
     * Gambar TANPA tulisan memulangkan null, bukan string kosong.
     *
     * Bedanya menentukan nasib dokumennya: null berakhir sebagai `failed`
     * dengan kalimat yang menyuruh admin mengetik isinya, sedangkan string
     * kosong lolos jadi artikel tanpa isi yang tampak berhasil diunggah lalu
     * tidak pernah menjawab apa pun.
     */
    public function test_gambar_tanpa_tulisan_memulangkan_null(): void
    {
        $this->assertNull($this->reader->read($this->blankPng()));
    }

    public function test_berkas_rusak_tidak_meledak(): void
    {
        $path = $this->tempPath('.png');
        file_put_contents($path, 'ini jelas bukan gambar');

        $this->assertNull($this->reader->read($path));
    }

    /**
     * Gambar hanya butuh Tesseract. Server tanpa poppler tetap boleh membaca
     * foto — mematikannya karena `pdftoppm` tidak ada berarti menolak
     * kemampuan yang sebenarnya utuh.
     */
    public function test_gambar_tetap_terbaca_walau_poppler_tidak_ada(): void
    {
        $config = array_merge((array) config('eva.ocr'), ['pdftoppm' => '/jalan/yang/tidak/ada/pdftoppm']);
        $reader = new TesseractImageReader(new OcrBinaries($config), $config);

        $this->assertTrue($reader->isAvailable());
        $this->assertFalse((new OcrBinaries($config))->allPresent(), 'poppler memang sengaja dibuat tidak lengkap');
    }

    public function test_binari_tesseract_tak_ditemukan_membuat_gambar_tak_terbaca(): void
    {
        $config = ['tesseract' => '/jalan/yang/tidak/ada/tesseract'];
        $reader = new TesseractImageReader(new OcrBinaries($config), $config);

        $this->assertFalse($reader->isAvailable());
        $this->assertNull($reader->read($this->pageAsPng('apa pun')));
        $this->assertFalse((new DocumentTextExtractor(null, $reader))->canRead('PNG'));
    }

    public function test_gambar_diumumkan_terbaca_saat_tesseract_terpasang(): void
    {
        $extractor = $this->app->make(DocumentTextExtractor::class);

        $this->assertTrue($extractor->canRead('PNG'));
        $this->assertTrue($extractor->canRead('JPG'));
        $this->assertTrue($extractor->canRead('JPEG'));
    }

    // ---- ujung ke ujung ----------------------------------------------------

    /** Foto diunggah TANPA teks ketik, dan tetap jadi artikel yang bisa dikutip EVA. */
    public function test_unggah_gambar_tanpa_teks_ketik(): void
    {
        Storage::fake('local');

        $png = $this->pageAsPng('SOP Ganti Kartu Akses', 'Ajukan penggantian kartu ke Bagian Umum.');

        $this->postJson('/eva/api/documents', [
            'file' => new UploadedFile($png, 'SOP Ganti Kartu Akses.png', 'image/png', null, true),
        ])->assertStatus(202);

        $document = Document::with('article')->sole();

        $this->assertSame('PNG', $document->extension);
        $this->assertStringContainsString('SOP Ganti Kartu Akses', $document->extracted_text);
        $this->assertTrue($document->isIndexed());
        $this->assertNotNull($document->article);
    }

    /**
     * Foto yang tidak ada tulisannya berakhir `failed` dengan alasan yang bisa
     * ditindaklanjuti — bukan dokumen yang tampak berhasil lalu diam.
     */
    public function test_gambar_tanpa_tulisan_gagal_dengan_alasan_yang_jelas(): void
    {
        Storage::fake('local');

        $this->postJson('/eva/api/documents', [
            'file' => new UploadedFile($this->blankPng(), 'bagan-alur.png', 'image/png', null, true),
        ])->assertStatus(202);

        $document = Document::sole();

        $this->assertSame(Document::STATUS_FAILED, $document->status);
        $this->assertStringContainsString('salin teksnya', $document->failure_reason);
        // Berkasnya TETAP tersimpan: admin masih bisa membukanya untuk mengetik
        // isinya sendiri, dan gambar itu tetap bisa jadi rujukan setelahnya.
        $this->assertNotNull($document->storage_path);
    }
}
