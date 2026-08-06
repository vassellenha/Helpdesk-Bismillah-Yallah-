<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Models\AuditTrail;
use App\Models\SlaPolicy;
use App\Models\SupportAgent;
use App\Models\Ticket;
use App\Models\User;
use App\Support\TicketFlow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * The "Support" stage's `by` field used to read only the ticket's current
 * assigned_agent_id — correct until BPO escalates to IT, at which point that
 * FK is overwritten and Support BPO's name silently vanishes from Riwayat
 * Status, even though they handled the ticket first. TicketFlow::picChain()
 * rebuilds the full PIC history from the `escalate`/`reassign` audit trail
 * instead of trusting the single frozen FK.
 */
class TicketFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_support_stage_mencatat_bpo_dan_it_setelah_eskalasi(): void
    {
        $bpoUser = User::factory()->create(['name' => 'Denny Firmansyah', 'nip' => '19960130096']);
        $bpoAgent = SupportAgent::create(['name' => $bpoUser->name, 'type' => 'bpo', 'is_active' => true, 'user_id' => $bpoUser->id]);

        $itUser = User::factory()->create(['name' => 'Agung Wijayanto', 'nip' => '10027761']);
        $itAgent = SupportAgent::create(['name' => $itUser->name, 'type' => 'it', 'is_active' => true, 'user_id' => $itUser->id]);

        $ticket = $this->inProgressTicket($itAgent->id);

        AuditTrail::record($bpoUser, [
            'module' => 'ticket_support',
            'action' => 'escalate',
            'target_type' => 'ticket',
            'target_id' => $ticket->id,
            'target_name' => $ticket->ticket_no,
            'old_value' => ['assigned_agent' => $bpoAgent->name],
            'new_value' => ['assigned_agent' => $itAgent->name],
            'description' => "{$bpoUser->name} mengeskalasi tiket ke Support IT ({$itAgent->name}).",
        ]);

        $support = TicketFlow::stages($ticket)['stages'][2];

        $this->assertSame('support', $support['key']);
        $this->assertSame('Denny Firmansyah (BPO) → Agung Wijayanto (IT)', $support['by']);
    }

    public function test_support_stage_tanpa_eskalasi_hanya_menampilkan_pic_saat_ini(): void
    {
        $itUser = User::factory()->create(['name' => 'Agung Wijayanto', 'nip' => '10027761']);
        $itAgent = SupportAgent::create(['name' => $itUser->name, 'type' => 'it', 'is_active' => true, 'user_id' => $itUser->id]);

        $ticket = $this->inProgressTicket($itAgent->id);

        $support = TicketFlow::stages($ticket)['stages'][2];

        $this->assertSame('Agung Wijayanto (IT)', $support['by']);
    }

    private function inProgressTicket(int $assignedAgentId): Ticket
    {
        $now = Carbon::now();

        return Ticket::create([
            'ticket_no' => 'INC-UJI-2026-'.random_int(1000, 9999),
            'title' => 'Tiket uji riwayat PIC',
            'requester_name' => 'Andi Pratama',
            'status' => 'In Progress',
            'priority' => 'Medium',
            'assigned_agent_id' => $assignedAgentId,
            'sla_policy_id' => $this->slaPolicyId(),
            'response_time_minutes' => 480,
            'resolution_time_minutes' => 2880,
            'warning_threshold_percent' => 80,
            'response_due_at' => $now->clone()->addHours(8),
            'resolution_due_at' => $now->clone()->addDays(2),
            'warning_at' => $now->clone()->addDays(1),
        ]);
    }

    private ?int $slaPolicyId = null;

    private function slaPolicyId(): int
    {
        return $this->slaPolicyId ??= SlaPolicy::create([
            'policy_name' => 'Uji Riwayat PIC',
            'priority' => 'Medium',
            'service_type' => 'Incident',
            'response_time_minutes' => 480,
            'resolution_time_minutes' => 2880,
            'warning_threshold_percent' => 80,
            'status' => 'active',
        ])->id;
    }
}
