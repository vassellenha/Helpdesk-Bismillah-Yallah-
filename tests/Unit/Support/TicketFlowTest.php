<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Models\AuditTrail;
use App\Models\SlaPolicy;
use App\Models\SupportAgent;
use App\Models\Ticket;
use App\Models\User;
use App\Support\TicketFlow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Support BPO dan Support IT adalah dua lingkaran terpisah di Riwayat Status,
 * bukan satu lingkaran "Support" yang memadatkan keduanya jadi rantai
 * "A (BPO) → B (IT)".
 *
 * Bentuk lama memaksa satu sel menceritakan dua tahap, jadi peran seseorang
 * harus DITEBAK dari teks: SupportAgent::where('name', …)->value('type').
 * Akun berperan ganda punya dua baris SupportAgent bernama sama (satu 'bpo',
 * satu 'it'), sehingga tebakan itu bisa memilih baris yang salah dan BPO yang
 * mengeskalasi tampil berlabel "(IT)".
 *
 * Sekarang tiap lingkaran membaca pemegangnya dari kolom yang memang
 * menyimpannya — escalated_by_agent_id untuk desk BPO, assigned_agent_id untuk
 * desk yang sedang memegang — jadi tidak ada lagi yang perlu ditebak.
 */
class TicketFlowTest extends TestCase
{
    use RefreshDatabase;

    /** Kunci stage, terurut — bentuk inilah yang dibaca TicketFlow.jsx. */
    private function keys(Ticket $ticket): array
    {
        return array_column(TicketFlow::stages($ticket)['stages'], 'key');
    }

    private function stage(Ticket $ticket, string $key): array
    {
        $stages = collect(TicketFlow::stages($ticket)['stages'])->keyBy('key');

        $this->assertTrue($stages->has($key), "Stage '{$key}' tidak ada. Yang ada: ".implode(', ', $stages->keys()->all()));

        return $stages->get($key);
    }

    public function test_tiket_yang_belum_dieskalasi_hanya_punya_satu_lingkaran_support(): void
    {
        $bpoAgent = $this->agent('Denny Firmansyah', 'bpo');
        $ticket = $this->inProgressTicket($bpoAgent->id);

        $this->assertSame(['requester', 'approver', 'support_bpo', 'done'], $this->keys($ticket));

        $support = $this->stage($ticket, 'support_bpo');
        $this->assertSame('Denny Firmansyah', $support['by']);
        $this->assertSame('current', $support['state']);
    }

    public function test_eskalasi_memunculkan_lingkaran_it_terpisah_dari_bpo(): void
    {
        $bpoAgent = $this->agent('Denny Firmansyah', 'bpo');
        $itAgent = $this->agent('Agung Wijayanto', 'it');

        $ticket = $this->escalatedTicket(bpoAgent: $bpoAgent, holder: $itAgent);

        $this->assertSame(['requester', 'approver', 'support_bpo', 'support_it', 'done'], $this->keys($ticket));

        // Giliran BPO sudah lewat — lingkarannya selesai, bukan yang aktif.
        $bpo = $this->stage($ticket, 'support_bpo');
        $this->assertSame('Denny Firmansyah', $bpo['by']);
        $this->assertSame('done', $bpo['state']);

        $it = $this->stage($ticket, 'support_it');
        $this->assertSame('Agung Wijayanto', $it['by']);
        $this->assertSame('current', $it['state']);
    }

