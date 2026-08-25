<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\SlaPolicy;
use App\Models\SupportAgent;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Kolom PIC pada Ticket Management diturunkan dari agen yang didaftarkan pada
 * subjek katalog. Tiket yang dibuat tanpa subjek katalog — aplikasi
 * mengizinkannya, layanan "Lainnya (belum ada di katalog)" ada di dashboard —
 * karena itu selalu tampil "Menunggu Assignment" walau petugasnya jelas ada
 * dan tiketnya sudah ditutup. Ditemukan saat UAT test case 15: 23 dari 48
 * tiket terdampak.
 */
final class TicketManagementPicFallbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_tiket_tanpa_subjek_katalog_menampilkan_petugas_yang_menangani(): void
    {
        $agen = SupportAgent::create([
            'user_id' => User::factory()->create(['name' => 'Febria Sahrina'])->id,
            'name' => 'Febria Sahrina',
            'email' => 'febria.pic@adhikarya-helpdesk.test',
            'type' => 'it',
            'is_active' => true,
        ]);
        $tiket = $this->buatTiket(assignedAgentId: $agen->id);

        $baris = $this->barisTicketManagement($tiket->ticket_no);

        $this->assertSame('Febria Sahrina', $baris['pic']);
        $this->assertContains('Febria Sahrina', $baris['picNames']);
    }

    public function test_tanpa_subjek_katalog_dan_tanpa_petugas_tetap_menunggu_assignment(): void
    {
        $tiket = $this->buatTiket(assignedAgentId: null);

        $baris = $this->barisTicketManagement($tiket->ticket_no);

        $this->assertNull($baris['pic']);
        $this->assertSame([], $baris['picNames']);
    }

    private function barisTicketManagement(string $ticketNo): array
    {
        $admin = User::factory()->create(['email' => 'admin.pic@adhi.co.id']);
        $admin->roles()->attach(Role::firstOrCreate(['slug' => 'administrator'], ['name' => 'Administrator'])->id);

        $response = $this->actingAs($admin)->get('/admin/ticket-management');
        $response->assertOk();

        $tickets = $response->viewData('tickets');
        $baris = collect($tickets)->firstWhere('ticketNo', $ticketNo);

        $this->assertNotNull($baris, "Tiket {$ticketNo} tidak ada pada daftar Ticket Management");

        return $baris;
    }

    private function buatTiket(?int $assignedAgentId): Ticket
    {
        $mulai = Carbon::now()->subDays(3);

        return Ticket::create([
            'ticket_no' => 'INC-UJI-2026-'.random_int(1000, 9999),
            'title' => 'Tiket tanpa subjek katalog',
            'requester_id' => User::factory()->create(['name' => 'Andi Pratama'])->id,
            'requester_name' => 'Andi Pratama',
            'status' => 'Closed',
            'priority' => 'Critical',
            'issue_category' => 'Incident',
            'catalog_subject_id' => null,
            'assigned_agent_id' => $assignedAgentId,
            'sla_policy_id' => SlaPolicy::create([
                'policy_name' => 'Uji PIC '.random_int(1000, 9999),
                'priority' => 'Critical',
                'response_time_minutes' => 60,
                'resolution_time_minutes' => 240,
                'warning_threshold_percent' => 80,
                'status' => 'active',
            ])->id,
            'response_time_minutes' => 60,
            'resolution_time_minutes' => 240,
            'warning_threshold_percent' => 80,
            'response_due_at' => $mulai->clone()->addHour(),
            'resolution_due_at' => $mulai->clone()->addHours(4),
            'warning_at' => $mulai->clone()->addHours(3),
            'created_at' => $mulai,
            'resolved_at' => $mulai->clone()->addHours(5),
        ]);
    }
}
