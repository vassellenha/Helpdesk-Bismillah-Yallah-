<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\IssueCategory;
use App\Models\Role;
use App\Models\ServiceCatalogService;
use App\Models\ServiceCatalogSubcategory;
use App\Models\ServiceCatalogSubject;
use App\Models\SlaPolicy;
use App\Models\SupportAgent;
use App\Models\Ticket;
use App\Models\TicketNotification;
use App\Models\User;
use App\Support\TicketBroadcast;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Begitu BPO mengeskalasi tiket "Lainnya" (tanpa Subject spesifik) ke IT,
 * tiket itu broadcast lagi — kali ini ke semua PIC IT Layanan-nya, pola
 * yang sama persis dengan broadcast BPO di awal tiket dibuat. Siapa pun IT
 * yang pertama bertindak otomatis mengklaimnya, PIC IT lain diberi tahu.
 */
class TicketEscalateBroadcastTest extends TestCase
{
    use RefreshDatabase;

    private function agent(string $name, string $type, string $roleName): SupportAgent
    {
        $role = Role::firstOrCreate(['name' => $roleName]);
        $user = User::factory()->create(['name' => $name, 'status' => 'active', 'helpdesk_access' => 'enabled']);
        $user->roles()->attach($role->id);

        return SupportAgent::create(['name' => $name, 'type' => $type, 'is_active' => true, 'user_id' => $user->id]);
    }

    /**
     * Layanan ELISA dengan satu Subject per pasangan BPO/IT yang diberikan
     * — supaya mereka jadi PIC broadcast lewat jalur sungguhan
     * (support_agent_id / it_agent_id di ServiceCatalogSubject), bukan
     * daftar terpisah.
     */
    private function serviceWithPics(SupportAgent $bpo, array $itAgents): ServiceCatalogService
    {
        $issueCategory = IssueCategory::firstOrCreate(['name' => 'Incident']);
        $service = ServiceCatalogService::create(['name' => 'ELISA']);
        $subcategory = ServiceCatalogSubcategory::create(['service_id' => $service->id, 'name' => 'Vendor Management']);

        foreach ($itAgents as $it) {
            ServiceCatalogSubject::create([
                'issue_category_id' => $issueCategory->id, 'service_id' => $service->id, 'subcategory_id' => $subcategory->id,
                'name' => 'Subject '.$it->name, 'requires_approval' => false,
                'support_agent_id' => $bpo->id, 'it_agent_id' => $it->id, 'support_level' => 2, 'is_active' => true,
            ]);
        }

        return $service;
    }

    private function broadcastTicket(ServiceCatalogService $service): Ticket
    {
        $now = Carbon::now();
        $policy = SlaPolicy::create([
            'policy_name' => 'Uji Eskalasi Broadcast', 'priority' => 'Medium', 'service_type' => 'Incident',
            'response_time_minutes' => 480, 'resolution_time_minutes' => 2880, 'warning_threshold_percent' => 80, 'status' => 'active',
        ]);

        return Ticket::create([
            'ticket_no' => 'INC-UJI-2026-'.random_int(1000, 9999),
            'title' => 'Kendala ELISA',
            'requester_name' => 'Andi Requester',
            'status' => 'Open',
            'priority' => 'Medium',
            'sla_policy_id' => $policy->id,
            'service_name' => $service->name,
            'service_catalog_service_id' => $service->id,
            'catalog_subject_id' => null,
            'assigned_agent_id' => null,
            'response_time_minutes' => 480,
            'resolution_time_minutes' => 2880,
            'warning_threshold_percent' => 80,
            'response_due_at' => $now->clone()->addHours(8),
            'resolution_due_at' => $now->clone()->addDays(2),
            'warning_at' => $now->clone()->addDays(1),
        ]);
    }

    public function test_eskalasi_tiket_lainnya_broadcast_ke_semua_pic_it_dan_reset_assigned_agent(): void
    {
        $bpo = $this->agent('Andi Pratama', 'bpo', 'Support BPO');
        $itA = $this->agent('Agung Wijayanto', 'it', 'Support IT');
        $itB = $this->agent('Aditya Dwi Nugraha', 'it', 'Support IT');
        $service = $this->serviceWithPics($bpo, [$itA, $itB]);
        $ticket = $this->broadcastTicket($service);

        $this->actingAs(User::find($bpo->user_id))
            ->postJson(route('support-bpo.tickets.escalate', $ticket), ['note' => 'perlu IT'])
            ->assertOk();

        $ticket->refresh();

        $this->assertNull($ticket->assigned_agent_id, 'Belum diklaim IT manapun — bukan ditebak satu.');
        $this->assertNotNull($ticket->escalated_at);

        foreach ([$itA, $itB] as $it) {
            $notif = TicketNotification::where('user_id', $it->user_id)
                ->where('type', 'ticket_incoming_escalation')
                ->first();
            $this->assertNotNull($notif, "{$it->name} harus dapat notifikasi eskalasi.");
        }
    }

