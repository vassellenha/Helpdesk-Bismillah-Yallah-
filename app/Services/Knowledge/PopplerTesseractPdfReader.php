<?php

namespace App\Services\Knowledge;

use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

/**
 * Membaca PDF dengan poppler + Tesseract, seluruhnya di server sendiri.
 *
 * Halaman diperiksa SATU PER SATU, dan OCR hanya dijalankan pada halaman yang
 * lapisan teksnya kosong. Alasannya bukan sekadar kecepatan: SOP nyata sering
 * campuran — beberapa halaman hasil ekspor Word, sisanya lembar tanda tangan
 * yang dipindai. Meng-OCR halaman yang sudah punya teks justru MENURUNKAN
 * kualitas, karena hasil render + tebak-karakter tidak pernah lebih baik
 * daripada teks aslinya.
 *
 * Semua langkahnya memakai binari yang sudah ikut poppler/tesseract, jadi tidak
 * ada satu pun paket composer baru — batasan repo tim tetap aman.
 */
final class PopplerTesseractPdfReader implements PdfTextReader
{
    /**
     * Jumlah karakter minimum agar lapisan teks sebuah halaman dipercaya.
     *
     * Di bawah ini halaman dianggap hasil pindai. Ambangnya sengaja rendah:
     * salah menduga "pindai" hanya membuat halaman itu di-OCR (lebih lambat,
     * hasil setara), sedangkan salah menduga "ada teks" membuat halaman
     * pindai masuk sebagai isi kosong tanpa gejala apa pun.
     */
    private const MIN_TEXT_LAYER_CHARS = 40;

    public function __construct(
        private readonly OcrBinaries $binaries,
        /** @var array<string,mixed> Bagian `ocr` dari config/eva.php. */
        private readonly array $config,
    ) {}

    public function isAvailable(): bool
    {
        return $this->binaries->allPresent();
    }

    public function read(string $pdfPath): ?string
    {
        if (! $this->isAvailable()) {
            Log::warning('EVA: PDF tidak dibaca, binari OCR belum lengkap.', [
                'missing' => $this->binaries->missing(),
            ]);

            return null;
        }

        $pageCount = $this->pageCount($pdfPath);

        if ($pageCount === 0) {
            return null;
        }

        $limit = min($pageCount, (int) ($this->config['max_pages'] ?? 30));
        $pages = [];

        for ($page = 1; $page <= $limit; $page++) {
            $text = trim((string) $this->textLayer($pdfPath, $page));

            if (mb_strlen($text) < self::MIN_TEXT_LAYER_CHARS) {
                $text = trim((string) $this->ocrPage($pdfPath, $page));
            }

            if ($text !== '') {
                $pages[] = $text;
            }
        }

        return $pages === [] ? null : implode("\n\n", $pages);
    }

    private function pageCount(string $pdfPath): int
    {
        $output = $this->run([$this->binaries->path('pdfinfo'), $pdfPath]);

        if ($output === null || preg_match('~^Pages:\s*(\d+)~m', $output, $m) !== 1) {
            return 0;
        }

        return (int) $m[1];
    }

    /** Teks asli halaman, bila PDF-nya lahir digital. */
    private function textLayer(string $pdfPath, int $page): ?string
    {
        return $this->run([
            $this->binaries->path('pdftotext'),
            '-layout', '-f', (string) $page, '-l', (string) $page,
            $pdfPath, '-',
        ]);
    }

    /**
     * Render satu halaman jadi gambar, lalu bacakan ke Tesseract.
     *
     * Gambar sementara ditulis ke direktori temp lalu SELALU dihapus, termasuk
     * saat prosesnya gagal — halaman SOP yang tertinggal di /tmp adalah
     * kebocoran isi dokumen internal, bukan sekadar sampah berkas.
     */
    private function ocrPage(string $pdfPath, int $page): ?string
    {
        $prefix = tempnam(sys_get_temp_dir(), 'eva-ocr');

        if ($prefix === false) {
            return null;
        }

        $image = $prefix.'.png';

        try {
            $rendered = $this->run([
                $this->binaries->path('pdftoppm'),
                '-r', (string) ($this->config['dpi'] ?? 300),
                '-f', (string) $page, '-l', (string) $page,
                '-png', '-singlefile',
                $pdfPath, $prefix,
            ]);

            if ($rendered === null || ! is_file($image)) {
                return null;
            }

            return $this->run([
                $this->binaries->path('tesseract'),
                $image, 'stdout',
                '-l', (string) ($this->config['languages'] ?? 'ind+eng'),
            ]);
        } finally {
            @unlink($image);
            @unlink($prefix);
        }
    }

    /**
     * @param  array<int,string|null>  $command
     * @return string|null null bila gagal atau melewati batas waktu.
     */
    private function run(array $command): ?string
    {
        $process = new Process(array_map('strval', $command));
        $process->setTimeout((float) ($this->config['timeout'] ?? 120));

        try {
            $process->run();
        } catch (ProcessTimedOutException) {
            // Satu berkas rusak tidak boleh menggantung antrean selamanya.
            Log::warning('EVA: proses OCR melewati batas waktu.', ['command' => $command[0]]);

            return null;
        }

        if (! $process->isSuccessful()) {
            Log::warning('EVA: proses OCR gagal.', [
                'command' => $command[0],
                'error' => mb_substr($process->getErrorOutput(), 0, 500),
            ]);

            return null;
        }

        return $process->getOutput();
    }
}
