<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\IssueCategory;
use App\Models\ServiceCatalogService;
use App\Models\ServiceCatalogSubcategory;
use App\Models\ServiceCatalogSubject;
use App\Models\SlaPolicy;
use App\Models\SupportAgent;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsRole;
use Tests\TestCase;

/**
 * Sesudah BPO mengeskalasi, giliran BPO atas tiket itu HABIS — dia masih
 * boleh membukanya dan ikut Forum Diskusi, tapi tidak lagi memulai,
 * menyelesaikan, mengeskalasi, atau mengembalikannya. Aturan itu sudah
 * dipegang daftar tiketnya (visibleTicketsQuery() menuntut escalated_at
 * kosong untuk tiket broadcast), tapi dulu tidak dipegang halaman detailnya.
 *
 * Lubangnya khusus tiket "Lainnya" (tanpa Subject) yang dieskalasi secara
 * broadcast: assigned_agent_id kembali null, dan TicketBroadcast::canAct()
 * lalu jatuh ke daftar PIC — yang sesudah eskalasi berisi PIC *IT*. Untuk
 * orang dobel peran (BPO & IT di Layanan yang sama — pola nyata, lihat
 * TicketEscalateDualRoleTest) daftar itu memuat dirinya sendiri, sehingga
 * layar BPO-nya kembali menganggap tiket itu miliknya: popup "Mulai kerjakan
 * tiket ini?" muncul lagi tiap kali dia membukanya, padahal tiketnya sudah
 * ia serahkan ke IT dan belum tentu IT sudah menekan "Kerjakan Sekarang".
 *
 * Daftar PIC IT tidak pernah boleh jadi sumber wewenang di portal BPO.
 */
class SupportBpoEscalatedAuthorityTest extends TestCase
{
    use ActsAsRole, RefreshDatabase;

    public function test_bpo_dobel_peran_kehilangan_wewenang_di_portal_bpo_sesudah_eskalasi_broadcast(): void
    {
        [$ticket] = $this->escalatedBroadcastTicket();

        $props = $this->get(route('support-bpo.tickets.show', $ticket))->assertOk()->viewData('ticket');

        // Statusnya memang kembali "Open" untuk tahap IT — justru itu sebabnya
        // canManage yang harus menutup popupnya, bukan status.
        $this->assertSame('Open', $props['status']);
        $this->assertTrue($props['escalated']);
        $this->assertFalse($props['canManage']);
    }

    public function test_bpo_dobel_peran_tidak_bisa_memulai_tiket_yang_sudah_dieskalasikannya(): void
    {
        [$ticket] = $this->escalatedBroadcastTicket();

        $this->post(route('support-bpo.tickets.start', $ticket))->assertStatus(403);

        $this->assertSame('Open', $ticket->fresh()->status);
    }

    public function test_bpo_dobel_peran_tidak_bisa_menyelesaikan_atau_mengeskalasi_ulang(): void
    {
        [$ticket] = $this->escalatedBroadcastTicket();

        $this->post(route('support-bpo.tickets.resolve', $ticket), ['note' => 'Sudah beres.'])->assertStatus(403);
        $this->post(route('support-bpo.tickets.escalate', $ticket), ['note' => 'Tolong lanjut.'])->assertStatus(403);
        $this->post(route('support-bpo.tickets.return', $ticket), ['note' => 'Kurang jelas.'])->assertStatus(403);

        $this->assertSame('Open', $ticket->fresh()->status);
    }

    /**
     * Yang HILANG cuma wewenang mengerjakannya. Membuka tiket dan ikut Forum
     * Diskusi tetap boleh — BPO yang mengeskalasi masih pihak yang paling tahu
     * duduk perkaranya, dan tiketnya tetap tercatat di riwayatnya.
     */
    public function test_bpo_pengeskalasi_tetap_bisa_membuka_dan_ikut_forum_diskusi(): void
    {
        [$ticket] = $this->escalatedBroadcastTicket();

        $this->get(route('support-bpo.tickets.show', $ticket))->assertOk();
        $this->post(route('support-bpo.tickets.comments.store', $ticket), ['message' => 'Konteks tambahan untuk tim IT.'])
            ->assertCreated();

        // Komentarnya TIDAK boleh ikut mengklaim tiketnya kembali:
        // claimIfUnclaimed() menimbang agent BPO ini terhadap daftar PIC IT
        // (tiket broadcast yang sudah dieskalasi kembali assigned_agent_id
        // null), jadi tanpa penjagaan, sekadar berkomentar akan menarik tiket
        // yang sudah diserahkan balik ke portal BPO.
        $this->assertNull($ticket->fresh()->assigned_agent_id);
    }

