<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Models\AuditTrail;
use App\Models\SlaPolicy;
use App\Models\SupportAgent;
use App\Models\Ticket;
use App\Models\User;
use App\Support\TicketPeople;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Panel "Orang Terkait" harus menyebut desk seseorang dengan benar — sisa
 * terakhir dari akar masalah yang sama dengan TicketFlow: peran ditebak dengan
 * mencari agen BERDASARKAN NAMA. Orang berperan ganda punya dua baris
 * SupportAgent bernama sama (satu 'bpo', satu 'it'), jadi whereIn('name', …)
 * mengembalikan keduanya dan unique('name') menyimpan yang kebetulan lebih
 * dulu — bisa baris yang salah.
 *
 * Terlihat di produksi pada tiket "Lainnya" (catalog_subject_id null): panel
 * menulis BPO yang mengeskalasi sebagai "Support · IT", padahal Riwayat Status
 * di halaman yang sama sudah menyebutnya BPO dengan benar. Satu halaman, dua
 * jawaban berbeda tentang orang yang sama.
 *
 * Kolom escalated_by_agent_id menyimpan baris agen yang tepat, jadi tidak ada
 * yang perlu ditebak untuk orang itu.
 */
final class TicketPeopleTest extends TestCase
{
    use RefreshDatabase;

    public function test_bpo_pengeskalasi_berperan_ganda_disebut_sebagai_bpo(): void
    {
        $user = User::factory()->create(['name' => 'Dummy SINTA 3']);

        // Baris IT dibuat LEBIH DULU: itulah yang dulu ditemukan duluan oleh
        // pencarian berdasarkan nama, dan membuat labelnya jadi "Support · IT".
        SupportAgent::create(['name' => $user->name, 'type' => 'it', 'is_active' => true, 'user_id' => $user->id]);
        $bpoRow = SupportAgent::create(['name' => $user->name, 'type' => 'bpo', 'is_active' => true, 'user_id' => $user->id]);

        $itAgent = SupportAgent::create(['name' => 'Dummy SINTA', 'type' => 'it', 'is_active' => true]);

        $ticket = $this->escalatedOtherTicket($bpoRow, $itAgent);

        $orang = collect(TicketPeople::supportAgents($ticket))->keyBy('name');

        $this->assertSame('Support · BPO', $orang['Dummy SINTA 3']['role']);
        $this->assertSame('Support · IT', $orang['Dummy SINTA']['role']);
    }

    /**
     * Orang yang benar-benar memegang tiket di KEDUA desk (mengeskalasi sebagai
     * BPO lalu mengklaimnya sendiri sebagai IT) disebut sekali dengan kedua
     * deskinya — bukan salah satu yang dipilih secara acak.
     */
    public function test_orang_yang_memegang_dua_desk_disebut_dengan_keduanya(): void
    {
        $user = User::factory()->create(['name' => 'Dummy SINTA']);
        $itRow = SupportAgent::create(['name' => $user->name, 'type' => 'it', 'is_active' => true, 'user_id' => $user->id]);
        $bpoRow = SupportAgent::create(['name' => $user->name, 'type' => 'bpo', 'is_active' => true, 'user_id' => $user->id]);

        $ticket = $this->escalatedOtherTicket($bpoRow, $itRow);

        $orang = TicketPeople::supportAgents($ticket);

        $this->assertCount(1, $orang, 'Satu orang tetap satu baris.');
        $this->assertSame('Support · BPO, IT', $orang[0]['role']);
    }

    /** Tiket biasa tanpa peran ganda tidak boleh ikut berubah bentuknya. */
    public function test_tiket_tanpa_peran_ganda_tetap_menyebut_satu_desk(): void
    {
        $bpo = SupportAgent::create(['name' => 'Denny Firmansyah', 'type' => 'bpo', 'is_active' => true]);
        $it = SupportAgent::create(['name' => 'Agung Wijayanto', 'type' => 'it', 'is_active' => true]);

        $ticket = $this->escalatedOtherTicket($bpo, $it);

        $orang = collect(TicketPeople::supportAgents($ticket))->keyBy('name');

        $this->assertSame('Support · BPO', $orang['Denny Firmansyah']['role']);
        $this->assertSame('Support · IT', $orang['Agung Wijayanto']['role']);
    }

    /**
     * Tiket "Lainnya" (catalog_subject_id null, jadi tidak ada PIC katalog yang
     * bisa dibaca) yang sudah dieskalasi BPO ke IT — bentuk yang memunculkan
     * bugnya di produksi.
     */
    private function escalatedOtherTicket(SupportAgent $bpoAgent, SupportAgent $itAgent): Ticket
    {
        $ticket = Ticket::create([
            'ticket_no' => 'AR-UJI-2026-'.random_int(1000, 9999),
            'title' => 'Tiket uji panel Orang Terkait',
            'requester_name' => 'Andi Pratama',
            'status' => 'In Progress',
            'priority' => 'Medium',
            'catalog_subject_id' => null,
            'assigned_agent_id' => $itAgent->id,
            'escalated_at' => now()->subMinutes(10),
            'escalated_by_agent_id' => $bpoAgent->id,
            'sla_policy_id' => $this->slaPolicyId(),
            'response_time_minutes' => 480,
            'resolution_time_minutes' => 2880,
            'warning_threshold_percent' => 80,
            'response_due_at' => now()->addHours(8),
            'resolution_due_at' => now()->addDays(2),
            'warning_at' => now()->addDay(),
        ]);

        AuditTrail::record(User::factory()->create(), [
            'module' => 'ticket_support',
            'action' => 'escalate',
            'target_type' => 'ticket',
            'target_id' => $ticket->id,
            'target_name' => $ticket->ticket_no,
            'old_value' => ['assigned_agent' => $bpoAgent->name],
            'new_value' => ['assigned_agent' => $itAgent->name],
            'description' => 'Uji eskalasi.',
        ]);

        return $ticket->fresh();
    }

    private ?int $slaPolicyId = null;

    private function slaPolicyId(): int
    {
        return $this->slaPolicyId ??= SlaPolicy::create([
            'policy_name' => 'Uji Orang Terkait',
            'priority' => 'Medium',
            'service_type' => 'Incident',
            'response_time_minutes' => 480,
            'resolution_time_minutes' => 2880,
            'warning_threshold_percent' => 80,
            'status' => 'active',
        ])->id;
    }
}