    /**
     * Bug yang dilaporkan: BPO yang mengeskalasi tampil sebagai "(IT)".
     *
     * Satu orang, dua baris SupportAgent bernama sama — pola nyata, lihat
     * TicketEscalateDualRoleTest. Baris IT-nya dibuat LEBIH DULU supaya
     * pencarian-berdasarkan-nama yang lama menemukannya duluan; itulah yang
     * dulu membuat namanya berakhiran "(IT)" padahal ia bertindak sebagai BPO.
     */
    public function test_akun_dobel_peran_muncul_di_lingkaran_desk_yang_benar(): void
    {
        $user = User::factory()->create(['name' => 'Dummy Sinta 3']);
        $itRow = SupportAgent::create(['name' => $user->name, 'type' => 'it', 'is_active' => true, 'user_id' => $user->id]);
        $bpoRow = SupportAgent::create(['name' => $user->name, 'type' => 'bpo', 'is_active' => true, 'user_id' => $user->id]);

        $penerima = $this->agent('Dummy Sinta', 'it');

        $ticket = $this->escalatedTicket(bpoAgent: $bpoRow, holder: $penerima);

        $this->assertSame('Dummy Sinta 3', $this->stage($ticket, 'support_bpo')['by']);
        $this->assertSame('Dummy Sinta', $this->stage($ticket, 'support_it')['by']);

        // Tidak ada lagi akhiran peran di belakang nama: lingkarannya sendiri
        // yang menyebut desk-nya, jadi akhiran itu cuma mengulang.
        foreach (TicketFlow::stages($ticket)['stages'] as $stage) {
            $this->assertStringNotContainsString('(IT)', (string) $stage['by']);
            $this->assertStringNotContainsString('(BPO)', (string) $stage['by']);
        }

        $this->assertNotSame($itRow->id, $bpoRow->id);
    }

    /**
     * Eskalasi broadcast melepas assigned_agent_id dan menulis baris audit
     * berisi teks harfiah "Broadcast PIC IT". Teks itu tidak boleh bocor ke
     * layar: lingkaran IT-nya memang belum punya PIC, dan itulah yang harus
     * dikatakannya.
     */
    public function test_eskalasi_broadcast_yang_belum_diklaim_menyebut_belum_ada_pic(): void
    {
        $bpoAgent = $this->agent('Denny Firmansyah', 'bpo');
        $ticket = $this->escalatedTicket(bpoAgent: $bpoAgent, holder: null, broadcast: true);

        $this->assertSame(__('flow.no_pic'), $this->stage($ticket, 'support_it')['by']);
        $this->assertSame('Denny Firmansyah', $this->stage($ticket, 'support_bpo')['by']);

        foreach (TicketFlow::stages($ticket)['stages'] as $stage) {
            $this->assertStringNotContainsString('Broadcast PIC IT', (string) $stage['by']);
        }
    }

    /**
     * Alih PIC oleh Team Lead masuk ke lingkaran desk tempat alih itu terjadi,
     * bukan ditumpuk jadi satu rantai panjang lintas-desk. Baris `reassign`
     * menyimpan namanya di kunci `agent`, bukan `assigned_agent`.
     */
    public function test_alih_pic_setelah_eskalasi_hanya_mengubah_lingkaran_it(): void
    {
        $bpoAgent = $this->agent('Denny Firmansyah', 'bpo');
        $itPertama = $this->agent('Agung Wijayanto', 'it');
        $itKedua = $this->agent('Budi Santoso', 'it');

        $ticket = $this->escalatedTicket(bpoAgent: $bpoAgent, holder: $itKedua);

        $this->handover($ticket, 'reassign', $itPertama->name, $itKedua->name, Carbon::now()->subMinutes(10), 'agent');

        $bpo = $this->stage($ticket, 'support_bpo');
        $this->assertSame('Denny Firmansyah', $bpo['by'], 'Alih PIC di desk IT tidak boleh menyentuh lingkaran BPO.');
        $this->assertSame(__('flow.sub.handling'), $bpo['sub']);

        $it = $this->stage($ticket, 'support_it');
        $this->assertSame('Agung Wijayanto → Budi Santoso', $it['by']);
        $this->assertSame(__('flow.sub.handovers', ['count' => 1]), $it['sub']);
    }

