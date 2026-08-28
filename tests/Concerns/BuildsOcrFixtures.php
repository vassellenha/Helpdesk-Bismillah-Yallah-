<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Services\Knowledge\OcrBinaries;
use Symfony\Component\Process\Process;

/**
 * Membangun berkas uji untuk jalur OCR — PDF dan gambar.
 *
 * Berkasnya DIBANGUN di dalam tes, bukan disimpan sebagai fixture biner. Dua
 * alasannya nyata: berkas biner di repo tidak bisa dibaca saat ditinjau (tidak
 * ada yang tahu isinya benar-benar apa), dan yang diuji di sini justru
 * perbedaan halus antar bentuk berkas — halaman berlapis teks versus halaman
 * yang isinya cuma gambar. Perbedaan itu harus bisa DIPERIKSA, bukan dipercaya.
 *
 * Berdiri sendiri, dipakai PdfOcrTest maupun ImageOcrTest: keduanya berangkat
 * dari halaman yang sama, hanya berbeda di kemasan akhirnya.
 */
trait BuildsOcrFixtures
{
    /** Berkas sementara yang selalu dibersihkan saat tes selesai. */
    protected function tempPath(string $suffix): string
    {
        $path = tempnam(sys_get_temp_dir(), 'eva-uji').$suffix;
        $this->beforeApplicationDestroyed(fn () => @unlink($path));

        return $path;
    }

    protected function binary(string $name): ?string
    {
        return (new OcrBinaries((array) config('eva.ocr')))->path($name);
    }

    /** PDF sederhana yang sah, dengan lapisan teks sungguhan. */
    protected function textLayerPdf(string ...$lines): string
    {
        $content = 'BT /F1 18 Tf 72 700 Td';
        foreach ($lines as $index => $line) {
            $content .= ($index === 0 ? '' : ' 0 -28 Td').' ('.$line.') Tj';
        }
        $content .= ' ET';

        return $this->assemblePdf([
            1 => '<</Type/Catalog/Pages 2 0 R>>',
            2 => '<</Type/Pages/Kids[3 0 R]/Count 1>>',
            3 => '<</Type/Page/Parent 2 0 R/MediaBox[0 0 612 792]/Contents 4 0 R'
                .'/Resources<</Font<</F1 5 0 R>>>>>>',
            4 => "<</Length {$this->len($content)}>> stream\n".$content."\nendstream",
            5 => '<</Type/Font/Subtype/Type1/BaseFont/Helvetica>>',
        ]);
    }

    /**
     * Halaman yang sama, dijadikan BERKAS GAMBAR — bentuk foto/hasil pindai
     * yang benar-benar diunggah admin, bukan PDF berisi gambar.
     *
     * Dirender 150 dpi: cukup tajam untuk Tesseract, dan tidak membuat tes
     * berjalan lama seperti 300 dpi yang dipakai di produksi.
     */
    protected function pageAsPng(string ...$lines): string
    {
        $prefix = $this->tempPath('-halaman');

        (new Process([
            $this->binary('pdftoppm'), '-png', '-r', '150', '-singlefile',
            $this->textLayerPdf(...$lines), $prefix,
        ]))->mustRun();

        $png = $prefix.'.png';
        $this->beforeApplicationDestroyed(fn () => @unlink($png));

        return $png;
    }

    /** @param array<int,string> $objects */
    protected function assemblePdf(array $objects): string
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

    /** Panjang dalam BYTE — /Length di PDF menghitung byte, bukan karakter. */
    protected function len(string $value): int
    {
        return strlen($value);
    }
}
