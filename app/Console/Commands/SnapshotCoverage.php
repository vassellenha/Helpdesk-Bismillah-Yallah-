<?php

namespace App\Console\Commands;

use App\Models\Knowledge\CoverageSnapshot;
use App\Services\Knowledge\CoverageCalculator;
use Illuminate\Console\Command;

/**
 * Merekam kesiapan EVA hari ini ke kb_coverage_snapshots.
 *
 * Ini satu-satunya cara baris riwayat lahir. Sebelumnya seeder mengarang lima
 * titik dari array persentase, dan grafik menampilkannya seolah-olah itu
 * riwayat sungguhan — orang membaca "kita naik 5 poin" dari angka yang tidak
 * pernah terjadi.
 *
 * Angka hari ini TIDAK pernah dibaca dari tabel ini; CoverageCalculator selalu
 * menghitungnya ulang. Tabel ini murni ingatan masa lalu.
 *
 * Jadwalkan sesuai kebutuhan (harian atau bulanan) — satu baris per tanggal,
 * jadi dijalankan berkali-kali dalam sehari tetap aman.
 */
class SnapshotCoverage extends Command
{
    protected $signature = 'eva:snapshot-coverage';

    protected $description = 'Catat kesiapan EVA hari ini sebagai titik riwayat grafik tren Coverage.';

    public function handle(CoverageCalculator $coverage): int
    {
        $summary = $coverage->summary();

        CoverageSnapshot::updateOrCreate(
            ['captured_on' => today()],
            [
                'total_subjects' => $summary['total_subjects'],
                'covered_subjects' => $summary['covered_subjects'],
                'coverage_percent' => $summary['percent'],
            ],
        );

        $this->info(sprintf(
            'Kesiapan EVA %s: %d%% (%d dari %d subject).',
            today()->translatedFormat('d M Y'),
            $summary['percent'],
            $summary['covered_subjects'],
            $summary['total_subjects'],
        ));

        return self::SUCCESS;
    }
}
