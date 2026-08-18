<?php

declare(strict_types=1);

namespace Tests\Feature\TeamLead;

use App\Models\IssueCategory;
use App\Models\Role;
use App\Models\ServiceCatalogService;
use App\Models\ServiceCatalogSubcategory;
use App\Models\ServiceCatalogSubject;
use App\Models\SlaPolicy;
use App\Models\SupportAgent;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\ActsAsRole;
use Tests\TestCase;

/**
 * Sebelum perbaikan ini, scopeTickets()/assertInScope() Team Lead memakai
 * whereHas('assignedAgent', type=it) — begitu BPO mengeskalasi tiket
 * "Lainnya" lewat jalur broadcast (TicketBroadcast::escalateBroadcast(),
 * assigned_agent_id kembali null sampai ada PIC IT yang mengklaim), tiket
 * itu langsung lenyap dari SETIAP layar Team Lead (dashboard, monitor SLA,
 * ekspor) walau menurut dokumentasi controllernya sendiri tiket itu sudah
 * masuk cakupan Tim IT sejak dieskalasi.
 */
class TeamLeadSeesUnclaimedItBroadcastTest extends TestCase
{
    use ActsAsRole, RefreshDatabase;

    public function test_tiket_broadcast_it_yang_belum_diklaim_tetap_kelihatan_di_dashboard_team_lead(): void
    {
        $bpo = $this->agent('bpo', 'Andi Pratama', 'Support BPO');
        $it = $this->agent('it', 'Agung Wijayanto', 'Support IT');

        $issueCategory = IssueCategory::firstOrCreate(['name' => 'Incident']);
        $service = ServiceCatalogService::create(['name' => 'ELISA']);
        $subcategory = ServiceCatalogSubcategory::create(['service_id' => $service->id, 'name' => 'Vendor Management']);
        ServiceCatalogSubject::create([
            'issue_category_id' => $issueCategory->id, 'service_id' => $service->id, 'subcategory_id' => $subcategory->id,
            'name' => 'Subject '.$it->name, 'requires_approval' => false,
            'support_agent_id' => $bpo->id, 'it_agent_id' => $it->id, 'support_level' => 2, 'is_active' => true,
        ]);

        $now = Carbon::now();
        $policy = SlaPolicy::create([
            'policy_name' => 'Uji Team Lead Broadcast', 'priority' => 'Medium', 'service_type' => 'Incident',
            'response_time_minutes' => 480, 'resolution_time_minutes' => 2880, 'warning_threshold_percent' => 80, 'status' => 'active',
        ]);
        $ticket = Ticket::create([
            'ticket_no' => 'INC-UJI-2026-'.random_int(1000, 9999),
            'title' => 'Kendala ELISA', 'requester_name' => 'Andi Requester', 'status' => 'Open', 'priority' => 'Medium',
            'sla_policy_id' => $policy->id, 'service_name' => $service->name, 'service_catalog_service_id' => $service->id,
            'catalog_subject_id' => null, 'assigned_agent_id' => $bpo->id,
            'response_time_minutes' => 480, 'resolution_time_minutes' => 2880, 'warning_threshold_percent' => 80,
            'response_due_at' => $now->clone()->addHours(8), 'resolution_due_at' => $now->clone()->addDays(2), 'warning_at' => $now->clone()->addDays(1),
        ]);

        $this->actingAsUserWithRoles($bpo->user, 'support-bpo');
        $this->postJson(route('support-bpo.tickets.escalate', $ticket), ['note' => 'perlu IT'])->assertOk();

        $ticket->refresh();
        $this->assertNull($ticket->assigned_agent_id, 'Prasyarat: broadcast IT belum diklaim siapa pun.');
        $this->assertNotNull($ticket->escalated_at);

        $this->actingAsRole('team-lead');

        $rows = $this->getJson(route('team-lead.data-feed'))->assertOk()->json('monitorRows');
        $ids = collect($rows)->pluck('id');
        $this->assertTrue($ids->contains($ticket->ticket_no), 'Tiket broadcast IT yang belum diklaim harus tetap tampil di dashboard Team Lead.');

        $this->getJson(route('team-lead.tickets.data', $ticket))->assertOk();
    }

    private function agent(string $type, string $name, string $roleName): SupportAgent
    {
        $role = Role::firstOrCreate(['name' => $roleName]);
        $user = User::factory()->create(['name' => $name, 'status' => 'active', 'helpdesk_access' => 'enabled']);
        $user->roles()->attach($role->id);

        return SupportAgent::create(['name' => $name, 'type' => $type, 'is_active' => true, 'user_id' => $user->id])->load('user');
    }
}
