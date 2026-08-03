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
            ['Field dipertahankan (override Admin)', $summary['kept_admin_override']],
            ['Tidak ada di sumber', count($summary['not_in_source'])],
            ['Kunci tidak cocok', count($summary['key_mismatch'])],
        ]);

        foreach ($summary['key_mismatch'] as $note) {
            $this->line("  <fg=magenta>kunci beda</> {$note}");
        }

        foreach ($summary['not_in_source'] as $name) {
            $this->line("  <fg=yellow>di luar sumber</> {$name} — tidak ada di respons API, dibiarkan apa adanya");
        }

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

        if ($summary['kept_admin_override'] > 0) {
            $this->newLine();
            $this->comment(
                "{$summary['kept_admin_override']} field dipertahankan karena pernah diedit manual oleh Admin — ".
                'perubahan Admin tidak akan ditimpa sync selama field itu masih ditandai sebagai override.'
            );
        }

        if ($dryRun) {
            $this->newLine();
            $this->comment('Dry run selesai. Jalankan tanpa --dry-run untuk menerapkan.');
        }

        return self::SUCCESS;
    }
}
