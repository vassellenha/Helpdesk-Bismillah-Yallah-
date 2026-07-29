<?php

namespace Tests\Feature\Knowledge;

use App\Models\Knowledge\Document;
use App\Services\Knowledge\DocumentTextExtractor;
use App\Services\Knowledge\OcrBinaries;
use App\Services\Knowledge\PdfTextReader;
use App\Services\Knowledge\PopplerTesseractPdfReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use Tests\Concerns\ActsAsEvaAdmin;
use Tests\TestCase;

/**
 * Membaca PDF: lapisan teks dulu, OCR hanya untuk halaman yang dipindai.
 *
 * Dua fixture dibangun di sini, bukan disimpan sebagai berkas biner:
 *  - PDF LAHIR DIGITAL — punya lapisan teks, dibaca `pdftotext`.
 *  - PDF HASIL PINDAI  — halaman yang sama dirender jadi JPEG lalu ditanam
 *                        sebagai gambar, sehingga lapisan teksnya BENAR-BENAR
 *                        kosong. Inilah bentuk SOP yang di-scan & ditandatangani.
 *
 * Membangunnya di dalam tes membuat perbedaan keduanya bisa diperiksa
 * (`assertSame('', lapisan teks pindai)`), bukan sekadar dipercaya.
 */
final class PdfOcrTest extends TestCase
{
    use ActsAsEvaAdmin;
    use RefreshDatabase;

    private PdfTextReader $reader;

    protected function setUp(): void
    {
        parent::setUp();

        $this->reader = $this->app->make(PdfTextReader::class);

        if (! $this->reader->isAvailable()) {
            $this->markTestSkipped(
                'Binari OCR belum terpasang: '.implode(', ', (new OcrBinaries((array) config('eva.ocr')))->missing())
                .'. Pasang dengan: brew install tesseract tesseract-lang poppler'
            );
        }

        $this->actingAsEvaAdmin();
    }

    // ---- fixture ----------------------------------------------------------

    private function tempPath(string $suffix): string
    {
        $path = tempnam(sys_get_temp_dir(), 'eva-uji').$suffix;
        $this->beforeApplicationDestroyed(fn () => @unlink($path));

        return $path;
    }

    /** PDF sederhana yang sah, dengan lapisan teks sungguhan. */
    private function textLayerPdf(string ...$lines): string
    {
        $content = 'BT /F1 18 Tf 72 700 Td';
        foreach ($lines as $index => $line) {
            $content .= ($index === 0 ? '' : ' 0 -28 Td').' ('.$line.') Tj';
        }
        $content .= ' ET';

        $objects = [
            1 => '<</Type/Catalog/Pages 2 0 R>>',
            2 => '<</Type/Pages/Kids[3 0 R]/Count 1>>',
            3 => '<</Type/Page/Parent 2 0 R/MediaBox[0 0 612 792]/Contents 4 0 R'
                .'/Resources<</Font<</F1 5 0 R>>>>>>',
            4 => "<</Length {$this->len($content)}>> stream\n".$content."\nendstream",
            5 => '<</Type/Font/Subtype/Type1/BaseFont/Helvetica>>',
        ];

        return $this->assemblePdf($objects);
    }

    /**
     * PDF hasil "pindai": halaman dirender jadi JPEG lalu ditanam sebagai
     * gambar. Tidak ada satu pun karakter yang bisa dipilih di dalamnya.
     */
    private function scannedPdf(string ...$lines): string
    {
        $source = $this->textLayerPdf(...$lines);
        $prefix = $this->tempPath('-render');

        (new Process([
            $this->binary('pdftoppm'), '-jpeg', '-r', '150', '-singlefile', $source, $prefix,
        ]))->mustRun();

        $jpegPath = $prefix.'.jpg';
        $this->beforeApplicationDestroyed(fn () => @unlink($jpegPath));

        $jpeg = file_get_contents($jpegPath);
        [$width, $height] = getimagesize($jpegPath);

        $content = "q {$width} 0 0 {$height} 0 0 cm /Im0 Do Q";

        return $this->assemblePdf([
            1 => '<</Type/Catalog/Pages 2 0 R>>',
            2 => '<</Type/Pages/Kids[3 0 R]/Count 1>>',
            3 => "<</Type/Page/Parent 2 0 R/MediaBox[0 0 {$width} {$height}]/Contents 4 0 R"
                .'/Resources<</XObject<</Im0 5 0 R>>>>>>',
            4 => "<</Length {$this->len($content)}>> stream\n".$content."\nendstream",
            5 => "<</Type/XObject/Subtype/Image/Width {$width}/Height {$height}/ColorSpace/DeviceRGB"
                ."/BitsPerComponent 8/Filter/DCTDecode/Length {$this->len($jpeg)}>> stream\n".$jpeg."\nendstream",
        ]);
    }

