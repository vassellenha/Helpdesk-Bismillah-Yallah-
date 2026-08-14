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
use App\Models\User;
use App\Support\TicketBroadcast;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Eskalasi tiket "Lainnya" oleh/ke agent dobel peran (BPO & IT, dua baris
 * SupportAgent untuk satu akun yang sama — mis. Arief Kurniawan) harus
 * mewakili orangnya lewat baris IT-nya, bukan baris BPO-nya.
 *
 * Bug yang terbukti di data nyata: fallback lama salah memanggil agentFor()
 * milik controller BPO untuk mencari "agent IT default", jadi tiket yang
 * "dieskalasi ke IT" malah tetap assigned ke baris BPO orang itu sendiri.
 * Akibatnya tiket tidak bisa dibuka lewat portal IT (403) dan tidak masuk
 * cakupan tim Team Lead (dianggap "tidak ditemukan").
 *
 * Sejak eskalasi tiket "Lainnya" jadi broadcast ke semua PIC IT Layanan
 * (bukan satu target), test ini memeriksa lewat eligiblePics(): baris IT
 * si agent dobel peran yang harus muncul, baris BPO-nya tidak boleh.
 */
class TicketEscalateDualRoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_eskalasi_tiket_lainnya_mewakili_agent_dobel_peran_lewat_baris_it_bukan_bpo(): void
    {
        $roleBpo = Role::firstOrCreate(['name' => 'Support BPO']);
        $roleIt = Role::firstOrCreate(['name' => 'Support IT']);

        $user = User::factory()->create(['name' => 'Arief Kurniawan', 'status' => 'active', 'helpdesk_access' => 'enabled']);
        $user->roles()->attach([$roleBpo->id, $roleIt->id]);

        $bpoAgent = SupportAgent::create(['name' => 'Arief Kurniawan', 'type' => 'bpo', 'is_active' => true, 'user_id' => $user->id]);
        $itAgent = SupportAgent::create(['name' => 'Arief Kurniawan', 'type' => 'it', 'is_active' => true, 'user_id' => $user->id]);

        $issueCategory = IssueCategory::firstOrCreate(['name' => 'Incident']);
        $service = ServiceCatalogService::create(['name' => 'SAP']);
        $subcategory = ServiceCatalogSubcategory::create(['service_id' => $service->id, 'name' => 'Login SAP']);
        // Subject ini yang membuat Arief (lewat baris IT-nya) jadi PIC IT
        // Layanan SAP — sumber activeItAgents(), bukan daftar terpisah.
        ServiceCatalogSubject::create([
            'issue_category_id' => $issueCategory->id, 'service_id' => $service->id, 'subcategory_id' => $subcategory->id,
            'name' => 'Password Expired', 'requires_approval' => false,
            'support_agent_id' => $bpoAgent->id, 'it_agent_id' => $itAgent->id, 'support_level' => 2, 'is_active' => true,
        ]);

        $policy = SlaPolicy::create([
            'policy_name' => 'Uji Eskalasi', 'priority' => 'Critical', 'service_type' => 'Incident',
            'response_time_minutes' => 60, 'resolution_time_minutes' => 240, 'warning_threshold_percent' => 80, 'status' => 'active',
        ]);

        $now = Carbon::now();
        $ticket = Ticket::create([
            'ticket_no' => 'INC-UJI-2026-'.random_int(1000, 9999),
            'title' => 'tolong benerin',
            'requester_name' => 'Andi Requester',
            'status' => 'Open',
            'priority' => 'Critical',
            'sla_policy_id' => $policy->id,
            'service_name' => 'SAP',
            'service_catalog_service_id' => $service->id,
            'catalog_subject_id' => null, // "Lainnya" — tidak ada Subject spesifik buat tiket ini sendiri
            'assigned_agent_id' => $bpoAgent->id, // sudah diklaim si BPO
            'response_time_minutes' => 60,
            'resolution_time_minutes' => 240,
            'warning_threshold_percent' => 80,
            'response_due_at' => $now->clone()->addHour(),
            'resolution_due_at' => $now->clone()->addHours(4),
            'warning_at' => $now->clone()->addHours(3),
        ]);

        $this->actingAs($user)
            ->postJson(route('support-bpo.tickets.escalate', $ticket), ['note' => 'perlu IT'])
            ->assertOk();

        $ticket->refresh();

        $this->assertNull($ticket->assigned_agent_id, 'Broadcast ke IT — belum diklaim siapa pun, bukan langsung di-assign.');
        $this->assertNotNull($ticket->escalated_at);

        $picIds = TicketBroadcast::eligiblePics($ticket)->pluck('id');
        $this->assertTrue($picIds->contains($itAgent->id), 'Baris IT Arief harus muncul sebagai PIC.');
        $this->assertFalse($picIds->contains($bpoAgent->id), 'Baris BPO Arief tidak boleh muncul di pool PIC IT.');
    }
}
