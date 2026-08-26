<?php

declare(strict_types=1);

namespace Tests\Feature\TeamLead;

use App\Models\AuditTrail;
use App\Models\Role;
use App\Models\SlaPolicy;
use App\Models\SupportAgent;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\TicketNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\ActsAsRole;
use Tests\TestCase;

/**
 * Pemindahan PIC oleh Team Lead adalah tindakan atas pekerjaan orang lain,
 * jadi tiga hal harus ikut terjadi — dan sebelum perbaikan ini tak satu pun
 * terjadi.
 *
 * Petugas ASAL tidak diberi tahu apa pun: tiketnya lenyap dari daftar kerjanya
 * tanpa penjelasan, dan satu-satunya jejak ada di Audit Trail yang tidak bisa
 * ia buka. Alasan pemindahan tidak pernah diminta maupun disimpan, padahal
 * itulah yang membedakan pemerataan beban dari pengalihan sepihak. Dan riwayat
 * tiket sendiri tetap berbunyi "Belum ada aktivitas" sesudahnya.
 *
 * Ditemukan saat UAT test case 33.
 */
final class ReassignRecordsReasonTest extends TestCase
{
    use ActsAsRole, RefreshDatabase;

    private const ALASAN = 'Pemerataan beban kerja, petugas asal sedang menangani tiket prioritas tinggi.';

    public function test_pemindahan_ditolak_bila_alasan_tidak_diisi(): void
    {
        [$ticket, , $tujuan] = $this->skenario();
        $this->actingAsRole('team-lead');

        $this->postJson(route('team-lead.tickets.reassign', $ticket), ['agent_id' => $tujuan->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors('reason');

        $this->assertSame(
            $ticket->assigned_agent_id,
            $ticket->fresh()->assigned_agent_id,
            'PIC tidak boleh berpindah selama alasannya belum diisi.'
        );
    }

    public function test_petugas_asal_ikut_diberi_tahu(): void
    {
        [$ticket, $asal, $tujuan] = $this->skenario();
        $lead = $this->actingAsRole('team-lead');

        $this->pindahkan($ticket, $tujuan)->assertOk();

        $kabar = TicketNotification::where('user_id', $asal->user_id)->latest('id')->first();

        $this->assertNotNull($kabar, 'petugas asal tidak menerima pemberitahuan apa pun');
        $this->assertStringContainsString($ticket->ticket_no, (string) $kabar->message);
        $this->assertStringContainsString($tujuan->name, (string) $kabar->message);
        $this->assertStringContainsString($lead->name, (string) $kabar->message);
    }

    public function test_petugas_tujuan_tetap_diberi_tahu(): void
    {
        [$ticket, , $tujuan] = $this->skenario();
        $this->actingAsRole('team-lead');

        $this->pindahkan($ticket, $tujuan)->assertOk();

        $kabar = TicketNotification::where('user_id', $tujuan->user_id)->latest('id')->first();

        $this->assertNotNull($kabar);
        $this->assertStringContainsString($ticket->ticket_no, (string) $kabar->message);
    }

    public function test_alasan_tersimpan_pada_riwayat_tiket(): void
    {
        [$ticket, $asal, $tujuan] = $this->skenario();
        $lead = $this->actingAsRole('team-lead');

        $this->pindahkan($ticket, $tujuan)->assertOk();

        $catatan = TicketComment::where('ticket_id', $ticket->id)->latest('id')->first();

        $this->assertNotNull($catatan, 'pemindahan tidak muncul di riwayat tiket');
        $this->assertSame($lead->name, $catatan->author_name);
        $this->assertStringContainsString($asal->name, (string) $catatan->message);
        $this->assertStringContainsString($tujuan->name, (string) $catatan->message);
        $this->assertStringContainsString(self::ALASAN, (string) $catatan->message);
    }

    public function test_alasan_tercatat_pada_jejak_audit(): void
    {
        [$ticket, , $tujuan] = $this->skenario();
        $lead = $this->actingAsRole('team-lead');

        $this->pindahkan($ticket, $tujuan)->assertOk();

        $jejak = AuditTrail::where('action', 'reassign')->latest('id')->first();

        $this->assertNotNull($jejak);
        $this->assertSame($lead->id, $jejak->actor_id);
        $this->assertSame(self::ALASAN, $jejak->new_value['reason'] ?? null);
    }

    private function pindahkan(Ticket $ticket, SupportAgent $tujuan)
    {
        return $this->postJson(route('team-lead.tickets.reassign', $ticket), [
            'agent_id' => $tujuan->id,
            'reason' => self::ALASAN,
        ]);
    }

    /** @return array{0:Ticket,1:SupportAgent,2:SupportAgent} */
    private function skenario(): array
    {
        $asal = $this->agent('Aditya Dwi Nugraha');
        $tujuan = $this->agent('Arief Kurniawan');

        $now = Carbon::now();
        $policy = SlaPolicy::create([
            'policy_name' => 'Uji Pemindahan PIC', 'priority' => 'Medium', 'service_type' => 'Incident',
            'response_time_minutes' => 480, 'resolution_time_minutes' => 2880,
            'warning_threshold_percent' => 80, 'status' => 'active',
        ]);

        $ticket = Ticket::create([
            'ticket_no' => 'INC-UJI-2026-'.random_int(1000, 9999),
            'title' => 'SAP Lambat', 'requester_name' => 'Andi Pratama',
            'status' => 'Open', 'priority' => 'Medium',
            'sla_policy_id' => $policy->id, 'service_name' => 'SAP',
            'assigned_agent_id' => $asal->id,
            'response_time_minutes' => 480, 'resolution_time_minutes' => 2880, 'warning_threshold_percent' => 80,
            'response_due_at' => $now->clone()->addHours(8),
            'resolution_due_at' => $now->clone()->addDays(2),
            'warning_at' => $now->clone()->addDays(1),
        ]);

        return [$ticket, $asal, $tujuan];
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
