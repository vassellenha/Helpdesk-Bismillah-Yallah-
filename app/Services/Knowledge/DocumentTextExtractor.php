<?php

namespace App\Services\Knowledge;

use ZipArchive;

/**
 * Membaca isi berkas yang diunggah menjadi teks — TANPA dependensi composer
 * baru (aturan repo: jangan menambah paket ke composer.json tim tanpa izin).
 *
 * TXT/MD dibaca apa adanya. DOCX bisa dibaca karena format aslinya memang
 * arsip ZIP berisi `word/document.xml`; ZipArchive sudah bagian dari PHP, jadi
 * ini membaca format pada bentuk sebenarnya, bukan menebak-nebak.
 *
 * PDF dibaca lewat PdfTextReader (poppler + Tesseract on-premise): lapisan
 * teks dulu, OCR hanya untuk halaman yang dipindai. Ternyata smalot/pdfparser
 * tidak dibutuhkan sama sekali — `pdftotext` sudah ikut poppler yang memang
 * harus dipasang demi OCR.
 *
 * GAMBAR (PNG/JPG) dibaca lewat ImageTextReader — Tesseract yang sama, tanpa
 * poppler. Foto surat edaran yang dijepret di ponsel adalah bentuk paling
 * umum "dokumen" yang beredar di grup kerja, dan sebelumnya tidak ada satu pun
 * jalan memasukkannya ke Knowledge Base.
 *
 * SVG SENGAJA TIDAK ikut. Ia bukan gambar raster melainkan markup yang bisa
 * memuat skrip, dan berkas rujukan disajikan inline ke layar setiap karyawan.
 *
 * XLSX tetap mengembalikan null. Bisa saja dibongkar seperti DOCX, tapi
 * deretan nilai sel menghasilkan artikel yang buruk. Biarkan sampai ada
 * kebutuhan nyata.
 *
 * null berarti "belum bisa dibaca", BUKAN "kosong". Pemanggil wajib
 * membedakannya: string kosong akan lolos ke DocumentIndexer dan melahirkan
 * artikel tanpa isi yang tampak berhasil diunggah.
 */
final class DocumentTextExtractor
{
    /** Format yang selalu terbaca, tanpa bergantung binari apa pun. */
    private const ALWAYS_READABLE = ['TXT', 'MD', 'DOCX'];

    /**
     * Gambar raster yang boleh di-OCR.
     *
     * Publik karena dua tempat lain menurunkan perilakunya dari daftar ini:
     * DocumentController (format yang boleh diunggah) dan SourceDocument
     * (rujukan yang bisa ditampilkan apa adanya di popup). Ditulis ulang di
     * sana, cepat atau lambat salah satunya melenceng — dan gejalanya adalah
     * berkas yang bisa diunggah tapi tidak bisa dibuka lagi.
     */
    public const IMAGE_EXTENSIONS = ['PNG', 'JPG', 'JPEG'];

    /**
     * Batas ukuran document.xml setelah dibuka.
     *
     * ZIP kecil bisa mengembang jadi gigabytes (zip bomb). Berkas diunggah
     * admin, tapi batas ini murah dan menutup satu-satunya jalan berkas
     * mengendalikan pemakaian memori server.
     */
    private const MAX_UNCOMPRESSED_BYTES = 32 * 1024 * 1024;

    /**
     * PdfTextReader sengaja OPSIONAL. Kalau binari OCR belum terpasang di
     * lingkungan ini, PDF otomatis kembali jadi "belum terbaca" — dan layar
     * Documents ikut berhenti menjanjikannya, karena daftar format terbaca
     * diturunkan dari canRead() yang sama.
     */
    public function __construct(
        private readonly ?PdfTextReader $pdf = null,
        private readonly ?ImageTextReader $image = null,
    ) {}

    public static function isImage(string $extension): bool
    {
        return in_array(mb_strtoupper($extension), self::IMAGE_EXTENSIONS, true);
    }

    public function canRead(string $extension): bool
    {
        $extension = mb_strtoupper($extension);

        if ($extension === 'PDF') {
            return $this->pdf?->isAvailable() ?? false;
        }

        if (self::isImage($extension)) {
            return $this->image?->isAvailable() ?? false;
        }

        return in_array($extension, self::ALWAYS_READABLE, true);
    }

    /**
     * Membaca berkas yang SUDAH TERSIMPAN, bukan UploadedFile.
     *
     * Perbedaan itu yang membuat pembacaan bisa dipindah ke antrean: berkas
     * unggahan sementara sudah lama dihapus saat job dijalankan, sedangkan
     * berkas di disk privat masih ada.
     *
     * @param  string  $path  Path absolut berkas tersimpan.
     * @param  string  $extension  Ekstensi yang SUDAH divalidasi controller —
     *                             sengaja tidak diambil dari nama berkas
     *                             kiriman klien.
     */
    public function extract(string $path, string $extension): ?string
    {
        $extension = mb_strtoupper($extension);

        $text = match (true) {
            in_array($extension, ['TXT', 'MD'], true) => $this->plainText($path),
            $extension === 'DOCX' => $this->docx($path),
            $extension === 'PDF' => $this->pdf?->read($path),
            self::isImage($extension) => $this->image?->read($path),
            default => null,
        };

        $text = $text === null ? null : trim($text);

        return ($text === null || $text === '') ? null : $text;
    }

    private function plainText(string $path): ?string
    {
        $contents = @file_get_contents($path);

        return $contents === false ? null : $contents;
    }

    private function docx(string $path): ?string
    {
        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            return null;
        }

        try {
            $entry = $zip->statName('word/document.xml');

            if ($entry === false || $entry['size'] > self::MAX_UNCOMPRESSED_BYTES) {
                return null;
            }

            $xml = $zip->getFromName('word/document.xml');
        } finally {
            $zip->close();
        }

        return $xml === false ? null : $this->xmlToText($xml);
    }

    /**
     * Mengubah WordprocessingML jadi teks polos.
     *
     * Batas paragraf (`</w:p>`) dan pemisah baris jadi baris baru lebih dulu;
     * kalau tag dibuang begitu saja, seluruh SOP menyatu jadi satu paragraf
     * raksasa yang tak berguna sebagai artikel. Sebaliknya batas antar `<w:r>`
     * TIDAK menghasilkan spasi: Word memecah satu kalimat jadi beberapa run
     * setiap kali formatnya berubah, jadi menyisipkan spasi di sana akan
     * mencacah kata di tengah.
     */
    private function xmlToText(string $xml): string
    {
        $withBreaks = preg_replace(
            ['~<w:br\s*/?>~i', '~<w:tab\s*/?>~i', '~</w:p>~i'],
            ["\n", "\t", "\n"],
            $xml,
        );

        $text = html_entity_decode(strip_tags($withBreaks), ENT_QUOTES | ENT_XML1, 'UTF-8');

        // Baris kosong beruntun dari markup Word dirapikan, tapi baris tunggal
        // dipertahankan — struktur langkah 1/2/3 adalah isi, bukan hiasan.
        $text = preg_replace('~[ \t]+~', ' ', $text);
        $text = preg_replace('~\n{3,}~', "\n\n", $text);

        return implode("\n", array_map('trim', explode("\n", $text)));
    }
}
