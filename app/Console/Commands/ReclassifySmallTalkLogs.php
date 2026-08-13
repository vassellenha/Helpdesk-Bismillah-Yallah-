<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Knowledge\AnswerLog;
use App\Services\Knowledge\SmallTalkDetector;
use Illuminate\Console\Command;

/**
 * Memindahkan sapaan lama keluar dari Unanswered Questions.
 *
 * SmallTalkDetector hanya berlaku untuk pertanyaan yang masuk SETELAH ia
 * dipasang. Baris yang terlanjur tercatat sebagai `no_answer` — "Halo",
 * "terima kasih", "tes" — tetap duduk di daftar kerja admin sebagai celah
 * materi yang mustahil ditutup, karena tidak ada artikel yang menjawab "Halo".
 *
 * Dijalankan sebagai perintah, bukan migrasi, karena daftar frasanya akan
 * tumbuh: setiap kali pola baru ditambahkan, perintah ini bisa dijalankan lagi
 * untuk menyapu sisa yang dulu belum tertangkap. Migrasi hanya jalan sekali.
 *
 * Bawaannya HANYA melihat dan melaporkan. Perubahan baru terjadi dengan
 * --apply, supaya isi tabel tidak berubah karena seseorang penasaran.
 */
class ReclassifySmallTalkLogs extends Command
{
    protected $signature = 'eva:reclassify-small-talk {--apply : Simpan perubahannya, bukan sekadar melihat}';

    protected $description = 'Memindahkan sapaan lama dari Unanswered Questions ke outcome small_talk';

    public function handle(SmallTalkDetector $detector): int
    {
        $matches = AnswerLog::query()
            ->unanswered()
            ->get(['id', 'question', 'outcome'])
            ->filter(fn (AnswerLog $log) => $detector->balasan($log->question) !== null);

        if ($matches->isEmpty()) {
            $this->info('Tidak ada sapaan yang tersangkut di Unanswered Questions.');

            return self::SUCCESS;
        }

        $this->table(
            ['Pertanyaan', 'Outcome sekarang'],
            $matches->groupBy('question')
                ->map(fn ($rows, $question) => [$question.' ('.$rows->count().'x)', $rows->first()->outcome])
                ->values()
                ->all(),
        );

        if (! $this->option('apply')) {
            $this->warn($matches->count().' baris akan dipindahkan ke small_talk.');
            $this->line('Jalankan ulang dengan --apply untuk menyimpannya.');

            return self::SUCCESS;
        }

        AnswerLog::whereIn('id', $matches->pluck('id'))
            ->update(['outcome' => AnswerLog::OUTCOME_SMALL_TALK]);

        $this->info($matches->count().' baris dipindahkan ke small_talk dan tidak lagi muncul di Unanswered Questions.');

        return self::SUCCESS;
    }
}
