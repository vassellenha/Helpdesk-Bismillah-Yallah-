<?php

declare(strict_types=1);

namespace Tests\Feature\Ticket;

use App\Models\SlaPolicy;
use App\Models\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Jam SLA respons harus berhenti ketika tiket selesai.
 *
 * Kalau petugas pernah merespons, patokannya jelas: first_response_at. Yang
 * mudah terlewat adalah tiket yang selesai TANPA pernah tercatat respons
 * pertama — sebelumnya hitungannya jatuh ke now(), sehingga tiket yang sudah
 * Closed berbulan-bulan lalu tetap menampilkan keterlambatan yang bertambah
 * setiap hari dibuka. Ditemukan saat UAT test case 11 pada AR-AKSES-2026-0001.
 */
final class ResponseSlaStopsWhenTicketDoneTest extends TestCase
{
    use RefreshDatabase;

    private ?int $slaPolicyId = null;

    public function test_tiket_selesai_tanpa_respons_membekukan_hitungan_pada_waktu_penyelesaian(): void
    {
        $tiket = $this->buatTiket(status: 'Closed', resolvedAt: Carbon::now()->subDays(7));

        // Tenggang respons lewat 9 hari sebelum tiket diselesaikan.
        $harusnya = -(9 * 24 * 60);

        $this->assertSame($harusnya, $tiket->response_minutes_remaining);
    }

    public function test_hitungan_tidak_bertambah_seiring_berjalannya_waktu(): void
    {
        $tiket = $this->buatTiket(status: 'Closed', resolvedAt: Carbon::now()->subDays(7));
        $sebelum = $tiket->response_minutes_remaining;

        Carbon::setTestNow(Carbon::now()->addDays(30));

        $this->assertSame($sebelum, $tiket->fresh()->response_minutes_remaining);
    }

    public function test_tiket_yang_masih_berjalan_tetap_dihitung_sampai_sekarang(): void
    {
        $tiket = $this->buatTiket(status: 'In Progress', resolvedAt: null);
        $sebelum = $tiket->response_minutes_remaining;

        Carbon::setTestNow(Carbon::now()->addDay());

        $this->assertLessThan($sebelum, $tiket->fresh()->response_minutes_remaining);
    }

    public function test_respons_yang_sudah_tercatat_tetap_jadi_patokan(): void
    {
        $tiket = $this->buatTiket(status: 'Closed', resolvedAt: Carbon::now()->subDays(7));
        $tiket->update(['first_response_at' => Carbon::now()->subDays(15)]);

        // Direspons 15 hari lalu, tenggangnya 16 hari lalu: telat satu hari.
        $this->assertSame(-(24 * 60), $tiket->fresh()->response_minutes_remaining);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function buatTiket(string $status, ?Carbon $resolvedAt): Ticket
    {
        $mulai = Carbon::now()->subDays(17);

        return Ticket::create([
            'ticket_no' => 'INC-UJI-2026-'.random_int(1000, 9999),
            'title' => 'Tiket uji SLA respons',
            'requester_name' => 'Andi Pratama',
            'status' => $status,
            'priority' => 'Critical',
            'sla_policy_id' => $this->slaPolicyId(),
            'response_time_minutes' => 60,
            'resolution_time_minutes' => 240,
            'warning_threshold_percent' => 80,
            'response_due_at' => $mulai->clone()->addDay(),
            'resolution_due_at' => $mulai->clone()->addDays(2),
            'warning_at' => $mulai->clone()->addHours(40),
            'created_at' => $mulai,
            'resolved_at' => $resolvedAt,
        ]);
    }

    private function slaPolicyId(): int
    {
        return $this->slaPolicyId ??= SlaPolicy::create([
            'policy_name' => 'Uji SLA Respons',
            'priority' => 'Critical',
            'service_type' => 'Incident',
            'response_time_minutes' => 60,
            'resolution_time_minutes' => 240,
            'warning_threshold_percent' => 80,
            'status' => 'active',
        ])->id;
    }
}
