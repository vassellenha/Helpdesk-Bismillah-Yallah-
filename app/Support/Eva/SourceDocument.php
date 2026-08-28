<?php

declare(strict_types=1);

namespace App\Support\Eva;

use App\Models\Knowledge\Document;
use App\Services\Knowledge\DocumentTextExtractor;
use Illuminate\Support\Facades\Storage;

/**
 * Dokumen ASLI di balik sebuah kutipan EVA — berkas yang diunggah admin di
 * layar Documents, bukan artikel hasil ekstraksinya.
 *
 * Alasan keberadaannya: yang dipercaya karyawan adalah surat edaran atau SOP
 * bertanda tangan, bukan salinan teksnya. Artikel adalah turunan yang boleh
 * disunting admin; ketika seseorang menekan rujukan, yang ingin ia lihat adalah
 * dokumennya sendiri, lengkap dengan kop, tabel, dan tanda tangannya.
 *
 * Tiga keadaan yang WAJIB dibedakan, karena ketiganya nyata di data yang ada:
 *   - berkas ada dan bisa dipratinjau (PDF, gambar) → ditampilkan utuh di popup
 *   - berkas ada tapi tidak bisa dirender browser (DOCX) → ditawarkan diunduh
 *   - berkas tidak pernah ada (isinya diketik admin) → tinggal hasil bacaannya
 * Menyamakan ketiganya menghasilkan popup yang menjanjikan dokumen lalu
 * menampilkan bingkai kosong.
 */
final class SourceDocument
{
    /**
     * Dua permukaan menyajikan berkas yang sama dengan GERBANG yang berbeda,
     * dan bedanya bukan detail teknis:
     *
     *   ROUTE_ASSISTANT — dibuka karyawan dari kutipan jawaban. Hanya dokumen
     *                     yang artikelnya boleh dipakai EVA menjawab.
     *   ROUTE_CONSOLE   — dibuka admin di layar Documents. Termasuk dokumen
     *                     yang artikelnya masih draf atau yang indexing-nya
     *                     GAGAL — justru itulah yang paling perlu dilihat,
     *                     karena admin membukanya untuk mencari tahu kenapa.
     *
     * Bentuk datanya sengaja sama persis supaya satu penampil di layar melayani
     * keduanya. Yang berbeda hanya siapa yang boleh meminta byte-nya.
     */
    public const ROUTE_ASSISTANT = 'eva.assistant.document-file';

    public const ROUTE_CONSOLE = 'eva.documents.file';

    /**
     * Format yang bisa dirender browser apa adanya, beserta CARA merendernya.
     *
     * Bukan sekadar ya/tidak: PDF butuh bingkai (<iframe>) sedangkan gambar
     * butuh <img>. Memasang gambar di dalam iframe "jalan" di sebagian browser
     * dan menghasilkan halaman abu-abu berisi satu gambar mentah di sisanya,
     * jadi caranya ikut ditentukan di sini, bukan ditebak di layar.
     *
     * Sengaja daftar-putih, bukan daftar-hitam: format yang belum dikenal
     * diperlakukan sebagai "tidak bisa dipratinjau" dan tetap bisa diunduh —
     * jauh lebih baik daripada memasangnya di bingkai pratinjau dan berharap.
     *
     * DOCX TIDAK ada di sini dan tidak akan pernah bisa ditambahkan tanpa
     * mengubah berkasnya di server: tidak ada browser yang merendernya.
     */
    private const PREVIEW_MODES = ['PDF' => 'pdf'];

    /**
     * Bentuk yang dibaca popup rujukan.
     *
     * @return array<string, mixed>|null null bila materinya memang tidak lahir
     *                                   dari dokumen (FAQ, atau artikel yang
     *                                   dokumen sumbernya sudah dihapus)
     */
    public static function present(?Document $document, string $route = self::ROUTE_ASSISTANT): ?array
    {
        if ($document === null) {
            return null;
        }

        // Berkas yang tercatat di baris tapi hilang dari disk diperlakukan
        // sebagai tidak ada. Tanpa pemeriksaan ini popup memasang bingkai
        // pratinjau yang isinya halaman 404 — dan itu terbaca sebagai aplikasi
        // yang rusak, bukan sebagai berkas yang hilang.
        $hasFile = $document->hasFile()
            && Storage::disk('local')->exists((string) $document->storage_path);

        return [
            'id' => $document->id,
            'name' => $document->name,
            'filename' => self::filename($document),
            'extension' => $document->extension,
            'size_kb' => (int) round(($document->size_bytes ?? 0) / 1024),
            'page_count' => $document->page_count,
            'has_file' => $hasFile,
            'is_previewable' => $hasFile && self::previewMode($document->extension) !== null,
            // 'pdf' | 'image' | null — layar memilih bingkai atau gambar dari
            // sini, bukan dari menebak-nebak ekstensinya sendiri.
            'preview_as' => $hasFile ? self::previewMode($document->extension) : null,
            'file_url' => $hasFile
                ? route($route, ['document' => $document->id])
                : null,
            /*
             | Isi dokumen hasil pembacaan — bukan badan artikelnya.
             |
             | Bedanya penting justru saat admin sudah menyunting artikel:
             | teks di sini tetap apa yang tertulis di dokumen, dan itulah yang
             | dijanjikan tombol rujukan. Dipakai untuk format yang tidak bisa
             | dipratinjau, dan untuk dokumen yang berkasnya tidak tersimpan.
             */
            'text' => $document->extracted_text,
        ];
    }

    /**
     * Nama berkas yang dilihat karyawan saat mengunduh.
     *
     * `original_filename` bisa kosong pada dokumen yang dibuat dari teks, jadi
     * ada susunan cadangan dari nama dokumennya. Nama kiriman klien TIDAK
     * pernah menentukan lokasi baca/tulis apa pun — ia hanya label unduhan.
     */
    public static function filename(Document $document): string
    {
        if (filled($document->original_filename)) {
            return (string) $document->original_filename;
        }

        $extension = mb_strtolower((string) $document->extension);

        return $extension === ''
            ? $document->name
            : $document->name.'.'.$extension;
    }

    private static function previewMode(?string $extension): ?string
    {
        $extension = mb_strtoupper((string) $extension);

        if (DocumentTextExtractor::isImage($extension)) {
            return 'image';
        }

        return self::PREVIEW_MODES[$extension] ?? null;
    }
}
