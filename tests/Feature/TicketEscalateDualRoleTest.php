<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Role;
use App\Models\ServiceCatalogService;
use App\Models\SlaPolicy;
use App\Models\SupportAgent;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * SupportBpoController::escalate() untuk tiket tanpa Subject (mis. hasil
 * broadcast "Lainnya") harus jatuh ke agent IT AKTIF MANA PUN — bukan ke
 * baris SupportAgent milik orang yang sedang mengeskalasi itu sendiri.
 *
 * Bug yang terbukti di data nyata: seorang agent dobel peran (BPO & IT,
 * dua baris SupportAgent untuk satu akun yang sama — mis. Arief Kurniawan)
 * mengeskalasi tiket "Lainnya", dan assigned_agent_id-nya malah jadi baris
 * BPO-nya sendiri (bukan baris IT), karena fallback lama salah memanggil
 * agentFor() milik controller BPO. Akibatnya tiket itu tidak bisa dibuka
 * lewat portal IT (403) dan tidak masuk cakupan tim Team Lead (dianggap
 * "tidak ditemukan").
 */
class TicketEscalateDualRoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_eskalasi_tiket_lainnya_oleh_agent_dobel_peran_jatuh_ke_baris_it_bukan_bpo(): void
    {
        $roleBpo = Role::firstOrCreate(['name' => 'Support BPO']);
        $roleIt = Role::firstOrCreate(['name' => 'Support IT']);

        $user = User::factory()->create(['name' => 'Arief Kurniawan', 'status' => 'active', 'helpdesk_access' => 'enabled']);
        $user->roles()->attach([$roleBpo->id, $roleIt->id]);

        $bpoAgent = SupportAgent::create(['name' => 'Arief Kurniawan', 'type' => 'bpo', 'is_active' => true, 'user_id' => $user->id]);
        $itAgent = SupportAgent::create(['name' => 'Arief Kurniawan', 'type' => 'it', 'is_active' => true, 'user_id' => $user->id]);

        $service = ServiceCatalogService::create(['name' => 'SAP']);
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
            'catalog_subject_id' => null, // "Lainnya" — tidak ada Subject buat cari it_agent_id
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

        $this->assertSame($itAgent->id, $ticket->assigned_agent_id, 'Harus jatuh ke baris IT, bukan baris BPO milik orang yang sama.');
        $this->assertNotSame($bpoAgent->id, $ticket->assigned_agent_id);
    }
}