    /**
     * Kasus tepi yang nyata: Subjek tanpa PIC BPO merutekan tiketnya langsung
     * ke agen IT tanpa eskalasi — lihat TicketController::resolveAssignedAgentId(),
     * `$subject?->support_agent_id ?? $subject?->it_agent_id`. Tiket seperti itu
     * tidak boleh menampilkan agen IT di dalam lingkaran berlabel "Support BPO".
     */
    public function test_tiket_yang_langsung_dipegang_agen_it_memakai_lingkaran_it(): void
    {
        $itAgent = $this->agent('Agung Wijayanto', 'it');
        $ticket = $this->inProgressTicket($itAgent->id);

        $this->assertSame(['requester', 'approver', 'support_it', 'done'], $this->keys($ticket));
        $this->assertSame('Agung Wijayanto', $this->stage($ticket, 'support_it')['by']);
    }

    private function agent(string $name, string $type): SupportAgent
    {
        $user = User::factory()->create(['name' => $name]);

        return SupportAgent::create(['name' => $name, 'type' => $type, 'is_active' => true, 'user_id' => $user->id]);
    }

    /**
     * Tiket yang sudah berpindah ke desk IT. `holder` null berarti eskalasi
     * broadcast yang belum diklaim siapa pun (assigned_agent_id dilepas).
     */
    private function escalatedTicket(SupportAgent $bpoAgent, ?SupportAgent $holder, bool $broadcast = false): Ticket
    {
        $escalatedAt = Carbon::now()->subMinutes(30);

        $ticket = $this->inProgressTicket($holder?->id);
        $ticket->update([
            'status' => $broadcast ? 'Open' : 'In Progress',
            'escalated_at' => $escalatedAt,
            'escalated_by_agent_id' => $bpoAgent->id,
        ]);

        $this->handover(
            $ticket,
            'escalate',
            $bpoAgent->name,
            $broadcast ? 'Broadcast PIC IT' : $holder?->name,
            $escalatedAt,
        );

        return $ticket->fresh();
    }

    private function handover(Ticket $ticket, string $action, ?string $from, ?string $to, Carbon $at, string $nameKey = 'assigned_agent'): void
    {
        $entry = AuditTrail::record(User::factory()->create(), [
            'module' => 'ticket_support',
            'action' => $action,
            'target_type' => 'ticket',
            'target_id' => $ticket->id,
            'target_name' => $ticket->ticket_no,
            'old_value' => [$nameKey => $from],
            'new_value' => [$nameKey => $to],
            'description' => "Uji {$action}: {$from} → {$to}",
        ]);

        // created_at yang menentukan alih PIC ini terjadi di fase BPO atau IT,
        // jadi tidak boleh dibiarkan jatuh ke now() bersamaan semuanya.
        $entry->forceFill(['created_at' => $at])->save();
    }

    private function inProgressTicket(?int $assignedAgentId): Ticket
    {
        $now = Carbon::now();

        return Ticket::create([
            'ticket_no' => 'INC-UJI-2026-'.random_int(1000, 9999),
            'title' => 'Tiket uji riwayat PIC',
            'requester_name' => 'Andi Pratama',
            'status' => 'In Progress',
            'priority' => 'Medium',
            'assigned_agent_id' => $assignedAgentId,
            'sla_policy_id' => $this->slaPolicyId(),
            'response_time_minutes' => 480,
            'resolution_time_minutes' => 2880,
            'warning_threshold_percent' => 80,
            'response_due_at' => $now->clone()->addHours(8),
            'resolution_due_at' => $now->clone()->addDays(2),
            'warning_at' => $now->clone()->addDays(1),
        ]);
    }

    private ?int $slaPolicyId = null;

    private function slaPolicyId(): int
    {
        return $this->slaPolicyId ??= SlaPolicy::create([
            'policy_name' => 'Uji Riwayat PIC',
            'priority' => 'Medium',
            'service_type' => 'Incident',
            'response_time_minutes' => 480,
            'resolution_time_minutes' => 2880,
            'warning_threshold_percent' => 80,
            'status' => 'active',
        ])->id;
    }
}
