<?php

namespace Tests\Feature;

use App\Models\AuditTrail;
use App\Models\SlaPolicy;
use App\Models\SupportAgent;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * "Kerjakan Sekarang" popup: Support IT/BPO landing on an Open ticket can
 * explicitly move it to In Progress (SupportController::start(),
 * SupportBpoController::start()). Choosing "Nanti" is just not calling this
 * endpoint — the ticket has no other state to assert there.
 */
class SupportStartWorkTest extends TestCase
{
    use RefreshDatabase;

    public function test_support_it_memulai_tiket_open_menjadi_in_progress(): void
    {
        [$agentUser, $ticket] = $this->openTicketFor('it', '10027761', 'Agung Wijayanto');

        $this->post(route('support.tickets.start', $ticket))
            ->assertOk()
            ->assertJson(['status' => 'In Progress']);

        $this->assertSame('In Progress', $ticket->fresh()->status);
        $this->assertNotNull($ticket->fresh()->first_response_at);

        $entry = AuditTrail::where('module', 'ticket_support')->where('action', 'start')->first();
        $this->assertNotNull($entry);
        $this->assertSame($agentUser->id, $entry->actor_id);
        $this->assertSame($ticket->id, $entry->target_id);
    }

    public function test_support_bpo_memulai_tiket_open_menjadi_in_progress(): void
    {
        [, $ticket] = $this->openTicketFor('bpo', '19960130096', 'Denny Firmansyah');

        $this->post(route('support-bpo.tickets.start', $ticket))
            ->assertOk()
            ->assertJson(['status' => 'In Progress']);

        $this->assertSame('In Progress', $ticket->fresh()->status);
    }

    public function test_tiket_yang_bukan_open_tidak_bisa_dimulai(): void
    {
        [, $ticket] = $this->openTicketFor('it', '10027761', 'Agung Wijayanto', status: 'In Progress');

        $this->post(route('support.tickets.start', $ticket))->assertStatus(422);

        $this->assertSame('In Progress', $ticket->fresh()->status);
    }

    public function test_agent_lain_tidak_bisa_memulai_tiket_yang_bukan_miliknya(): void
    {
        [, $ticket] = $this->openTicketFor('it', '10027761', 'Agung Wijayanto');
        $ticket->update(['assigned_agent_id' => SupportAgent::create([
            'name' => 'Agent Lain', 'type' => 'it', 'is_active' => true,
        ])->id]);

        $this->post(route('support.tickets.start', $ticket))->assertStatus(403);
    }

    /** @return array{0:User,1:Ticket} */
    private function openTicketFor(string $type, string $nip, string $name, string $status = 'Open'): array
    {
        $user = User::factory()->create(['name' => $name, 'nip' => $nip, 'status' => 'active', 'helpdesk_access' => 'enabled']);
        $agent = SupportAgent::create(['name' => $name, 'type' => $type, 'is_active' => true, 'user_id' => $user->id]);

        $now = Carbon::now();
        $ticket = Ticket::create([
            'ticket_no' => 'INC-UJI-2026-'.random_int(1000, 9999),
            'title' => 'Tiket uji start work',
            'requester_name' => 'Andi Pratama',
            'status' => $status,
            'priority' => 'Medium',
            'assigned_agent_id' => $agent->id,
            'sla_policy_id' => $this->slaPolicyId(),
            'response_time_minutes' => 480,
            'resolution_time_minutes' => 2880,
            'warning_threshold_percent' => 80,
            'response_due_at' => $now->clone()->addHours(8),
            'resolution_due_at' => $now->clone()->addDays(2),
            'warning_at' => $now->clone()->addDays(1),
        ]);

        return [$user, $ticket];
    }

    private ?int $slaPolicyId = null;

    private function slaPolicyId(): int
    {
        return $this->slaPolicyId ??= SlaPolicy::create([
            'policy_name' => 'Uji Start Work',
            'priority' => 'Medium',
            'service_type' => 'Incident',
            'response_time_minutes' => 480,
            'resolution_time_minutes' => 2880,
            'warning_threshold_percent' => 80,
            'status' => 'active',
        ])->id;
    }
}