    /**
     * Tiket "Lainnya" Layanan ERISKA yang baru saja dieskalasi BPO ke IT lewat
     * jalur broadcast — dan orang yang mengeskalasinya juga PIC IT Layanan
     * yang sama, persis pola yang memunculkan bugnya.
     *
     * Eskalasinya dijalankan lewat endpoint sungguhan, bukan update kolom
     * manual, supaya yang diuji adalah keadaan yang benar-benar ditinggalkan
     * SupportBpoController::escalate().
     *
     * @return array{0:Ticket,1:User}
     */
    private function escalatedBroadcastTicket(): array
    {
        $user = User::factory()->create(['name' => 'Arief Kurniawan', 'status' => 'active', 'helpdesk_access' => 'enabled']);
        $this->actingAsUserWithRoles($user, 'support-bpo', 'support');

        $bpoAgent = SupportAgent::create(['name' => $user->name, 'type' => 'bpo', 'is_active' => true, 'user_id' => $user->id]);
        $itAgent = SupportAgent::create(['name' => $user->name, 'type' => 'it', 'is_active' => true, 'user_id' => $user->id]);

        // PIC IT kedua supaya broadcast tetap punya tujuan lain — tanpa ini
        // tesnya lolos hanya karena tidak ada IT yang bisa menerimanya.
        $otherIt = SupportAgent::create(['name' => 'Febria Sahrina', 'type' => 'it', 'is_active' => true]);

        $issueCategory = IssueCategory::firstOrCreate(['name' => 'Incident']);
        $service = ServiceCatalogService::create(['name' => 'ERISKA']);
        $subcategory = ServiceCatalogSubcategory::create(['service_id' => $service->id, 'name' => 'Manajemen Risiko']);

        foreach ([$itAgent, $otherIt] as $it) {
            ServiceCatalogSubject::create([
                'issue_category_id' => $issueCategory->id, 'service_id' => $service->id, 'subcategory_id' => $subcategory->id,
                'name' => 'Subject '.$it->id, 'requires_approval' => false,
                'support_agent_id' => $bpoAgent->id, 'it_agent_id' => $it->id, 'support_level' => 2, 'is_active' => true,
            ]);
        }

        $policy = SlaPolicy::create([
            'policy_name' => 'Uji Eskalasi', 'priority' => 'Medium', 'service_type' => 'Incident',
            'response_time_minutes' => 480, 'resolution_time_minutes' => 2880,
            'warning_threshold_percent' => 80, 'status' => 'active',
        ]);

        // catalog_subject_id null = tiket "Lainnya" — inilah yang membuat
        // eskalasinya lewat jalur broadcast, bukan satu agent IT tertentu.
        $ticket = Ticket::create([
            'ticket_no' => 'AR-ERISKA-2026-0006',
            'title' => 'Tidak bisa membuka modul risiko',
            'requester_name' => 'Andi Pratama',
            'status' => 'In Progress',
            'priority' => 'Medium',
            'service_catalog_service_id' => $service->id,
            'catalog_subject_id' => null,
            'assigned_agent_id' => $bpoAgent->id,
            'sla_policy_id' => $policy->id,
            'response_time_minutes' => 480,
            'resolution_time_minutes' => 2880,
            'warning_threshold_percent' => 80,
            'response_due_at' => now()->addHours(8),
            'resolution_due_at' => now()->addDays(2),
            'warning_at' => now()->addDay(),
        ]);

        $this->post(route('support-bpo.tickets.escalate', $ticket), ['note' => 'Perlu penanganan tim IT.'])
            ->assertOk()
            ->assertJson(['escalated' => true]);

        $ticket = $ticket->fresh();
        $this->assertNull($ticket->assigned_agent_id, 'Eskalasi broadcast harus melepas assigned_agent_id.');
        $this->assertNotNull($ticket->escalated_at);

        return [$ticket, $user];
    }
}