    /** @param array<int,string> $objects */
    private function assemblePdf(array $objects): string
    {
        $pdf = "%PDF-1.4\n";
        $offsets = [];

        foreach ($objects as $number => $body) {
            $offsets[$number] = $this->len($pdf);
            $pdf .= "{$number} 0 obj ".$body." endobj\n";
        }

        $xrefAt = $this->len($pdf);
        $size = count($objects) + 1;
        $pdf .= "xref\n0 {$size}\n0000000000 65535 f \n";

        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        $pdf .= "trailer <</Size {$size}/Root 1 0 R>>\nstartxref\n{$xrefAt}\n%%EOF\n";

        $path = $this->tempPath('.pdf');
        file_put_contents($path, $pdf);

        return $path;
    }

    private function len(string $value): int
    {
        return strlen($value);
    }

    private function binary(string $name): string
    {
        return (new OcrBinaries((array) config('eva.ocr')))->path($name);
    }

    private function textLayerOf(string $pdfPath): string
    {
        $process = new Process([$this->binary('pdftotext'), $pdfPath, '-']);
        $process->run();

        // pdftotext menutup tiap halaman dengan form-feed (\f), dan charlist
        // bawaan trim() TIDAK memuatnya — tanpa ini "halaman kosong" terbaca
        // sebagai satu karakter dan tesnya menuduh fixture-nya salah.
        return trim($process->getOutput(), " \t\n\r\0\x0B\f");
    }

    // ---- tes --------------------------------------------------------------

    public function test_pdf_lahir_digital_dibaca_dari_lapisan_teksnya(): void
    {
        $pdf = $this->textLayerPdf('SOP Reset Password SAP', 'Langkah 1. Buka portal SAP.');

        $text = $this->reader->read($pdf);

        $this->assertStringContainsString('SOP Reset Password SAP', $text);
        $this->assertStringContainsString('Langkah 1. Buka portal SAP.', $text);
    }

    /**
     * Inti fitur ini: halaman yang TIDAK punya karakter apa pun tetap terbaca.
     * Lapisan teksnya dipastikan kosong lebih dulu, supaya tes ini tidak
     * diam-diam lulus lewat jalur pdftotext.
     */
    public function test_pdf_hasil_pindai_dibaca_lewat_ocr(): void
    {
        $pdf = $this->scannedPdf('SOP Unlock Akun SAP', 'Akun terkunci setelah lima kali gagal.');

        $this->assertSame('', $this->textLayerOf($pdf), 'fixture harus benar-benar tanpa lapisan teks');

        $text = $this->reader->read($pdf);

        $this->assertNotNull($text, 'PDF pindai harus terbaca lewat OCR');
        $this->assertStringContainsString('SOP Unlock Akun SAP', $text);
        $this->assertStringContainsString('lima kali gagal', $text);
    }

    public function test_berkas_rusak_tidak_meledak(): void
    {
        $path = $this->tempPath('.pdf');
        file_put_contents($path, 'ini jelas bukan pdf');

        $this->assertNull($this->reader->read($path));
    }

    /**
     * Binari yang salah path membuat OCR MENOLAK bekerja, bukan melempar
     * exception — dan canRead('PDF') ikut jadi false, sehingga layar Documents
     * berhenti menjanjikan pembacaan otomatis.
     */
    public function test_binari_tak_ditemukan_membuat_pdf_tak_terbaca(): void
    {
        $config = ['tesseract' => '/jalan/yang/tidak/ada/tesseract'];
        $reader = new PopplerTesseractPdfReader(new OcrBinaries($config), $config);

        $this->assertFalse($reader->isAvailable());
        $this->assertNull($reader->read($this->textLayerPdf('apa pun')));
        $this->assertContains('tesseract', (new OcrBinaries($config))->missing());
        $this->assertFalse((new DocumentTextExtractor($reader))->canRead('PDF'));
    }

    public function test_pdf_diumumkan_terbaca_saat_binari_lengkap(): void
    {
        $this->assertTrue($this->app->make(DocumentTextExtractor::class)->canRead('PDF'));
    }

    /** Ujung ke ujung: PDF pindai diunggah TANPA teks tempel, dan tetap jadi artikel. */
    public function test_unggah_pdf_pindai_tanpa_teks_tempel(): void
    {
        Storage::fake('local');

        $pdf = $this->scannedPdf('SOP Aktivasi VPN', 'Hubungi Helpdesk untuk mengaktifkan VPN.');

        $this->postJson('/eva/api/documents', [
            'file' => new UploadedFile($pdf, 'SOP Aktivasi VPN.pdf', 'application/pdf', null, true),
        ])->assertStatus(202);

        $document = Document::with('article')->sole();

        $this->assertSame('PDF', $document->extension);
        $this->assertStringContainsString('SOP Aktivasi VPN', $document->extracted_text);
        $this->assertTrue($document->isIndexed());
        $this->assertNotNull($document->article);
    }
}
