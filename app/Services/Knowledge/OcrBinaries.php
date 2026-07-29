<?php

namespace App\Services\Knowledge;

use Symfony\Component\Process\ExecutableFinder;

/**
 * Menemukan binari poppler & Tesseract.
 *
 * Kelas ini ada karena satu jebakan nyata yang sudah terbukti di mesin dev:
 * PHP TIDAK melihat /opt/homebrew/bin lewat PATH-nya, walau di terminal
 * `tesseract` jalan normal. Memanggil binari dengan nama telanjang berarti
 * fitur ini "jalan waktu dicoba di terminal, diam-diam gagal di aplikasi" —
 * dan hal yang sama umum di server, karena PHP-FPM biasanya berjalan dengan
 * PATH minimal.
 *
 * Urutan pencarian: path dari config/env lebih dulu (jawaban pasti untuk
 * server), lalu PATH, lalu direktori yang lazim dipakai pemasangan paket.
 */
final class OcrBinaries
{
    /** Direktori yang lazim, untuk lingkungan yang PATH-nya minim. */
    private const FALLBACK_DIRS = ['/opt/homebrew/bin', '/usr/local/bin', '/usr/bin', '/bin'];

    public const REQUIRED = ['pdfinfo', 'pdftotext', 'pdftoppm', 'tesseract'];

    /** @var array<string,string|null> Hasil pencarian, supaya tidak diulang tiap halaman. */
    private array $resolved = [];

    /** @param array<string,mixed> $config Bagian `ocr` dari config/eva.php. */
    public function __construct(private readonly array $config) {}

    public function path(string $name): ?string
    {
        return $this->resolved[$name] ??= $this->locate($name);
    }

    /** Semua binari yang dibutuhkan tersedia — kalau tidak, OCR tidak ditawarkan sama sekali. */
    public function allPresent(): bool
    {
        foreach (self::REQUIRED as $binary) {
            if ($this->path($binary) === null) {
                return false;
            }
        }

        return true;
    }

    /** @return string[] Nama binari yang belum ketemu — untuk pesan yang bisa ditindaklanjuti. */
    public function missing(): array
    {
        return array_values(array_filter(
            self::REQUIRED,
            fn (string $binary) => $this->path($binary) === null,
        ));
    }

    private function locate(string $name): ?string
    {
        $configured = $this->config[$name] ?? null;

        if (is_string($configured) && $configured !== '') {
            return is_executable($configured) ? $configured : null;
        }

        return (new ExecutableFinder)->find($name, null, self::FALLBACK_DIRS);
    }
}
