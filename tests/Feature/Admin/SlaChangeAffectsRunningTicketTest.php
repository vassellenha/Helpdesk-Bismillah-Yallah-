<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\ServiceCatalogService;
use App\Models\SlaPolicy;
use App\Models\Ticket;
use App\Support\PriorityRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\ActsAsRole;
use Tests\TestCase;

/**
 * Apa yang terjadi pada tiket yang SEDANG BERJALAN ketika Admin memperketat
 * target SLA-nya di tengah jalan.
 *
 * Pertanyaannya sederhana: apakah rusak, atau ikut menyesuaikan? Berkas ini
 * menjawabnya dengan mengukur, bukan menebak.
 */
final class SlaChangeAffectsRunningTicketTest extends TestCase
{
    use ActsAsRole;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        PriorityRegistry::flush();
    }

    private function criticalPolicy(): SlaPolicy
    {
        return SlaPolicy::create([
            'policy_name' => 'Critical Response',
            'priority' => 'Critical',
            'response_time_minutes' => 60,
            'resolution_time_minutes' => 240,   // 4 jam
            'escalation_extension_minutes' => 120,
            'warning_threshold_percent' => 80,
            'status' => 'active',
        ]);
    }

    /** Tiket dibuat 1 jam lalu, jadi masih punya sisa waktu. */
    private function runningTicket(SlaPolicy $policy): Ticket
    {
        $createdAt = Carbon::now()->subHour();

        $ticket = Ticket::create([
            'ticket_no' => 'INC-UJI-2026-'.random_int(1000, 9999),
            'title' => 'Tiket berjalan saat SLA diubah',
            'requester_name' => 'Andi Pratama',
            'status' => 'In Progress',
            'priority' => 'Critical',
            'sla_policy_id' => $policy->id,
            'response_time_minutes' => $policy->response_time_minutes,
            'resolution_time_minutes' => $policy->resolution_time_minutes,
            'warning_threshold_percent' => $policy->warning_threshold_percent,
            'response_due_at' => $createdAt->clone()->addMinutes(60),
            'resolution_due_at' => $createdAt->clone()->addMinutes(240),
            'warning_at' => $createdAt->clone()->addMinutes(192), // 80% dari 240
        ]);

        $ticket->created_at = $createdAt;
        $ticket->save();

        return $ticket->fresh();
    }

    private function tightenTo(SlaPolicy $policy, int $resolutionMinutes): \Illuminate\Testing\TestResponse
    {
        return $this->putJson("/admin/sla-policies/{$policy->id}", [
            'policy_name' => $policy->policy_name,
            'priority' => $policy->priority,
            'response_time_minutes' => $policy->response_time_minutes,
            'resolution_time_minutes' => $resolutionMinutes,
            'escalation_extension_minutes' => 120,
            'warning_threshold_percent' => 80,
            'status' => 'active',
        ]);
    }

    public function test_memperketat_sla_tidak_menimbulkan_error(): void
    {
        $policy = $this->criticalPolicy();
        $ticket = $this->runningTicket($policy);

        $this->actingAsRole('admin');

        $this->tightenTo($policy, 180)->assertOk();

        // Tiketnya masih utuh dan masih bisa dibaca layar mana pun.
        $ticket = $ticket->fresh();
        $this->assertSame('In Progress', $ticket->status);
        $this->assertContains($ticket->sla_kind, ['ontrack', 'warning', 'breach']);
    }

    public function test_tiket_berjalan_tetap_memakai_target_lamanya(): void
    {
        $policy = $this->criticalPolicy();
        $ticket = $this->runningTicket($policy);
        $deadlineSebelum = $ticket->resolution_due_at->toDateTimeString();

        $this->actingAsRole('admin');
        $this->tightenTo($policy, 180)->assertOk();

        $ticket = $ticket->fresh();

        // Inilah perilakunya: tenggat tiket yang sudah berjalan TIDAK ikut maju.
        $this->assertSame($deadlineSebelum, $ticket->resolution_due_at->toDateTimeString());
        $this->assertSame(240, $ticket->resolution_time_minutes);
    }

    public function test_tiket_baru_setelah_perubahan_memakai_target_baru(): void
    {
        $policy = $this->criticalPolicy();

        $this->actingAsRole('admin');
        $this->tightenTo($policy, 180)->assertOk();
        $this->assertSame(180, $policy->fresh()->resolution_time_minutes);

        // Dibuat lewat layar requester yang sebenarnya, bukan disisipkan
        // langsung ke basis data — supaya yang diukur benar-benar target yang
        // dipakai aplikasi saat tiket lahir.
        $this->actingAsRole('requester');

        $service = ServiceCatalogService::create(['name' => 'SAP', 'is_active' => true]);

        $response = $this->postJson('/api/tickets', [
            'title' => 'Tiket sesudah SLA diperketat',
            'sla_policy_id' => $policy->id,
            'issue_category' => 'Incident',
            'service_id' => $service->id,
            'service_name' => $service->name,
            'subcategory_name' => 'PERFORMANCE',
            'subject_name' => 'Lambat sesudah perubahan target',
        ])->assertCreated();

        $baru = Ticket::find($response->json('id'));

        $this->assertSame(180, $baru->resolution_time_minutes);
        // Tenggatnya 3 jam dari sekarang, bukan 4 jam.
        $this->assertEqualsWithDelta(
            180,
            Carbon::now()->diffInMinutes($baru->resolution_due_at, false),
            2,
        );
    }
}
