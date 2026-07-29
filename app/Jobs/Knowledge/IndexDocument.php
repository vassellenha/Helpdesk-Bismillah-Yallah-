<?php

namespace App\Jobs\Knowledge;

use App\Models\Knowledge\Document;
use App\Services\Knowledge\DocumentIndexer;
use App\Services\Knowledge\DocumentTextExtractor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Membaca isi berkas lalu mengindeksnya — di luar request.
 *
 * Dulu seluruh jalur ini dikerjakan sinkron di DocumentController::store().
 * Untuk TXT/DOCX itu tak terasa, tapi PDF hasil pindai harus di-OCR halaman
 * demi halaman: satu berkas 30 halaman bisa menghabiskan menit, dan request
 * mati di tengah jalan tanpa meninggalkan jejak apa pun.
 *
 * EKSTRAKSI ikut pindah ke sini, bukan cuma indexing. Bagian yang mahal memang
 * ekstraksinya — memindahkan DocumentIndexer saja tidak mengubah apa-apa.
 *
 * Invarian: dokumen tidak boleh tertinggal `processing` selamanya. Setiap jalan
 * keluar dari job ini menetapkan `indexed` atau `failed`; baris yang macet di
 * `processing` tidak memunculkan error apa pun, ia hanya diam sementara admin
 * menunggu sesuatu yang tak akan pernah datang.
 */
class IndexDocument implements ShouldQueue
{
    use Queueable;

    /**
     * Sekali coba saja. Kegagalan di sini hampir selalu soal isi berkas
     * (pindaian buram, PDF rusak, binari belum terpasang) — mengulang OCR mahal
     * yang pasti gagal lagi cuma menahan antrean untuk dokumen berikutnya.
     */
    public int $tries = 1;

    /** Longgar di atas batas per-proses OCR (120 detik × beberapa halaman). */
    public int $timeout = 900;

    public function __construct(private readonly int $documentId) {}

    public function handle(DocumentTextExtractor $extractor, DocumentIndexer $indexer): void
    {
        $document = Document::find($this->documentId);

        if ($document === null) {
            return;
        }

        try {
            $text = $this->resolveText($document, $extractor);

            if (blank($text)) {
                $this->markFailed($document, $this->unreadableReason($document));

                return;
            }

            $document->update(['extracted_text' => $text, 'failure_reason' => null]);
            $indexer->index($document);
        } catch (Throwable $e) {
            // Ditelan SENGAJA supaya dokumennya sempat ditandai gagal — tapi
            // tidak diam-diam: rinciannya masuk log server, ringkasannya masuk
            // layar. Melempar ulang di sini hanya menukar baris `failed` yang
            // bisa dilihat admin dengan baris `processing` yang menggantung.
            Log::error('Indexing dokumen gagal', [
                'document_id' => $document->id,
                'exception' => $e,
            ]);

            $this->markFailed($document, $e->getMessage());
        }
    }

    /**
     * Teks tempel menang kalau berkasnya memang tidak bisa dibaca sendiri;
     * selebihnya isi berkas yang dipakai. Berkas dibaca dari DISK PRIVAT, bukan
     * dari unggahan sementara — saat job jalan, berkas sementara sudah lama
     * dihapus.
     */
    private function resolveText(Document $document, DocumentTextExtractor $extractor): ?string
    {
        if (filled($document->extracted_text)) {
            return trim($document->extracted_text);
        }

        if (blank($document->storage_path)) {
            return null;
        }

        $disk = Storage::disk('local');

        if (! $disk->exists($document->storage_path)) {
            return null;
        }

        return $extractor->extract($disk->path($document->storage_path), $document->extension);
    }

    /**
     * Kalimat yang dibaca admin di layar Documents. Ia menggantikan pesan 422
     * yang dulu muncul saat unggah — sekarang jawabannya datang setelah request
     * selesai, jadi alasannya harus ikut tersimpan.
     */
    private function unreadableReason(Document $document): string
    {
        if (blank($document->storage_path)) {
            return 'Dokumen ini tidak punya isi teks maupun berkas untuk dibaca.';
        }

        return sprintf(
            'Isi berkas %s tidak bisa dibaca otomatis. Buka dokumen ini, salin teksnya ke kolom isi, '
            .'lalu indeks ulang — berkas aslinya tetap tersimpan.',
            $document->extension,
        );
    }

    private function markFailed(Document $document, string $reason): void
    {
        $document->update([
            'status' => Document::STATUS_FAILED,
            'failure_reason' => mb_substr($reason, 0, 500),
            'indexed_at' => null,
        ]);
    }

    /**
     * Jaring terakhir untuk kegagalan yang tidak bisa ditangkap handle():
     * worker kehabisan waktu, proses dibunuh, koneksi putus.
     */
    public function failed(?Throwable $e): void
    {
        $document = Document::find($this->documentId);

        if ($document === null || $document->status !== Document::STATUS_PROCESSING) {
            return;
        }

        $this->markFailed($document, $e?->getMessage() ?? 'Proses indexing berhenti sebelum selesai.');
    }
}
