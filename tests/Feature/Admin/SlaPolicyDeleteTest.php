<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\AuditTrail;
use App\Models\SlaPolicy;
use App\Models\Ticket;
use App\Support\PriorityRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsRole;
use Tests\TestCase;

/**
 * Menghapus SLA Policy dari Konsol Admin.
 *
 * Yang dijaga di sini bukan cuma "tombolnya jalan", tapi bahwa tombol itu
 * tidak bisa dipakai untuk merusak tiket: kolom `sla_policy_id` pada tiket
 * adalah foreign key yang tidak boleh kosong, jadi policy yang masih dipakai
 * harus ditolak dengan pesan yang bisa ditindaklanjuti — bukan galat SQL.
 */
final class SlaPolicyDeleteTest extends TestCase
{
    use ActsAsRole;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        PriorityRegistry::flush();
    }

    private function policy(string $priority = 'Critical'): SlaPolicy
    {
        return SlaPolicy::create([
            'policy_name' => $priority.' Response',
            'priority' => $priority,
            'response_time_minutes' => 60,
            'resolution_time_minutes' => 240,
            'escalation_extension_minutes' => 120,
            'warning_threshold_percent' => 80,
            'status' => 'active',
        ]);
    }

    private function ticketUsing(SlaPolicy $policy): Ticket
    {
        return Ticket::create([
            'ticket_no' => 'INC-UJI-2026-'.random_int(1000, 9999),
            'title' => 'Tiket pemakai policy',
            'requester_name' => 'Andi Pratama',
            'status' => 'In Progress',
            'priority' => $policy->priority,
            'sla_policy_id' => $policy->id,
            'response_time_minutes' => 60,
            'resolution_time_minutes' => 240,
            'warning_threshold_percent' => 80,
            'response_due_at' => now()->addHour(),
            'resolution_due_at' => now()->addHours(4),
            'warning_at' => now()->addHours(3),
        ]);
    }

    public function test_policy_yang_tidak_dipakai_bisa_dihapus(): void
    {
        $policy = $this->policy('Sementara');

        $this->actingAsRole('admin');

        $this->deleteJson("/admin/sla-policies/{$policy->id}")
            ->assertOk()
            ->assertJson(['deleted' => true]);

        $this->assertDatabaseMissing('sla_policies', ['id' => $policy->id]);
    }

    public function test_policy_yang_masih_dipakai_tiket_ditolak_dan_tiketnya_selamat(): void
    {
        $policy = $this->policy();
        $ticket = $this->ticketUsing($policy);

        $this->actingAsRole('admin');

        $this->deleteJson("/admin/sla-policies/{$policy->id}")
            ->assertStatus(422)
            ->assertJson(['tickets_using' => 1]);

        // Dua-duanya harus utuh: policy-nya masih ada, tiketnya tidak tersentuh.
        $this->assertDatabaseHas('sla_policies', ['id' => $policy->id]);
        $this->assertDatabaseHas('tickets', ['id' => $ticket->id, 'sla_policy_id' => $policy->id]);
    }

    public function test_pesan_penolakan_menyebut_jumlah_tiket_yang_menahannya(): void
    {
        $policy = $this->policy();
        $this->ticketUsing($policy);
        $this->ticketUsing($policy);
        $this->ticketUsing($policy);

        $this->actingAsRole('admin');

        $response = $this->deleteJson("/admin/sla-policies/{$policy->id}")->assertStatus(422);

        $this->assertSame(3, $response->json('tickets_using'));
        $this->assertStringContainsString('3 tiket', $response->json('message'));
    }

    public function test_penghapusan_tercatat_di_audit_trail(): void
    {
        $policy = $this->policy('Sementara');
        $admin = $this->actingAsRole('admin');

        $this->deleteJson("/admin/sla-policies/{$policy->id}")->assertOk();

        $row = AuditTrail::where('target_type', 'sla_policy')
            ->where('action', 'delete')
            ->latest('id')
            ->first();

        $this->assertNotNull($row, 'Penghapusan SLA Policy tidak tercatat di audit trail.');
        $this->assertStringContainsString('Sementara Response', $row->description);
        $this->assertStringContainsString($admin->name, $row->description);
    }

    public function test_prioritas_ikut_hilang_dari_pemilih_setelah_policy_dihapus(): void
    {
        $this->policy('Critical');
        $sementara = $this->policy('Sementara');
        PriorityRegistry::flush();
        $this->assertContains('Sementara', PriorityRegistry::all());

        $this->actingAsRole('admin');
        $this->deleteJson("/admin/sla-policies/{$sementara->id}")->assertOk();

        PriorityRegistry::flush();
        $this->assertNotContains('Sementara', PriorityRegistry::all());
    }

    public function test_selain_admin_tidak_boleh_menghapus(): void
    {
        $policy = $this->policy('Sementara');

        $this->actingAsRole('requester');

        $this->deleteJson("/admin/sla-policies/{$policy->id}")->assertForbidden();
        $this->assertDatabaseHas('sla_policies', ['id' => $policy->id]);
    }
}
