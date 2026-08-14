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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Sebuah tiket yang sudah dieskalasi BPO -> IT, lalu IT mengembalikannya ke
 * requester untuk klarifikasi, harus BALIK ke IT begitu requester mengirim
 * ulang — bukan reset ke slot BPO default Subject-nya. TicketController::
 * update() dulu selalu memanggil resolveAssignedAgentId() tanpa syarat, yang
 * cuma pernah mengembalikan slot BPO (support_agent_id ?? it_agent_id, BPO
 * duluan), jadi eskalasi yang sudah terjadi hilang begitu tiket dikirim ulang.
 */
class TicketReturnRoutingTest extends TestCase
{
    use RefreshDatabase;

    private function requesterUser(): User
    {
        $role = Role::firstOrCreate(['name' => 'Requester']);
        $user = User::factory()->create(['status' => 'active', 'helpdesk_access' => 'enabled']);
        $user->roles()->attach($role->id);

        return $user;
    }

    private function subjectWithBpoAndIt(): array
    {
        $bpo = SupportAgent::create(['name' => 'Rian', 'type' => 'bpo', 'is_active' => true]);
        $it = SupportAgent::create(['name' => 'Aditya', 'type' => 'it', 'is_active' => true]);

        $issueCategory = IssueCategory::firstOrCreate(['name' => 'Incident']);
        $service = ServiceCatalogService::create(['name' => 'SAP']);
        $subcategory = ServiceCatalogSubcategory::create(['service_id' => $service->id, 'name' => 'LOGIN SAP']);
        $subject = ServiceCatalogSubject::create([
            'issue_category_id' => $issueCategory->id, 'service_id' => $service->id, 'subcategory_id' => $subcategory->id,
            'name' => 'Password Expired', 'requires_approval' => false,
            'support_agent_id' => $bpo->id, 'it_agent_id' => $it->id, 'support_level' => 2, 'is_active' => true,
        ]);

        return [$subject, $bpo, $it];
    }

    public function test_tiket_yang_dieskalasi_lalu_returned_tetap_di_it_setelah_dikirim_ulang(): void
    {
        [$subject, $bpo, $it] = $this->subjectWithBpoAndIt();
        $requester = $this->requesterUser();

        $policy = SlaPolicy::create([
            'policy_name' => 'Uji Return', 'priority' => 'Medium', 'service_type' => 'Incident',
            'response_time_minutes' => 480, 'resolution_time_minutes' => 2880, 'warning_threshold_percent' => 80, 'status' => 'active',
        ]);

        $now = Carbon::now();
        $ticket = Ticket::create([
            'ticket_no' => 'INC-UJI-2026-'.random_int(1000, 9999),
            'title' => 'Password Expired',
            'requester_name' => $requester->name,
            'requester_id' => $requester->id,
            'status' => 'Returned', // IT sudah mengembalikan tiket ini ke requester
            'priority' => 'Medium',
            'sla_policy_id' => $policy->id,
            'service_name' => 'SAP',
            'subcategory_name' => 'LOGIN SAP',
            'subject_name' => 'Password Expired',
            'catalog_subject_id' => $subject->id,
            'assigned_agent_id' => $it->id, // sedang di tangan IT (hasil eskalasi), bukan BPO
            'response_time_minutes' => 480,
            'resolution_time_minutes' => 2880,
            'warning_threshold_percent' => 80,
            'response_due_at' => $now->clone()->addHours(8),
            'resolution_due_at' => $now->clone()->addDays(2),
            'warning_at' => $now->clone()->addDays(1),
        ]);

        $response = $this->actingAs($requester)->putJson(route('requester.tickets.update', $ticket), [
            'title' => 'Password Expired',
            'sla_policy_id' => $policy->id,
            'service_name' => 'SAP',
            'subcategory_name' => 'LOGIN SAP',
            'subject_name' => 'Password Expired',
            'issue_category' => 'Incident',
            'description' => 'Sudah saya coba lagi, masih gagal.',
            'catalog_subject_id' => $subject->id,
            'requires_approval' => false,
            'is_draft' => false,
        ]);

        $response->assertOk();
        $ticket->refresh();

        $this->assertSame($it->id, $ticket->assigned_agent_id, 'Tiket harus tetap di IT, bukan reset ke slot BPO default.');
        $this->assertSame('Open', $ticket->status);
    }

    public function test_tiket_returned_yang_ganti_kategori_dihitung_ulang_dari_kategori_baru(): void
    {
        [$subjectLama, $bpoLama, $itLama] = $this->subjectWithBpoAndIt();
        $requester = $this->requesterUser();

        $bpoBaru = SupportAgent::create(['name' => 'Sari', 'type' => 'bpo', 'is_active' => true]);
        $issueCategory = IssueCategory::firstOrCreate(['name' => 'Incident']);
        $serviceBaru = ServiceCatalogService::create(['name' => 'VPN']);
        $subcategoryBaru = ServiceCatalogSubcategory::create(['service_id' => $serviceBaru->id, 'name' => 'Akses VPN']);
        $subjectBaru = ServiceCatalogSubject::create([
            'issue_category_id' => $issueCategory->id, 'service_id' => $serviceBaru->id, 'subcategory_id' => $subcategoryBaru->id,
            'name' => 'Tidak Bisa Konek', 'requires_approval' => false,
            'support_agent_id' => $bpoBaru->id, 'support_level' => 1, 'is_active' => true,
        ]);

        $policy = SlaPolicy::create([
            'policy_name' => 'Uji Return Ganti Kategori', 'priority' => 'Medium', 'service_type' => 'Incident',
            'response_time_minutes' => 480, 'resolution_time_minutes' => 2880, 'warning_threshold_percent' => 80, 'status' => 'active',
        ]);

        $now = Carbon::now();
        $ticket = Ticket::create([
            'ticket_no' => 'INC-UJI-2026-'.random_int(1000, 9999),
            'title' => 'Salah kategori',
            'requester_name' => $requester->name,
            'requester_id' => $requester->id,
            'status' => 'Returned',
            'priority' => 'Medium',
            'sla_policy_id' => $policy->id,
            'catalog_subject_id' => $subjectLama->id,
            'assigned_agent_id' => $itLama->id,
            'response_time_minutes' => 480,
            'resolution_time_minutes' => 2880,
            'warning_threshold_percent' => 80,
            'response_due_at' => $now->clone()->addHours(8),
            'resolution_due_at' => $now->clone()->addDays(2),
            'warning_at' => $now->clone()->addDays(1),
        ]);

        $this->actingAs($requester)->putJson(route('requester.tickets.update', $ticket), [
            'title' => 'Ternyata masalah VPN',
            'sla_policy_id' => $policy->id,
            'service_name' => 'VPN',
            'subcategory_name' => 'Akses VPN',
            'subject_name' => 'Tidak Bisa Konek',
            'issue_category' => 'Incident',
            'catalog_subject_id' => $subjectBaru->id,
            'requires_approval' => false,
            'is_draft' => false,
        ])->assertOk();

        $ticket->refresh();

        $this->assertSame($bpoBaru->id, $ticket->assigned_agent_id, 'Kategori berubah -> harus dirutekan ulang ke PIC kategori baru, bukan dipertahankan ke IT lama.');
    }
}
