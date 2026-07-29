<?php

namespace App\Console\Commands;

use App\Support\EmployeeSync;
use Illuminate\Console\Command;

class SyncEmployees extends Command
{
    protected $signature = 'employees:sync {--dry-run : Tampilkan perubahan tanpa menulis ke database}';

    protected $description = 'Sinkronkan data pengguna dari API data pegawai perusahaan';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $driver = config('integrations.employee_directory.driver');

        $this->line("Driver: <options=bold>{$driver}</>".($dryRun ? '  <fg=yellow>(dry run — tidak ada penulisan)</>' : ''));

        $summary = EmployeeSync::run($dryRun);

        if ($summary['fetched'] === 0) {
            $this->warn('Tidak ada data pegawai yang diterima. Cek log untuk penyebabnya.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->table(['Hasil', 'Jumlah'], [
            ['Diterima dari sumber', $summary['fetched']],
            ['Dibuat', $summary['created']],
            ['Diperbarui', $summary['updated']],
            ['Tidak berubah', $summary['unchanged']],
            ['Dinonaktifkan', $summary['deactivated']],
            ['Dilewati', count($summary['skipped'])],
            ['Field dipertahankan (API kosong)', $summary['kept_empty']],
        ]);

        foreach ($summary['changes'] as $change) {
            $this->line("  <fg=cyan>diubah</> {$change['name']} — ".implode(', ', $change['fields']));
        }

        foreach ($summary['skipped'] as $reason) {
            $this->warn("  dilewati — {$reason}");
        }

        if ($summary['kept_empty'] > 0) {
            $this->newLine();
            $this->comment(
                "{$summary['kept_empty']} field dipertahankan karena API tidak mengirim nilainya — ".
                'field itu tidak dikembalikan sync. Setel EMPLOYEE_DIRECTORY_OVERWRITE_WITH_EMPTY=true agar API selalu menang.'
            );
        }

        if ($dryRun) {
            $this->newLine();
            $this->comment('Dry run selesai. Jalankan tanpa --dry-run untuk menerapkan.');
        }

        return self::SUCCESS;
    }
}