    public function test_it_pertama_membalas_otomatis_klaim_dan_it_lain_diberitahu(): void
    {
        $bpo = $this->agent('Andi Pratama', 'bpo', 'Support BPO');
        $itA = $this->agent('Agung Wijayanto', 'it', 'Support IT');
        $itB = $this->agent('Aditya Dwi Nugraha', 'it', 'Support IT');
        $service = $this->serviceWithPics($bpo, [$itA, $itB]);
        $ticket = $this->broadcastTicket($service);

        $this->actingAs(User::find($bpo->user_id))
            ->postJson(route('support-bpo.tickets.escalate', $ticket), ['note' => 'perlu IT'])
            ->assertOk();

        $ticket->refresh();

        $this->actingAs(User::find($itA->user_id))
            ->postJson(route('support.tickets.comments.store', $ticket), ['message' => 'Saya cek dulu.'])
            ->assertCreated();

        $ticket->refresh();
        $this->assertSame($itA->id, $ticket->assigned_agent_id);

        $notif = TicketNotification::where('user_id', $itB->user_id)
            ->where('type', 'ticket_claimed_by_other')
            ->first();
        $this->assertNotNull($notif);
        $this->assertStringContainsString('Agung Wijayanto', $notif->message);

        // Aditya (itB) sekarang ditolak — sudah keburu diklaim Agung.
        $this->actingAs(User::find($itB->user_id))
            ->getJson(route('support.tickets.data', $ticket))
            ->assertForbidden();
    }

    public function test_subject_dengan_it_agent_spesifik_tetap_single_target_tidak_broadcast(): void
    {
        $bpo = $this->agent('Andi Pratama', 'bpo', 'Support BPO');
        $it = $this->agent('Agung Wijayanto', 'it', 'Support IT');

        $issueCategory = IssueCategory::firstOrCreate(['name' => 'Incident']);
        $service = ServiceCatalogService::create(['name' => 'ELISA']);
        $subcategory = ServiceCatalogSubcategory::create(['service_id' => $service->id, 'name' => 'Vendor Management']);
        $subject = ServiceCatalogSubject::create([
            'issue_category_id' => $issueCategory->id, 'service_id' => $service->id, 'subcategory_id' => $subcategory->id,
            'name' => 'Tidak bisa release vendor', 'requires_approval' => false,
            'support_agent_id' => $bpo->id, 'it_agent_id' => $it->id, 'support_level' => 2, 'is_active' => true,
        ]);

        $now = Carbon::now();
        $policy = SlaPolicy::create([
            'policy_name' => 'Uji Single Target', 'priority' => 'Medium', 'service_type' => 'Incident',
            'response_time_minutes' => 480, 'resolution_time_minutes' => 2880, 'warning_threshold_percent' => 80, 'status' => 'active',
        ]);
        $ticket = Ticket::create([
            'ticket_no' => 'INC-UJI-2026-'.random_int(1000, 9999),
            'title' => 'Tidak bisa release vendor', 'requester_name' => 'Andi Requester', 'status' => 'Open', 'priority' => 'Medium',
            'sla_policy_id' => $policy->id, 'service_name' => 'ELISA', 'service_catalog_service_id' => $service->id,
            'catalog_subject_id' => $subject->id, 'assigned_agent_id' => $bpo->id,
            'response_time_minutes' => 480, 'resolution_time_minutes' => 2880, 'warning_threshold_percent' => 80,
            'response_due_at' => $now->clone()->addHours(8), 'resolution_due_at' => $now->clone()->addDays(2), 'warning_at' => $now->clone()->addDays(1),
        ]);

        $this->actingAs(User::find($bpo->user_id))
            ->postJson(route('support-bpo.tickets.escalate', $ticket), ['note' => 'perlu IT'])
            ->assertOk();

        $ticket->refresh();
        $this->assertSame($it->id, $ticket->assigned_agent_id, 'Subject punya it_agent_id spesifik -> langsung ke situ, bukan broadcast null.');
    }
}
