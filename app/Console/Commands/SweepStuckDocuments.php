<?php

namespace App\Console\Commands;

use App\Models\Knowledge\Document;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Menutup dokumen yang menggantung `processing`.
 *
 * IndexDocument::failed() hanya jalan kalau job-nya SEMPAT dijalankan. Kalau
 * `queue:work` mati sebelum job diambil — kejadian paling lazim di lingkungan
 * ini — tidak ada satu pun kode yang menyentuh baris itu lagi. Statusnya diam
 * di `processing` selamanya: layar Documents terus menanyakannya, dan admin
 * menunggu sesuatu yang tak akan pernah datang. Diam yang paling mahal, karena
 * tidak terlihat seperti kegagalan.
 *
 * Ambang default 30 menit sengaja jauh di atas timeout job (900 detik), supaya
 * OCR panjang yang MASIH berjalan tidak ikut divonis gagal.
 *
 * Aman dijalankan berkali-kali: hanya menyentuh baris yang cocok, dan begitu
 * ditandai `failed` baris itu tak lagi masuk hitungan.
 */
class SweepStuckDocuments extends Command
{
    protected $signature = 'eva:sweep-stuck-documents';

    protected $description = 'Tandai dokumen yang macet di status processing sebagai failed dengan alasan terbaca.';

    private const FAILURE_REASON = 'Proses indexing tidak selesai — antrean kemungkinan berhenti '
        .'(queue:work tidak jalan). Coba indeks ulang dokumennya.';

    public function handle(): int
    {
        $ambangMenit = (int) config('eva.stuck_after_minutes', 30);

        $macet = Document::query()
            ->where('status', Document::STATUS_PROCESSING)
            ->where('updated_at', '<', now()->subMinutes($ambangMenit))
            ->get();

        if ($macet->isEmpty()) {
            $this->info('Tidak ada dokumen yang macet.');

            return self::SUCCESS;
        }

        foreach ($macet as $document) {
            $document->update([
                'status' => Document::STATUS_FAILED,
                'failure_reason' => self::FAILURE_REASON,
            ]);
        }

        // Dicatat karena ini gejala infrastruktur, bukan gejala isi berkas:
        // satu baris di sini berarti worker perlu diperiksa, bukan dokumennya.
        Log::warning('Dokumen macet di processing ditutup paksa', [
            'jumlah' => $macet->count(),
            'ambang_menit' => $ambangMenit,
            'document_ids' => $macet->pluck('id')->all(),
        ]);

        $this->warn(sprintf(
            '%d dokumen macet lebih dari %d menit ditandai gagal. Periksa apakah queue:work berjalan.',
            $macet->count(),
            $ambangMenit,
        ));

        return self::SUCCESS;
    }
}
