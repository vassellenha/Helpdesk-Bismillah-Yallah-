<?php

namespace App\Console\Commands;

use App\Models\Knowledge\AnswerLog;
use App\Models\Knowledge\Conversation;
use App\Support\Eva\LogRetention;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Menyapu log EVA yang sudah lewat masa simpan.
 *
 * Dua hal yang disapu, dan hanya dua:
 *   - Percakapan (kb_conversations) beserta turn-nya.
 *   - Pertanyaan TAK TERJAWAB (kb_answer_logs dengan outcome no_answer /
 *     ticket_draft).
 *
 * Yang SENGAJA tidak ikut disapu, betapapun tuanya:
 *
 *   Log yang TERJAWAB dan yang bertanya balik. Keduanya penyebut Analytics —
 *   `answerSummary()` menghitung SELURUH baris tanpa jendela waktu. Kalau log
 *   terjawab ikut dibuang sementara yang tak terjawab juga dibuang, angkanya
 *   masih bergerak tapi ke arah yang tak bisa ditebak. Dengan hanya membuang
 *   yang tak terjawab, arahnya jelas dan bisa dijelaskan: deflection naik.
 *
 *   Pertanyaan yang sudah di-dismiss (kb_dismissed_questions). Itu catatan
 *   KEPUTUSAN admin, bukan lalu lintas. Menghapusnya membuat pertanyaan yang
 *   pernah sengaja disembunyikan muncul lagi begitu ada yang menanyakannya.
 *
 * Konsekuensi yang harus diketahui sebelum menaikkan jadwalnya: Analytics dan
 * Coverage membaca kb_answer_logs secara langsung, tanpa batas tanggal. Begitu
 * baris tak terjawab yang lama hilang, angka masa lalu ikut berubah —
 * deflection percent naik, "unanswered" turun. Tren Coverage tidak ikut
 * bergeser karena sudah dipotret harian ke kb_coverage_snapshots oleh
 * eva:snapshot-coverage; itulah sebabnya perintah ini dijadwalkan SETELAH
 * snapshot harian, bukan sebelumnya.
 *
 * Aman dijalankan berkali-kali: yang sudah terhapus tidak muncul lagi di
 * pencarian berikutnya.
 */
class PurgeExpiredLogs extends Command
{
    protected $signature = 'eva:purge-expired-logs';

    protected $description = 'Hapus percakapan dan pertanyaan tak terjawab EVA yang sudah lewat masa simpan.';

    public function handle(): int
    {
        // Ambang diambil dari LogRetention, bukan dihitung ulang di sini:
        // hitung mundur di layar memakai kelas yang sama, dan keduanya harus
        // menunjuk tanggal yang persis sama.
        $hari = LogRetention::days();
        $batas = LogRetention::cutoff();

        // Turn ikut terhapus lewat cascadeOnDelete di kb_conversation_turns,
        // bukan lewat penghapusan manual di sini. Answer log yang menunjuk ke
        // percakapan ini TIDAK ikut terhapus — kolomnya nullOnDelete — dan itu
        // memang yang diinginkan: riwayat jawabannya tetap terhitung di
        // Analytics meski jejak percakapannya sudah dibuang.
        $percakapan = Conversation::query()
            ->where('started_at', '<', $batas)
            ->delete();

        $takTerjawab = AnswerLog::query()
            ->unanswered()
            ->where('created_at', '<', $batas)
            ->delete();

        if ($percakapan === 0 && $takTerjawab === 0) {
            $this->info(sprintf('Tidak ada log EVA yang lewat masa simpan %d hari.', $hari));

            return self::SUCCESS;
        }

        // Dicatat karena ini penghapusan permanen: kalau suatu saat ada yang
        // bertanya "ke mana pertanyaan minggu lalu", jawabannya harus ada di
        // log, bukan dalam tebakan.
        Log::info('Log EVA lewat masa simpan disapu', [
            'masa_simpan_hari' => $hari,
            'batas' => $batas->toDateTimeString(),
            'percakapan_dihapus' => $percakapan,
            'pertanyaan_tak_terjawab_dihapus' => $takTerjawab,
        ]);

        $this->info(sprintf(
            '%d percakapan dan %d pertanyaan tak terjawab yang lebih tua dari %d hari dihapus.',
            $percakapan,
            $takTerjawab,
            $hari,
        ));

        return self::SUCCESS;
    }
}
