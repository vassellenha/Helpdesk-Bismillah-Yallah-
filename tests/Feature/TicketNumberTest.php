<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\SlaPolicy;
use App\Models\Ticket;
use App\Support\TicketNumber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

/**
 * Format nomor tiket: {INC|SR|AR}-{KODE LAYANAN}-{tahun}-{urut}.
 *
 * Yang paling perlu dijaga di sini bukan bentuk teksnya, melainkan dua hal yang
 * gagalnya diam-diam:
 *
 *   1. Kode layanan tidak boleh mengandung tanda hubung. Nomor urut dibaca dari
 *      segmen TERAKHIR setelah dipisah tanda hubung — satu tanda hubung yang
 *      lolos dari nama layanan membuat pembacaan itu meleset, dan penomoran
 *      berikutnya mengulang angka yang sudah terpakai.
 *   2. Deret nomor terpisah per layanan. ADELE dan SILO APPS masing-masing
 *      mulai dari 0001, dan keduanya tidak boleh saling mendorong.
 */
final class TicketNumberTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-08-05 09:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** Layanan yang tidak terdaftar di config disingkat otomatis. */
    public function test_kode_layanan_membuang_spasi_dan_tanda_baca(): void
    {
        $this->assertSame('ADELE', TicketNumber::serviceCode('ADELE'));
        $this->assertSame('IBLAST', TicketNumber::serviceCode('iBLAST'));
        $this->assertSame('ADHIMISJO', TicketNumber::serviceCode('ADHIMIS-JO'));
    }

    public function test_singkatan_dari_config_dipakai_lebih_dulu(): void
    {
        Config::set('helpdesk.service_codes', [
            'PERUBAHAN AKSES APLIKASI' => 'AKSES',
            'Asset Management System' => 'AMS',
        ]);

        $this->assertSame('AKSES', TicketNumber::serviceCode('PERUBAHAN AKSES APLIKASI'));
        $this->assertSame('AMS', TicketNumber::serviceCode('Asset Management System'));
    }

    /** Kapitalisasi nama layanan yang berubah tidak boleh mematikan singkatan. */
    public function test_pencocokan_singkatan_abai_besar_kecil_huruf(): void
    {
        Config::set('helpdesk.service_codes', ['Sahabat APP' => 'SAHABAT']);

        $this->assertSame('SAHABAT', TicketNumber::serviceCode('SAHABAT APP'));
        $this->assertSame('SAHABAT', TicketNumber::serviceCode('sahabat app'));
    }

    /**
     * Singkatan yang salah ketik di config tidak boleh merusak format.
     * Tanda hubung di sana sama berbahayanya dengan tanda hubung di nama.
     */
    public function test_singkatan_config_ikut_dibersihkan(): void
    {
        Config::set('helpdesk.service_codes', ['ADHI MAN-POWER' => 'MAN-POWER']);

        $this->assertSame('MANPOWER', TicketNumber::serviceCode('ADHI MAN-POWER'));
    }

    /**
     * Tanda hubung pada nama layanan WAJIB ikut terbuang, bukan diganti.
     * Diuji terpisah karena inilah satu-satunya karakter yang merusak format.
     */
    public function test_tanda_hubung_pada_nama_layanan_tidak_pernah_lolos(): void
    {
        $this->assertStringNotContainsString('-', TicketNumber::serviceCode('ADHI MAN-POWER'));
        $this->assertStringNotContainsString('-', TicketNumber::serviceCode('ADHIMIS-JO'));
    }

    public function test_tiket_tanpa_layanan_memakai_kode_other(): void
    {
        $this->assertSame('OTHER', TicketNumber::serviceCode(null));
        $this->assertSame('OTHER', TicketNumber::serviceCode(''));
        $this->assertSame('OTHER', TicketNumber::serviceCode('   '));
    }

    public function test_nomor_pertama_sebuah_layanan_mulai_dari_0001(): void
    {
        $this->assertSame('INC-ADELE-2026-0001', TicketNumber::next('INC', 'ADELE'));
    }

    public function test_deret_nomor_terpisah_per_layanan(): void
    {
        $this->buatTiket('INC-ADELE-2026-0001');
        $this->buatTiket('INC-ADELE-2026-0002');

        // NETWORK belum punya tiket sama sekali → tetap mulai dari 0001.
        // Sengaja memakai layanan yang TIDAK terdaftar di helpdesk.service_codes,
        // supaya tes ini tetap berlaku saat daftar singkatannya diubah.
        $this->assertSame('INC-NETWORK-2026-0001', TicketNumber::next('INC', 'NETWORK'));
        $this->assertSame('INC-ADELE-2026-0003', TicketNumber::next('INC', 'ADELE'));
    }

    /**
     * Deret dihitung per LAYANAN, bukan per jenis tiket. Satu layanan yang
     * punya Incident dan Access Request memakai deret yang sama.
     */
    public function test_jenis_tiket_tidak_memisah_deret(): void
    {
        $this->buatTiket('INC-ADELE-2026-0001');

        $this->assertSame('AR-ADELE-2026-0002', TicketNumber::next('AR', 'ADELE'));
    }

    public function test_deret_dimulai_ulang_tiap_tahun(): void
    {
        $this->buatTiket('INC-ADELE-2025-0009');

        $this->assertSame('INC-ADELE-2026-0001', TicketNumber::next('INC', 'ADELE'));
    }

    /**
     * Layanan yang namanya berakhiran sama tidak boleh saling terbaca.
     * Pola pencariannya diawali tanda hubung justru untuk ini.
     */
    public function test_layanan_berakhiran_serupa_tidak_tercampur(): void
    {
        $this->buatTiket('INC-XELISA-2026-0007');

        $this->assertSame('INC-ELISA-2026-0001', TicketNumber::next('INC', 'ELISA'));
    }

    /**
     * Tiket seadanya — hanya kolom yang WAJIB diisi, karena yang diuji di sini
     * cuma nomornya. Kebijakan SLA dibuat sekali lalu dipakai ulang; tabel
     * tickets menuntut relasi itu ada.
     */
    private function buatTiket(string $nomor): Ticket
    {
        $now = Carbon::now();

        return Ticket::create([
            'ticket_no' => $nomor,
            'title' => 'Tiket uji',
            'requester_name' => 'Andi Pratama',
            'status' => 'Open',
            'priority' => 'Low',
            'sla_policy_id' => $this->slaPolicyId(),
            'response_time_minutes' => 60,
            'resolution_time_minutes' => 480,
            'warning_threshold_percent' => 80,
            'response_due_at' => $now,
            'resolution_due_at' => $now,
            'warning_at' => $now,
        ]);
    }

    private function slaPolicyId(): int
    {
        return $this->slaPolicyId ??= SlaPolicy::create([
            'policy_name' => 'Uji',
            'priority' => 'Low',
            'service_type' => 'Incident',
            'response_time_minutes' => 60,
            'resolution_time_minutes' => 480,
            'warning_threshold_percent' => 80,
            'status' => 'active',
        ])->id;
    }

    private ?int $slaPolicyId = null;
}
