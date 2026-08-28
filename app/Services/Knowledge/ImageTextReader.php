<?php

namespace App\Services\Knowledge;

/**
 * Membaca tulisan di dalam sebuah gambar jadi teks.
 *
 * Seam tersendiri, sejajar dengan PdfTextReader dan bukan bagian darinya:
 * keduanya boleh punya nasib berbeda di satu server. PDF menuntut poppler
 * LENGKAP (pdfinfo, pdftotext, pdftoppm) plus Tesseract, sedangkan gambar cukup
 * Tesseract — jadi ada server yang bisa membaca foto tapi tidak bisa membaca
 * PDF pindaian. Menggabungkan keduanya di satu antarmuka memaksa kedua fitur
 * mati bersama padahal salah satunya masih bisa jalan.
 */
interface ImageTextReader
{
    /**
     * Apakah mesinnya siap dipakai di lingkungan ini.
     *
     * Dipakai layar Documents untuk memutuskan apakah gambar boleh diunggah
     * tanpa teks ketik — jangan menjanjikan pembacaan otomatis di server yang
     * binarinya belum dipasang.
     */
    public function isAvailable(): bool;

    /**
     * @return string|null null berarti TIDAK TERBACA, bukan "tidak ada
     *                     tulisannya". Pembedaan itu yang mencegah gambar tanpa
     *                     hasil lolos jadi artikel kosong yang tampak berhasil.
     */
    public function read(string $imagePath): ?string;
}
