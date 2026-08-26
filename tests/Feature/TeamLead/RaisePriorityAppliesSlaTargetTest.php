<?php

declare(strict_types=1);

namespace Tests\Feature\TeamLead;

use App\Models\AuditTrail;
use App\Models\Role;
use App\Models\SlaPolicy;
use App\Models\SupportAgent;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use App\Support\PriorityRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\ActsAsRole;
use Tests\TestCase;

/**
 * Menaikkan prioritas adalah tindakan korektif: Team Lead melakukannya justru
 * supaya tiketnya ditangani lebih cepat. Sebelum perbaikan ini yang berubah
 * hanya labelnya — target SLA tiket tetap milik prioritas lama, jadi tenggatnya
 * tidak ikut mengetat sama sekali. Team Lead mengira sudah mempercepat
 * penanganan, padahal tidak ada yang berubah selain satu kata di layar.
 *
 * Tenggat baru dihitung dari sla_started_at, bukan dari sekarang. Kalau
 * dihitung dari sekarang, menaikkan prioritas justru memberi tim jam kerja
 * baru dan menyembunyikan keterlambatan yang sudah terjadi — tiket yang
 * seharusnya sudah breach malah tampak segar.
 *
 * Berbeda dari mengubah SLA Policy (lihat SlaChangeAffectsRunningTicketTest,
 * yang sengaja TIDAK memajukan tenggat tiket berjalan): di sana yang berubah
 * aturannya untuk semua orang, di sini yang berubah penggolongan satu tiket
 * ini saja, atas keputusan sadar seorang Team Lead.
 *
 * Ditemukan saat UAT test case 34.
 */
final class RaisePriorityAppliesSlaTargetTest extends TestCase
{
    use ActsAsRole, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        PriorityRegistry::flush();
    }

    public function test_target_sla_ikut_memakai_policy_prioritas_baru(): void
    {
        [$ticket, $urgent] = $this->skenario();
        $this->actingAsRole('team-lead');

        $this->naikkan($ticket, 'Urgent')->assertOk();

        $ticket = $ticket->fresh();

        $this->assertSame('Urgent', $ticket->priority);
        $this->assertSame($urgent->id, $ticket->sla_policy_id);
        $this->assertSame($urgent->response_time_minutes, $ticket->response_time_minutes);
        $this->assertSame($urgent->resolution_time_minutes, $ticket->resolution_time_minutes);
    }

    public function test_tenggat_dihitung_ulang_dari_waktu_tiket_mulai_berjalan(): void
    {
        [$ticket, $urgent, $mulai] = $this->skenario();
        $this->actingAsRole('team-lead');

        $this->naikkan($ticket, 'Urgent')->assertOk();

        $ticket = $ticket->fresh();

        $this->assertSame(
            $mulai->clone()->addMinutes($urgent->resolution_time_minutes)->toDateTimeString(),
            $ticket->resolution_due_at->toDateTimeString()
        );
        $this->assertSame(
            $mulai->clone()->addMinutes($urgent->response_time_minutes)->toDateTimeString(),
            $ticket->response_due_at->toDateTimeString()
        );
        $this->assertSame(
            $mulai->clone()->addMinutes((int) round($urgent->resolution_time_minutes * $urgent->warning_threshold_percent / 100))->toDateTimeString(),
            $ticket->warning_at->toDateTimeString()
        );
    }

    public function test_perubahan_prioritas_tercatat_pada_riwayat_tiket(): void
    {
        [$ticket, , ] = $this->skenario();
        $lead = $this->actingAsRole('team-lead');

        $this->naikkan($ticket, 'Urgent')->assertOk();

        $catatan = TicketComment::where('ticket_id', $ticket->id)->latest('id')->first();

        $this->assertNotNull($catatan, 'perubahan prioritas tidak muncul di riwayat tiket');
        $this->assertSame($lead->name, $catatan->author_name);
        $this->assertStringContainsString('Medium', (string) $catatan->message);
        $this->assertStringContainsString('Urgent', (string) $catatan->message);
    }

    public function test_perubahan_tetap_tercatat_pada_jejak_audit(): void
    {
        [$ticket, , ] = $this->skenario();
        $lead = $this->actingAsRole('team-lead');

        $this->naikkan($ticket, 'Urgent')->assertOk();

        $jejak = AuditTrail::where('action', 'raise_priority')->latest('id')->first();

        $this->assertNotNull($jejak);
        $this->assertSame($lead->id, $jejak->actor_id);
        $this->assertSame('Medium', $jejak->old_value['priority'] ?? null);
        $this->assertSame('Urgent', $jejak->new_value['priority'] ?? null);
    }

    /**
     * Prioritas tanpa policy aktif tidak boleh menghapus target yang sedang
     * berjalan — lebih baik ditolak daripada meninggalkan tiket tanpa tenggat.
     */
    public function test_prioritas_tanpa_policy_aktif_ditolak(): void
    {
        [$ticket, , ] = $this->skenario();
        $this->actingAsRole('team-lead');

        $this->naikkan($ticket, 'Tidak Ada Policy')->assertStatus(422);

        $this->assertSame('Medium', $ticket->fresh()->priority);
    }

    private function naikkan(Ticket $ticket, string $priority)
    {
        return $this->postJson(route('team-lead.tickets.raise-priority', $ticket), ['priority' => $priority]);
    }

    /** @return array{0:Ticket,1:SlaPolicy,2:Carbon} */
    private function skenario(): array
    {
        $agent = $this->agent('Arief Kurniawan');

        $medium = SlaPolicy::create([
            'policy_name' => 'Medium Standard', 'priority' => 'Medium', 'service_type' => 'Incident',
            'response_time_minutes' => 480, 'resolution_time_minutes' => 2880,
            'warning_threshold_percent' => 75, 'status' => 'active',
        ]);

        // Prioritas buatan Administrator, lebih ketat daripada Medium.
        $urgent = SlaPolicy::create([
            'policy_name' => 'Urgent Uji UAT', 'priority' => 'Urgent', 'service_type' => 'Incident',
            'response_time_minutes' => 30, 'resolution_time_minutes' => 180,
            'warning_threshold_percent' => 80, 'status' => 'active',
        ]);

        PriorityRegistry::flush();

        $mulai = Carbon::now()->subHours(6);

        $ticket = Ticket::create([
            'ticket_no' => 'INC-UJI-2026-'.random_int(1000, 9999),
            'title' => 'User Locked', 'requester_name' => 'Andi Pratama',
            'status' => 'Open', 'priority' => 'Medium',
            'sla_policy_id' => $medium->id, 'service_name' => 'SAP',
            'assigned_agent_id' => $agent->id,
            'response_time_minutes' => $medium->response_time_minutes,
            'resolution_time_minutes' => $medium->resolution_time_minutes,
            'warning_threshold_percent' => $medium->warning_threshold_percent,
            'sla_started_at' => $mulai,
            'response_due_at' => $mulai->clone()->addMinutes($medium->response_time_minutes),
            'resolution_due_at' => $mulai->clone()->addMinutes($medium->resolution_time_minutes),
            'warning_at' => $mulai->clone()->addMinutes(2160),
        ]);

        return [$ticket, $urgent, $mulai];
    }

    private function agent(string $name): SupportAgent
    {
        $role = Role::firstOrCreate(['name' => 'Support IT']);
        $user = User::factory()->create(['name' => $name, 'status' => 'active', 'helpdesk_access' => 'enabled']);
        $user->roles()->attach($role->id);

        return SupportAgent::create([
            'name' => $name, 'type' => 'it', 'is_active' => true, 'user_id' => $user->id,
        ])->load('user');
    }
}
