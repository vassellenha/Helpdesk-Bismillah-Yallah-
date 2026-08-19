<?php

declare(strict_types=1);

namespace Tests\Feature\Dashboard;

use App\Support\DashboardPeriod;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Rentang "Minggu / Bulan / Tahun" untuk ringkasan dashboard.
 *
 * Yang diuji di sini adalah aturan pembagian waktunya, bukan tampilannya:
 * berapa titik yang dihasilkan tiap rentang, dan — yang paling mudah salah —
 * apakah potongan pertama/terakhir bulan tetap terkurung di dalam bulannya.
 */
final class PeriodSummaryTest extends TestCase
{
    public function test_kunci_periode_sama_dengan_yang_dipakai_dashboard_support(): void
    {
        $this->assertSame(['week', 'month', 'year'], DashboardPeriod::KEYS);
    }

    public function test_minggu_dipecah_jadi_tujuh_titik_harian(): void
    {
        $buckets = DashboardPeriod::buckets('week');

        $this->assertCount(7, $buckets);
        $this->assertTrue($buckets->first()['start']->equalTo(Carbon::now()->startOfWeek()->startOfDay()));
    }

    public function test_tahun_dipecah_jadi_dua_belas_titik_bulanan(): void
    {
        $buckets = DashboardPeriod::buckets('year');

        $this->assertCount(12, $buckets);
        $this->assertSame(1, $buckets->first()['start']->month);
        $this->assertSame(12, $buckets->last()['start']->month);
    }

    /**
     * Potongan mingguan pertama dan terakhir dipotong di batas bulan. Kalau
     * tidak, grafik berjudul "Bulan" ikut menghitung tiket dari bulan tetangga
     * — dan selisihnya tidak akan terlihat sebagai kesalahan, hanya sebagai
     * angka yang sedikit lebih besar.
     */
    public function test_potongan_mingguan_tidak_bocor_ke_bulan_tetangga(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-18 10:00:00'));

        $buckets = DashboardPeriod::buckets('month');

        $this->assertTrue($buckets->first()['start']->equalTo(Carbon::parse('2026-08-01')->startOfMonth()));
        $this->assertTrue($buckets->last()['end']->lessThanOrEqualTo(Carbon::parse('2026-08-31')->endOfMonth()));

        foreach ($buckets as $bucket) {
            $this->assertSame(8, $bucket['start']->month, 'Potongan mulai di luar Agustus.');
            $this->assertSame(8, $bucket['end']->month, 'Potongan berakhir di luar Agustus.');
        }

        Carbon::setTestNow();
    }

    public function test_potongan_berurutan_tanpa_celah_dan_tanpa_tumpang_tindih(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-18 10:00:00'));

        $buckets = DashboardPeriod::buckets('month')->values();

        for ($i = 1; $i < $buckets->count(); $i++) {
            $this->assertTrue(
                $buckets[$i]['start']->greaterThan($buckets[$i - 1]['end']),
                'Potongan tumpang tindih dengan potongan sebelumnya.',
            );
        }

        Carbon::setTestNow();
    }

    public function test_batas_awal_tiap_rentang(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-18 10:00:00'));

        $this->assertTrue(DashboardPeriod::cutoff('week')->equalTo(Carbon::now()->startOfWeek()));
        $this->assertTrue(DashboardPeriod::cutoff('month')->equalTo(Carbon::parse('2026-08-01')->startOfMonth()));
        $this->assertTrue(DashboardPeriod::cutoff('year')->equalTo(Carbon::parse('2026-01-01')->startOfYear()));

        Carbon::setTestNow();
    }

    /** Nilai tak dikenal jatuh ke "bulan", bukan meledak. */
    public function test_periode_tak_dikenal_jatuh_ke_bulan(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-18 10:00:00'));

        $this->assertTrue(DashboardPeriod::cutoff('entah')->equalTo(Carbon::parse('2026-08-01')->startOfMonth()));
        $this->assertEquals(
            DashboardPeriod::buckets('month')->count(),
            DashboardPeriod::buckets('entah')->count(),
        );

        Carbon::setTestNow();
    }
}
