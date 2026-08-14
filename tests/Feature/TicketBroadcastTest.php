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
 * Tiket "Lainnya" (Layanan dipilih, tapi tidak ada Subcategory yang cocok)
 * dilempar ke SEMUA PIC BPO yang SUDAH tertaut ke Subject-Subject aktif
 * Layanan itu — bukan daftar terpisah, sengaja sama persis dengan yang
 * kelihatan di tabel Service Catalog (lihat ServiceCatalogService::
 * activeBpoAgents()). Siapa pun yang pertama bertindak otomatis
 * mengklaimnya, dan PIC lain diberi tahu — lihat App\Support\TicketBroadcast.
 */
class TicketBroadcastTest extends TestCase
{
    use RefreshDatabase;

    private function bpoAgent(string $name): SupportAgent
    {
        $role = Role::firstOrCreate(['name' => 'Support BPO']);
        $user = User::factory()->create(['name' => $name, 'status' => 'active', 'helpdesk_access' => 'enabled']);
        $user->roles()->attach($role->id);

        return SupportAgent::create(['name' => $name, 'type' => 'bpo', 'is_active' => true, 'user_id' => $user->id]);
    }

    /**
     * Layanan CCM dengan satu Subject aktif PER agent yang diberikan — jadi
     * mereka jadi PIC broadcast lewat jalur yang sama dengan yang sungguhan
     * dipakai (support_agent_id di ServiceCatalogSubject), bukan lewat
     * daftar terpisah.
     */
    private function serviceWithPics(array $agents): ServiceCatalogService
    {
        $issueCategory = IssueCategory::firstOrCreate(['name' => 'Service Request']);
        $service = ServiceCatalogService::firstOrCreate(['name' => 'CCM']);
        $subcategory = ServiceCatalogSubcategory::create(['service_id' => $service->id, 'name' => 'Kendala Aplikasi']);

        foreach ($agents as $agent) {
            ServiceCatalogSubject::create([
                'issue_category_id' => $issueCategory->id, 'service_id' => $service->id, 'subcategory_id' => $subcategory->id,
                'name' => 'Subject '.$agent->name, 'requires_approval' => false,
                'support_agent_id' => $agent->id, 'support_level' => 1, 'is_active' => true,
            ]);
        }

        return $service;
    }

    private function broadcastTicket(ServiceCatalogService $service, array $attributes = []): Ticket
    {
        $now = Carbon::now();
        $policy = SlaPolicy::create([
            'policy_name' => 'Uji Broadcast', 'priority' => 'Medium', 'service_type' => 'Incident',
            'response_time_minutes' => 480, 'resolution_time_minutes' => 2880, 'warning_threshold_percent' => 80, 'status' => 'active',
        ]);

        return Ticket::create(array_merge([
            'ticket_no' => 'INC-UJI-2026-'.random_int(1000, 9999),
            'title' => 'Kendala CCM',
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
        ], $attributes));
    }

    public function test_eligible_pics_kosong_untuk_tiket_katalog_biasa(): void
    {
        $agent = $this->bpoAgent('Rian');
        $service = $this->serviceWithPics([$agent]);
        $subject = ServiceCatalogSubject::where('service_id', $service->id)->firstOrFail();

        // Tiket katalog biasa: subcategory-nya cocok, bukan "Lainnya".
        $ticket = $this->broadcastTicket($service, ['catalog_subject_id' => $subject->id]);

        $this->assertTrue(TicketBroadcast::eligiblePics($ticket->fresh())->isEmpty());
    }

    public function test_pic_dari_subject_nonaktif_tidak_ikut_broadcast(): void
    {
        $agentAktif = $this->bpoAgent('Rian');
        $agentNonaktif = $this->bpoAgent('Nonaktif');
        $service = $this->serviceWithPics([$agentAktif, $agentNonaktif]);
        ServiceCatalogSubject::where('support_agent_id', $agentNonaktif->id)->update(['is_active' => false]);

        $ticket = $this->broadcastTicket($service);
        $names = TicketBroadcast::eligiblePics($ticket->fresh())->pluck('name');

        $this->assertTrue($names->contains('Rian'));
        $this->assertFalse($names->contains('Nonaktif'));
    }

    public function test_dua_pic_sama_sama_berhak_atas_tiket_yang_belum_diklaim(): void
    {
        $agentA = $this->bpoAgent('Rian');
        $agentB = $this->bpoAgent('Sari');
        $service = $this->serviceWithPics([$agentA, $agentB]);
        $ticket = $this->broadcastTicket($service);

        $this->assertTrue(TicketBroadcast::canAct($ticket, $agentA));
        $this->assertTrue(TicketBroadcast::canAct($ticket, $agentB));
    }

    public function test_agent_di_luar_daftar_pic_tidak_berhak(): void
    {
        $agentA = $this->bpoAgent('Rian');
        $luar = $this->bpoAgent('Bukan PIC');
        $service = $this->serviceWithPics([$agentA]);
        $ticket = $this->broadcastTicket($service);

        $this->assertFalse(TicketBroadcast::canAct($ticket, $luar));
    }

    public function test_klaim_otomatis_lewat_balasan_pertama_memberitahu_pic_lain(): void
    {
        $agentA = $this->bpoAgent('Rian');
        $agentB = $this->bpoAgent('Sari');
        $service = $this->serviceWithPics([$agentA, $agentB]);
        $ticket = $this->broadcastTicket($service);

        $this->actingAs(User::find($agentA->user_id))
            ->postJson(route('support-bpo.tickets.comments.store', $ticket), ['message' => 'Saya coba cek dulu.'])
            ->assertCreated();

        $ticket->refresh();
        $this->assertSame($agentA->id, $ticket->assigned_agent_id);

        $notif = TicketNotification::where('user_id', $agentB->user_id)
            ->where('type', 'ticket_claimed_by_other')
            ->first();
        $this->assertNotNull($notif);
        $this->assertStringContainsString('Rian', $notif->message);
    }

    public function test_pic_lain_kehilangan_akses_setelah_diklaim(): void
    {
        $agentA = $this->bpoAgent('Rian');
        $agentB = $this->bpoAgent('Sari');
        $service = $this->serviceWithPics([$agentA, $agentB]);
        $ticket = $this->broadcastTicket($service);

        TicketBroadcast::claimIfUnclaimed($ticket, User::find($agentA->user_id), $agentA);

        $this->actingAs(User::find($agentB->user_id))
            ->getJson(route('support-bpo.tickets.data', $ticket))
            ->assertForbidden();
    }

    public function test_tiket_broadcast_tampil_di_my_tickets_kedua_pic_sebelum_diklaim(): void
    {
        $agentA = $this->bpoAgent('Rian');
        $agentB = $this->bpoAgent('Sari');
        $service = $this->serviceWithPics([$agentA, $agentB]);
        $ticket = $this->broadcastTicket($service);

        foreach ([$agentA, $agentB] as $agent) {
            $this->actingAs(User::find($agent->user_id))
                ->get(route('support-bpo.tickets'))
                ->assertOk()
                ->assertSee($ticket->ticket_no);
        }
    }
}
