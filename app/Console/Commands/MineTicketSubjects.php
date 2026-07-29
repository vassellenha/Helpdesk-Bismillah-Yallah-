<?php

namespace App\Console\Commands;

use App\Services\Knowledge\CoverageCalculator;
use App\Services\Knowledge\TicketSubjectMiner;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * Daftar tugas menulis, diturunkan dari TIKET NYATA.
 *
 * Seluruh logika pemetaan tiket → subject katalog ada di TicketSubjectMiner,
 * bukan di sini. Perintah ini hanya menyajikannya sebagai tabel terminal.
 *
 * Daftar ini sempat ikut tampil sebagai kartu di Coverage Dashboard, lalu
 * dibuang atas keputusan pemilik. Terminal kembali jadi satu-satunya tempat
 * membacanya.
 */
class MineTicketSubjects extends Command
{
    protected $signature = 'eva:mine-ticket-subjects
        {--limit=20 : Berapa banyak subject teratas yang dicetak}
        {--all : Ikut menampilkan subject yang materinya sudah ada}';

    protected $description = 'Susun daftar tugas menulis materi EVA dari subject tiket yang paling sering muncul.';

    public function handle(TicketSubjectMiner $miner, CoverageCalculator $coverage): int
    {
        $hasil = $miner->tally();

        if ($hasil['catalogEmpty']) {
            $this->warn('Katalog layanan kosong — tidak ada yang bisa dipetakan.');

            return self::SUCCESS;
        }

        $this->render($hasil, $coverage);

        return self::SUCCESS;
    }

    /** @param array{rows:Collection<int,array<string,mixed>>,tickets:int,unmapped:int} $hasil */
    private function render(array $hasil, CoverageCalculator $coverage): void
    {
        $covered = $coverage->coveredSubjectIds()->flip();
        $rows = $hasil['rows'];
        $sudahAdaMateri = $rows->filter(fn (array $row) => $covered->has($row['id']));

        $target = $this->option('all')
            ? $rows
            : $rows->reject(fn (array $row) => $covered->has($row['id']))->values();

        $this->info(sprintf('%d tiket dibaca, %d subject katalog tersentuh.', $hasil['tickets'], $rows->count()));

        if ($target->isEmpty()) {
            $this->line('Tidak ada subject bermateri-kosong yang muncul di tiket.');
        } else {
            $this->table(
                ['#', 'Subject', 'Tiket', 'Katalog', 'Tebakan', 'Contoh judul'],
                $target->take((int) $this->option('limit'))
                    ->map(fn (array $row, int $i) => [
                        $i + 1,
                        $row['label'],
                        $row['total'],
                        $row['katalog'],
                        $row['tebakan'],
                        implode(' / ', $row['examples']),
                    ])->all(),
            );
        }

        $this->newLine();
        $this->line(sprintf('Sudah punya materi: %d subject (%d tiket).',
            $sudahAdaMateri->count(),
            $sudahAdaMateri->sum('total'),
        ));
        $this->line(sprintf('Tidak terpetakan: %d tiket.', $hasil['unmapped']));
        $this->comment('Tidak ada satu baris pun yang ditulis ke database.');
    }
}
