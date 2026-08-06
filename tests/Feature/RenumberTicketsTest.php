<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\SlaPolicy;
use App\Models\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Penomoran ulang tiket lama ke format {JENIS}-{LAYANAN}-{tahun}-{urut}.
 *
 * Perintah ini MENIMPA nomor yang sudah terlanjur beredar di notifikasi dan
 * ekspor. Tiga hal yang dijaga:
 *
 *   1. Urutannya mengikuti umur tiket. Tiket tertua sebuah layanan harus dapat
 *      0001 — kalau tidak, nomor kecil menunjuk tiket baru dan urutan waktunya
 *      berbohong.
 *   2. Tidak ada nomor kembar. Kolomnya unik, jadi tabrakan di tengah proses
 *      menggagalkan seluruh perintah — dan separuh tiket sudah terlanjur
 *      berganti nomor.
 *   3. Hanya nomor yang berubah. Tidak ada kolom lain yang boleh ikut tersentuh.
 */
final class RenumberTicketsTest extends TestCase
{
    use RefreshDatabase;

    public function test_menomori_ulang_per_layanan_urut_dari_yang_tertua(): void
    {
        $lama = $this->tiket('INC-2026-0005', 'ADELE', 'Incident', '2026-01-10');
        $baru = $this->tiket('INC-2026-0009', 'ADELE', 'Incident', '2026-03-02');
        // Layanan yang tidak terdaftar di helpdesk.service_codes, supaya tes ini
        // tidak ikut patah saat daftar singkatannya diubah.
        $lain = $this->tiket('SR-2026-0007', 'NETWORK', 'Service Request', '2026-02-01');

        $this->artisan('tickets:renumber')->assertSuccessful();

        $this->assertSame('INC-ADELE-2026-0001', $lama->fresh()->ticket_no);
        $this->assertSame('INC-ADELE-2026-0002', $baru->fresh()->ticket_no);
        // Layanan lain punya deret sendiri, tidak ikut terdorong.
        $this->assertSame('SR-NETWORK-2026-0001', $lain->fresh()->ticket_no);
    }

    public function test_tiket_tanpa_layanan_memakai_kode_other(): void
    {
        $tiket = $this->tiket('INC-2026-0033', null, null, '2026-01-05');

        $this->artisan('tickets:renumber')->assertSuccessful();

        $this->assertSame('INC-OTHER-2026-0001', $tiket->fresh()->ticket_no);
    }

    public function test_deret_terpisah_per_tahun(): void
    {
        $duaRibuDuaPuluhLima = $this->tiket('INC-2025-0002', 'ADELE', 'Incident', '2025-11-20');
        $duaRibuDuaPuluhEnam = $this->tiket('INC-2026-0004', 'ADELE', 'Incident', '2026-01-03');

        $this->artisan('tickets:renumber')->assertSuccessful();

        $this->assertSame('INC-ADELE-2025-0001', $duaRibuDuaPuluhLima->fresh()->ticket_no);
        $this->assertSame('INC-ADELE-2026-0001', $duaRibuDuaPuluhEnam->fresh()->ticket_no);
    }

    /** Dijalankan dua kali tidak boleh menggeser nomor yang sudah benar. */
    public function test_aman_dijalankan_berulang(): void
    {
        $tiket = $this->tiket('INC-2026-0005', 'ADELE', 'Incident', '2026-01-10');

        $this->artisan('tickets:renumber')->assertSuccessful();
        $pertama = $tiket->fresh()->ticket_no;

        $this->artisan('tickets:renumber')->assertSuccessful();

        $this->assertSame($pertama, $tiket->fresh()->ticket_no);
    }

    public function test_dry_run_tidak_mengubah_apa_pun(): void
    {
        $tiket = $this->tiket('INC-2026-0005', 'ADELE', 'Incident', '2026-01-10');

        $this->artisan('tickets:renumber', ['--dry-run' => true])->assertSuccessful();

        $this->assertSame('INC-2026-0005', $tiket->fresh()->ticket_no);
    }

    public function test_hanya_nomor_yang_berubah(): void
    {
        $tiket = $this->tiket('INC-2026-0005', 'ADELE', 'Incident', '2026-01-10');

        $this->artisan('tickets:renumber')->assertSuccessful();

        $segar = $tiket->fresh();
        $this->assertSame('Tiket uji', $segar->title);
        $this->assertSame('ADELE', $segar->service_name);
        $this->assertSame('Open', $segar->status);
    }

    private function tiket(string $nomor, ?string $layanan, ?string $jenis, string $dibuat): Ticket
    {
        $waktu = Carbon::parse($dibuat);

        $tiket = Ticket::create([
            'ticket_no' => $nomor,
            'title' => 'Tiket uji',
            'requester_name' => 'Andi Pratama',
            'service_name' => $layanan,
            'issue_category' => $jenis,
            'status' => 'Open',
            'priority' => 'Low',
            'sla_policy_id' => $this->slaPolicyId(),
            'response_time_minutes' => 60,
            'resolution_time_minutes' => 480,
            'warning_threshold_percent' => 80,
            'response_due_at' => $waktu,
            'resolution_due_at' => $waktu,
            'warning_at' => $waktu,
        ]);

        // created_at tidak fillable — ditulis setelah barisnya terbentuk.
        $tiket->created_at = $waktu;
        $tiket->save();

        return $tiket;
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
