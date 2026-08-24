<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\SlaPolicy;
use App\Models\Ticket;
use App\Support\PriorityRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsRole;
use Tests\TestCase;

/**
 * Prioritas tiket sepenuhnya milik Admin — bukan empat nama tetap di dalam kode.
 *
 * Dua keluhan yang diuji di sini datang langsung dari pemakaian:
 *   1. SLA baru dibuat di Admin, tapi prioritasnya tidak muncul di layar
 *      requester saat membuat tiket.
 *   2. "Critical" diganti jadi "Kritikal", lalu prioritas itu tampak nonaktif
 *      di requester dan warnanya jatuh jadi abu-abu.
 */
final class SlaPriorityCustomisableTest extends TestCase
{
    use ActsAsRole;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        PriorityRegistry::flush();
    }

    private function policy(string $priority, int $resolution, string $status = 'active'): SlaPolicy
    {
        $policy = SlaPolicy::create([
            'policy_name' => $priority.' Policy',
            'priority' => $priority,
            'response_time_minutes' => 60,
            'resolution_time_minutes' => $resolution,
            'warning_threshold_percent' => 80,
            'status' => $status,
        ]);
        PriorityRegistry::flush();

        return $policy;
    }

    private function ticket(string $priority, ?int $slaPolicyId = null, bool $escalated = false): Ticket
    {
        return Ticket::create([
            'ticket_no' => 'INC-UJI-2026-'.random_int(1000, 9999),
            'title' => 'Tiket uji prioritas',
            'requester_name' => 'Andi Pratama',
            'status' => 'In Progress',
            'priority' => $priority,
            'sla_policy_id' => $slaPolicyId,
            'response_time_minutes' => 60,
            'resolution_time_minutes' => 240,
            'warning_threshold_percent' => 80,
            'response_due_at' => now()->addHour(),
            'resolution_due_at' => now()->addHours(4),
            'warning_at' => now()->addHours(3),
            // Eskalasi IT yang belum diklaim — bentuk tiket yang memang masuk
            // cakupan Team Lead (lihat TeamLeadController::assertInScope).
            'escalated_at' => $escalated ? now()->subHour() : null,
        ]);
    }

    public function test_prioritas_baru_buatan_admin_ikut_dikirim_ke_layar_requester(): void
    {
        $this->policy('Critical', 240);
        $this->policy('Low', 7200);
        $this->policy('Impossible', 150);

        $this->actingAsRole('requester');

        $response = $this->getJson('/api/sla-policies/active');

        $response->assertOk();
        $this->assertSame(
            ['Impossible', 'Critical', 'Low'],
            array_column($response->json(), 'priority'),
        );
    }

    public function test_mengganti_nama_prioritas_ikut_memindahkan_tiket_lamanya(): void
    {
        // Tanpa ini, tiket lama memegang nama yang tidak dimiliki policy mana
        // pun: hilang dari filter, netral warnanya, tak terhitung di grafik.
        $policy = $this->policy('Critical', 240);
        $ticket = $this->ticket('Critical', $policy->id);

        $this->actingAsRole('admin');

        $this->putJson("/admin/sla-policies/{$policy->id}", [
            'policy_name' => $policy->policy_name,
            'priority' => 'Kritikal',
            'response_time_minutes' => 60,
            'resolution_time_minutes' => 240,
            'escalation_extension_minutes' => 60,
            'warning_threshold_percent' => 80,
            'status' => 'active',
        ])->assertOk();

        $this->assertSame('Kritikal', $ticket->fresh()->priority);
        PriorityRegistry::flush();
        $this->assertContains('Kritikal', PriorityRegistry::all());
    }

    public function test_tiket_tidak_dipindah_kalau_policy_lain_masih_memakai_nama_lama(): void
    {
        $first = $this->policy('Critical', 240);
        $this->policy('Critical', 300);
        $ticket = $this->ticket('Critical', $first->id);

        $this->actingAsRole('admin');

        $this->putJson("/admin/sla-policies/{$first->id}", [
            'policy_name' => $first->policy_name,
            'priority' => 'Kritikal',
            'response_time_minutes' => 60,
            'resolution_time_minutes' => 240,
            'escalation_extension_minutes' => 60,
            'warning_threshold_percent' => 80,
            'status' => 'active',
        ])->assertOk();

        // Nama lamanya masih sah — tiketnya memang milik policy yang satunya.
        $this->assertSame('Critical', $ticket->fresh()->priority);
    }

    public function test_team_lead_boleh_menaikkan_tiket_ke_prioritas_buatan_admin(): void
    {
        // Validasinya dulu dikunci empat nama, jadi prioritas buatan Admin
        // ditolak 422 — terlihat di layar, tapi tidak bisa dipilih.
        $this->policy('Impossible', 150);
        $low = $this->policy('Low', 7200);

        $ticket = $this->ticket('Low', $low->id, escalated: true);
        $lead = $this->actingAsRole('team-lead');

        $this->postJson("/team-lead/tickets/{$ticket->ticket_no}/raise-priority", [
            'priority' => 'Impossible',
        ])->assertOk();

        $this->assertSame('Impossible', $ticket->fresh()->priority);
    }
}
