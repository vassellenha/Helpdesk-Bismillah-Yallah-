<?php

namespace App\Services\Knowledge;

/**
 * Membaca isi PDF jadi teks.
 *
 * Seam yang sama seperti KnowledgeSearch dan SubjectSearch: pemanggil hanya
 * tahu antarmuka ini, sehingga mesin di belakangnya bisa ditukar tanpa
 * menyentuh DocumentTextExtractor. Implementasi hari ini memakai poppler +
 * Tesseract on-premise (isi SOP tidak boleh keluar jaringan ADHI); kalau kelak
 * dipilih mesin lain, yang berubah cuma binding di AppServiceProvider.
 */
interface PdfTextReader
{
    /**
     * Apakah mesinnya siap dipakai di lingkungan ini.
     *
     * Dipakai untuk memberi tahu layar Documents apakah PDF boleh diunggah
     * tanpa teks tempel — jangan menjanjikan pembacaan otomatis di server yang
     * binarinya belum dipasang.
     */
    public function isAvailable(): bool;

    /**
     * @return string|null null berarti TIDAK TERBACA, bukan "isinya kosong".
     *                     Pembedaan itu yang mencegah dokumen kosong lolos jadi
     *                     artikel tanpa isi yang tampak berhasil diunggah.
     */
    public function read(string $pdfPath): ?string;
}
