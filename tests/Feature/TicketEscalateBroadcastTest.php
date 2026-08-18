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

    /**
     * Varian serviceWithPics() untuk Layanan yang PIC BPO-nya lebih dari satu
     * — tiap Subject punya pasangan [BPO, IT] sendiri, persis seperti data
     * nyata Layanan yang dipegang dua tim.
     *
     * @param  array<int,array{0:SupportAgent,1:SupportAgent}>  $pairs
     */
    private function serviceWithPicPairs(array $pairs): ServiceCatalogService
    {
        $issueCategory = IssueCategory::firstOrCreate(['name' => 'Incident']);
        $service = ServiceCatalogService::create(['name' => 'ELISA']);
        $subcategory = ServiceCatalogSubcategory::create(['service_id' => $service->id, 'name' => 'Vendor Management']);

        foreach ($pairs as [$bpo, $it]) {
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

    /**
     * Notifikasi saja tidak cukup: tiket hasil eskalasi harus BENAR-BENAR
     * muncul di daftar "My Tickets"/dashboard setiap PIC IT sebelum ada yang
     * mengklaimnya. Kalau cuma loncengnya yang bunyi tapi tiketnya tidak ada
     * di daftar, tidak ada yang bisa membukanya lewat UI.
     */
    public function test_tiket_hasil_eskalasi_muncul_di_daftar_semua_pic_it(): void
    {
        $bpoA = $this->agent('Andi Pratama', 'bpo', 'Support BPO');
        $bpoB = $this->agent('Bagus Santoso', 'bpo', 'Support BPO');
        $itA = $this->agent('Agung Wijayanto', 'it', 'Support IT');
        $itB = $this->agent('Aditya Dwi Nugraha', 'it', 'Support IT');
        $service = $this->serviceWithPicPairs([[$bpoA, $itA], [$bpoB, $itB]]);
        $ticket = $this->broadcastTicket($service);

        $this->actingAs(User::find($bpoA->user_id))
            ->postJson(route('support-bpo.tickets.escalate', $ticket), ['note' => 'perlu IT'])
            ->assertOk();

        foreach ([$itA, $itB] as $it) {
            $rows = $this->actingAs(User::find($it->user_id))
                ->get(route('support.tickets'))
                ->assertOk()
                ->viewData('rows');

            $this->assertTrue(
                collect($rows)->contains('id', $ticket->ticket_no),
                "{$it->name} harus melihat tiket {$ticket->ticket_no} di My Tickets."
            );
        }
    }

    /**
     * Tiket yang sudah dilempar ke IT tidak boleh nyangkut di daftar BPO —
     * giliran mereka sudah selesai.
     */
    public function test_tiket_hasil_eskalasi_hilang_dari_daftar_pic_bpo(): void
    {
        $bpoA = $this->agent('Andi Pratama', 'bpo', 'Support BPO');
        $bpoB = $this->agent('Bagus Santoso', 'bpo', 'Support BPO');
        $itA = $this->agent('Agung Wijayanto', 'it', 'Support IT');
        $itB = $this->agent('Aditya Dwi Nugraha', 'it', 'Support IT');
        $service = $this->serviceWithPicPairs([[$bpoA, $itA], [$bpoB, $itB]]);
        $ticket = $this->broadcastTicket($service);

        $this->actingAs(User::find($bpoA->user_id))
            ->postJson(route('support-bpo.tickets.escalate', $ticket), ['note' => 'perlu IT'])
            ->assertOk();

        $rows = $this->actingAs(User::find($bpoB->user_id))
            ->get(route('support-bpo.tickets'))
            ->assertOk()
            ->viewData('rows');

        $this->assertFalse(
            collect($rows)->contains('id', $ticket->ticket_no),
            'Tiket sudah dieskalasi ke IT — tidak boleh muncul lagi di daftar PIC BPO.'
        );
    }

    /**
     * Sebagian orang punya LEBIH DARI SATU baris SupportAgent untuk satu
     * user_id (dobel peran, atau baris lama yang tertinggal). Kalau Subject
     * menunjuk baris yang berbeda dari baris yang dipakai saat login,
     * tiketnya dulu tidak pernah muncul di daftar — padahal loncengnya
     * bunyi, karena notifikasi dicocokkan lewat user_id.
     */
    public function test_pic_it_dengan_baris_agent_ganda_tetap_melihat_tiketnya(): void
    {
        $bpo = $this->agent('Andi Pratama', 'bpo', 'Support BPO');
        $it = $this->agent('Agung Wijayanto', 'it', 'Support IT');

        // Baris kedua untuk orang yang sama — ini yang ditunjuk Subject,
        // sementara login mengembalikan baris pertama.
        $barisLain = SupportAgent::create(['name' => 'Agung W.', 'type' => 'it', 'is_active' => true, 'user_id' => $it->user_id]);

        $service = $this->serviceWithPicPairs([[$bpo, $barisLain]]);
        $ticket = $this->broadcastTicket($service);

        $this->actingAs(User::find($bpo->user_id))
            ->postJson(route('support-bpo.tickets.escalate', $ticket), ['note' => 'perlu IT'])
            ->assertOk();

        $rows = $this->actingAs(User::find($it->user_id))
            ->get(route('support.tickets'))
            ->assertOk()
            ->viewData('rows');

        $this->assertTrue(
            collect($rows)->contains('id', $ticket->ticket_no),
            'Baris SupportAgent ganda tidak boleh menyembunyikan tiket dari PIC IT-nya.'
        );
    }

    /**
     * Layanan yang semua Subject aktifnya Level 1 (BPO-only, it_agent_id
     * kosong) tidak punya PIC IT untuk dituju. Dulu tiketnya tetap
     * di-broadcast: assigned_agent_id null + escalated_at terisi, tanpa satu
     * pun orang yang bisa melihatnya — tiketnya hilang begitu saja.
     */
    public function test_layanan_tanpa_pic_it_tidak_membuat_tiket_hilang(): void
    {
        $bpo = $this->agent('Andi Pratama', 'bpo', 'Support BPO');
        $it = $this->agent('Agung Wijayanto', 'it', 'Support IT');

        $issueCategory = IssueCategory::firstOrCreate(['name' => 'Incident']);
        $service = ServiceCatalogService::create(['name' => 'ELISA']);
        $subcategory = ServiceCatalogSubcategory::create(['service_id' => $service->id, 'name' => 'Vendor Management']);
        ServiceCatalogSubject::create([
            'issue_category_id' => $issueCategory->id, 'service_id' => $service->id, 'subcategory_id' => $subcategory->id,
            'name' => 'Reset password', 'requires_approval' => false,
            'support_agent_id' => $bpo->id, 'it_agent_id' => null, 'support_level' => 1, 'is_active' => true,
        ]);

        $ticket = $this->broadcastTicket($service);

        $this->actingAs(User::find($bpo->user_id))
            ->postJson(route('support-bpo.tickets.escalate', $ticket), ['note' => 'perlu IT'])
            ->assertOk();

        $ticket->refresh();

        $this->assertNotNull($ticket->assigned_agent_id, 'Tidak ada PIC IT untuk broadcast — harus jatuh ke jalur tunggal, bukan dibiarkan tanpa pemilik.');

        $rows = $this->actingAs(User::find($it->user_id))
            ->get(route('support.tickets'))
            ->assertOk()
            ->viewData('rows');

        $this->assertTrue(collect($rows)->contains('id', $ticket->ticket_no));
    }

    /**
     * Tahap IT dimulai dari nol: tiket hasil eskalasi harus kembali "Open"
     * supaya PIC IT mendapat popup "Mulai kerjakan tiket ini?" yang sama
     * dengan tahap BPO, dan tombol Kerjakan Sekarang-nya benar-benar bisa
     * dipakai. Sebelumnya status "In Progress" milik tahap BPO ikut terbawa,
     * jadi tiket tanpa pemilik tampil seolah sedang dikerjakan dan start()
     * menolaknya dengan 422.
     */
    public function test_tiket_hasil_eskalasi_kembali_open_supaya_pic_it_bisa_mulai(): void
    {
        $bpo = $this->agent('Andi Pratama', 'bpo', 'Support BPO');
        $itA = $this->agent('Agung Wijayanto', 'it', 'Support IT');
        $itB = $this->agent('Aditya Dwi Nugraha', 'it', 'Support IT');
        $service = $this->serviceWithPics($bpo, [$itA, $itB]);
        $ticket = $this->broadcastTicket($service);

        // BPO menekan "Kerjakan Sekarang" dulu -> status jadi In Progress.
        $this->actingAs(User::find($bpo->user_id))
            ->postJson(route('support-bpo.tickets.start', $ticket))
            ->assertOk();
        $this->assertSame('In Progress', $ticket->fresh()->status);

        $this->actingAs(User::find($bpo->user_id))
            ->postJson(route('support-bpo.tickets.escalate', $ticket), ['note' => 'perlu IT'])
            ->assertOk();

        $ticket->refresh();
        $this->assertSame('Open', $ticket->status, 'Tahap IT belum dikerjakan siapa pun -> harus Open lagi.');
        $this->assertNull($ticket->assigned_agent_id);

        // Dan tombol "Kerjakan Sekarang" milik PIC IT benar-benar jalan.
        $this->actingAs(User::find($itA->user_id))
            ->postJson(route('support.tickets.start', $ticket))
            ->assertOk();

        $ticket->refresh();
        $this->assertSame('In Progress', $ticket->status);
        $this->assertSame($itA->id, $ticket->assigned_agent_id, 'Menekan Kerjakan Sekarang sekaligus mengklaim tiketnya.');
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
