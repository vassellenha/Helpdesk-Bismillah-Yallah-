<?php

namespace App\Services\Knowledge;

use Illuminate\Support\Facades\Log;

/**
 * Membaca tulisan di dalam gambar dengan Tesseract, di server sendiri.
 *
 * Jalur ini bukan barang baru: begitulah EVA membaca PDF hasil pindai sejak
 * awal — halaman dirender jadi PNG lalu diserahkan ke Tesseract (lihat
 * PopplerTesseractPdfReader::ocrPage). Untuk berkas yang MEMANG sudah gambar,
 * langkah render itu tinggal dilewati.
 *
 * Yang dibutuhkan hanya binari `tesseract`, TANPA poppler. Itu sebabnya kelas
 * ini tidak memakai OcrBinaries::allPresent(): server yang belum punya poppler
 * tetap boleh membaca foto, dan mematikannya karena `pdftoppm` tidak ada berarti
 * menolak kemampuan yang sebenarnya utuh.
 *
 * Bahasanya ind+eng, sama persis dengan OCR PDF. Surat edaran ADHI berbahasa
 * Indonesia yang dibaca dengan model Inggris menghasilkan teks yang lebih buruk
 * daripada tidak dibaca sama sekali — ia tetap "berhasil", hanya isinya salah.
 */
final class TesseractImageReader implements ImageTextReader
{
    public function __construct(
        private readonly OcrBinaries $binaries,
        /** @var array<string,mixed> Bagian `ocr` dari config/eva.php. */
        private readonly array $config,
    ) {}

    public function isAvailable(): bool
    {
        return $this->binaries->path('tesseract') !== null;
    }

    public function read(string $imagePath): ?string
    {
        if (! $this->isAvailable()) {
            Log::warning('EVA: gambar tidak dibaca, binari tesseract belum terpasang.');

            return null;
        }

        $process = new OcrProcess((float) ($this->config['timeout'] ?? 120));

        $text = $process->run([
            $this->binaries->path('tesseract'),
            $imagePath, 'stdout',
            '-l', (string) ($this->config['languages'] ?? 'ind+eng'),
        ]);

        $text = $text === null ? null : trim($text);

        /*
         | Gambar TANPA tulisan (bagan alur, tangkapan layar polos) sampai di
         | sini sebagai string kosong, dan itu harus jadi null.
         |
         | Bedanya nyata bagi admin: null berakhir sebagai dokumen `failed`
         | dengan kalimat yang menyuruhnya mengetik isinya sendiri, sedangkan
         | string kosong lolos ke DocumentIndexer dan melahirkan artikel tanpa
         | isi yang tampak berhasil diunggah — lalu tidak pernah menjawab apa pun.
         */
        return ($text === null || $text === '') ? null : $text;
    }
}
