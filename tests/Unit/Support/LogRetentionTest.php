<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\Eva\LogRetention;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

/**
 * Aturan masa simpan log EVA — satu tempat, dipakai penyapu maupun layar.
 *
 * Perlu diuji terpisah karena hitungannya dipakai dua pihak yang tidak boleh
 * berselisih: perintah `eva:purge-expired-logs` yang MENGHAPUS, dan hitung
 * mundur di layar yang MENJANJIKAN kapan penghapusan itu terjadi. Kalau
 * keduanya menghitung sendiri-sendiri, layar bisa menjanjikan "2 hari lagi"
 * pada baris yang malam itu juga disapu.
 */
final class LogRetentionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Config::set('eva.log_retention_days', 14);
        Carbon::setTestNow('2026-08-03 09:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_sisa_hari_dihitung_dari_umur_baris(): void
    {
        $this->assertSame(14, LogRetention::daysLeft(Carbon::now()));
        $this->assertSame(4, LogRetention::daysLeft(Carbon::now()->subDays(10)));
        $this->assertSame(1, LogRetention::daysLeft(Carbon::now()->subDays(13)));
    }

    /**
     * Baris yang sudah lewat masa simpan mengembalikan 0, bukan angka negatif.
     *
     * Baris seperti ini nyata ada di layar: penyapu jalan sekali sehari, jadi
     * antara lewat tenggat dan tersapu ada jeda sampai 24 jam. "-3 hari" di
     * layar tidak berarti apa-apa bagi admin; 0 dibaca sebagai "sebentar lagi".
     */
    public function test_yang_sudah_lewat_tenggat_tidak_pernah_negatif(): void
    {
        $this->assertSame(0, LogRetention::daysLeft(Carbon::now()->subDays(14)));
        $this->assertSame(0, LogRetention::daysLeft(Carbon::now()->subDays(90)));
    }

    /** Baris tanpa tanggal tidak boleh menghasilkan angka tebakan. */
    public function test_tanpa_tanggal_mengembalikan_null(): void
    {
        $this->assertNull(LogRetention::daysLeft(null));
    }

    public function test_masa_simpan_mengikuti_config(): void
    {
        Config::set('eva.log_retention_days', 30);

        $this->assertSame(30, LogRetention::days());
        $this->assertSame(20, LogRetention::daysLeft(Carbon::now()->subDays(10)));
    }

    /** Batas yang dipakai penyapu harus berasal dari kelas yang sama. */
    public function test_batas_sapu_sejalan_dengan_sisa_hari(): void
    {
        $this->assertSame('2026-07-20 09:00:00', LogRetention::cutoff()->toDateTimeString());
    }
}
