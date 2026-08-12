<?php

namespace Tests\Feature\TeamLead;

use App\Models\AuditTrail;
use App\Models\IssueCategory;
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
 * Tab Riwayat Team Lead: jejak audit tiket yang diawasinya.
 *
 * Dua peristiwa yang paling ditunggu seorang supervisor — eskalasi Support BPO
 * ke Tim IT, dan penutupan tiket oleh PIC IT — dilakukan orang lain, bukan
 * Team Lead. Feed yang hanya membaca modul `team_lead` tidak akan pernah
 * memuatnya, jadi tes ini menjalankan endpoint aslinya lalu memeriksa feed
 * Team Lead-nya, bukan tabel audit_trails-nya.
 */
class RiwayatAuditTrailTest extends TestCase
{
    use ActsAsRole, RefreshDatabase;

    public function test_eskalasi_bpo_ke_tim_it_terlacak_di_riwayat_team_lead(): void
    {
        [$bpoAgent, $itAgent] = $this->agents();
        $ticket = $this->ticketFor($bpoAgent, $this->subjectRoutedTo($itAgent));

        $this->actingAsAgent($bpoAgent, 'support-bpo');
        $this->post(route('support-bpo.tickets.escalate', $ticket), ['note' => 'Perlu penanganan Tim IT.'])
            ->assertOk();

        $row = $this->riwayatRowFor($ticket, 'escalate');

        $this->assertNotNull($row, 'Eskalasi BPO → Tim IT tidak muncul di Riwayat Team Lead.');
        $this->assertSame($bpoAgent->user->name, $row['actor']);
        $this->assertStringContainsString('Perlu penanganan Tim IT.', $row['detail']);
    }

    public function test_penyelesaian_oleh_pic_it_terlacak_di_riwayat_team_lead(): void
    {
        [, $itAgent] = $this->agents();
        $ticket = $this->ticketFor($itAgent);

        $this->actingAsAgent($itAgent, 'support');
        $this->post(route('support.tickets.resolve', $ticket), ['note' => 'Akses SAP sudah dipulihkan.'])
            ->assertOk();

        $row = $this->riwayatRowFor($ticket, 'resolve');

        $this->assertNotNull($row, 'Penyelesaian oleh PIC IT tidak muncul di Riwayat Team Lead.');
        $this->assertSame($itAgent->user->name, $row['actor']);
        $this->assertStringContainsString('Akses SAP sudah dipulihkan.', $row['detail']);
    }

    public function test_tindakan_korektif_team_lead_tetap_tercatat(): void
    {
        [, $itAgent] = $this->agents();
        $ticket = $this->ticketFor($itAgent);

        $lead = $this->actingAsRole('team-lead');
        $this->post(route('team-lead.tickets.raise-priority', $ticket), ['priority' => 'Critical'])
            ->assertOk();

        $row = $this->riwayatRowFor($ticket, 'raise_priority');

        $this->assertNotNull($row);
        $this->assertSame($lead->name, $row['actor']);
    }

    public function test_tiket_tim_lain_tidak_bocor_ke_riwayat_team_lead(): void
    {
        [$bpoAgent] = $this->agents();
        $ticket = $this->ticketFor($bpoAgent);

        // Tiket ini tetap dipegang BPO — di luar cakupan Team Lead Support IT.
        AuditTrail::record($bpoAgent->user, [
            'module' => 'ticket_support',
            'action' => 'resolve',
            'target_type' => 'ticket',
            'target_id' => $ticket->id,
            'target_name' => $ticket->ticket_no,
            'description' => 'Tiket BPO diselesaikan sendiri oleh BPO.',
        ]);

        $this->assertNull($this->riwayatRowFor($ticket, 'resolve'));
    }

    /** Satu baris Riwayat dari payload dashboard Team Lead yang sebenarnya. */
    private function riwayatRowFor(Ticket $ticket, string $action): ?array
    {
        $this->actingAsRole('team-lead');
        $rows = $this->getJson(route('team-lead.data-feed'))->assertOk()->json('auditRows');

        return collect($rows)
            ->first(fn (array $r) => $r['action'] === $action && $r['ticket'] === $ticket->ticket_no);
    }

    /** @return array{0:SupportAgent,1:SupportAgent} */
    private function agents(): array
    {
        return [
            $this->agent('bpo', 'Denny Firmansyah'),
            $this->agent('it', 'Febria Sahrina'),
        ];
    }

    private function agent(string $type, string $name): SupportAgent
    {
        $user = User::factory()->create([
            'name' => $name,
            'status' => 'active',
            'helpdesk_access' => 'enabled',
        ]);

        return SupportAgent::create([
            'name' => $name,
            'type' => $type,
            'is_active' => true,
            'user_id' => $user->id,
        ])->load('user');
    }

    private function actingAsAgent(SupportAgent $agent, string $roleKey): void
    {
        $this->actingAsUserWithRoles($agent->user, $roleKey);
    }

    /** Subject katalog yang merutekan eskalasi ke agent IT tertentu. */
    private function subjectRoutedTo(SupportAgent $itAgent): ServiceCatalogSubject
    {
        $service = ServiceCatalogService::create(['name' => 'SAP']);

        return ServiceCatalogSubject::create([
            'issue_category_id' => IssueCategory::create(['name' => 'Incident'])->id,
            'service_id' => $service->id,
            'subcategory_id' => ServiceCatalogSubcategory::create([
                'service_id' => $service->id,
                'name' => 'Account & Profile Issues',
            ])->id,
            'name' => 'Login dan Otorisasi SAP',
            'it_agent_id' => $itAgent->id,
            'is_active' => true,
        ]);
    }

    private function ticketFor(SupportAgent $agent, ?ServiceCatalogSubject $subject = null): Ticket
    {
        $now = Carbon::now();

        return Ticket::create([
            'ticket_no' => 'INC-UJI-2026-'.random_int(1000, 9999),
            'title' => 'Tidak bisa masuk SAP',
            'requester_name' => 'Andi Pratama',
            'status' => 'In Progress',
            'priority' => 'Medium',
            'assigned_agent_id' => $agent->id,
            'catalog_subject_id' => $subject?->id,
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
            'policy_name' => 'Uji Riwayat',
            'priority' => 'Medium',
            'service_type' => 'Incident',
            'response_time_minutes' => 480,
            'resolution_time_minutes' => 2880,
            'warning_threshold_percent' => 80,
            'status' => 'active',
        ])->id;
    }
}
