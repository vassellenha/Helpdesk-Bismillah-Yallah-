<?php

declare(strict_types=1);

namespace Tests\Feature\Discussion;

use App\Models\SlaPolicy;
use App\Models\SupportAgent;
use App\Models\Ticket;
use App\Models\User;
use App\Support\TicketDiscussion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsRole;
use Tests\TestCase;

/**
 * Tiket berstatus "Returned" adalah tiket yang SUDAH dikirim dan sudah punya
 * percakapan — Support atau Approver mengembalikannya justru supaya requester
 * memperbaiki sesuatu. Di sanalah isi Forum Diskusi paling dibutuhkan.
 *
 * Beda tajam dengan "Draft", yang belum pernah dikirim: tidak ada lawan bicara,
 * dan tidak ada satu pun komentar yang bisa ada. Menyembunyikan forum untuk
 * Draft masuk akal; memperlakukan Returned sama seperti Draft tidak.
 *
 * Server tidak pernah menganggap keduanya sama — addComment() hanya menutup
 * 'Closed' dan 'Rejected', baik di sisi requester maupun Support, dan show()
 * selalu mengirim comments apa pun statusnya. Tes ini mengunci kontrak itu,
 * karena halaman detail requester-lah yang dulu lebih ketat daripada servernya:
 * requester tidak bisa membaca alasan pengembalian sampai ia menekan
 * "Edit & Resubmit".
 */
final class ReturnedTicketDiscussionTest extends TestCase
{
    use ActsAsRole, RefreshDatabase;

    public function test_requester_tetap_menerima_isi_forum_saat_tiket_dikembalikan(): void
    {
        [$requester, $ticket] = $this->returnedTicketWithSupportComment();

        $comments = $this->actingAs($requester)
            ->get(route('requester.tickets.show', $ticket))
            ->assertOk()
            ->viewData('comments');

        $this->assertCount(1, $comments);
        $this->assertSame('Mohon lampirkan tangkapan layar errornya.', $comments[0]['message']);
    }

    public function test_requester_boleh_membalas_di_tiket_yang_dikembalikan(): void
    {
        [$requester, $ticket] = $this->returnedTicketWithSupportComment();

        $this->actingAs($requester)
            ->post(route('requester.tickets.comments.store', $ticket), ['message' => 'Baik, saya lampirkan.'])
            ->assertCreated();

        $this->assertSame('Returned', $ticket->fresh()->status);
        $this->assertCount(2, $ticket->fresh()->comments);
    }

    /**
     * Yang ditutup saat Returned adalah sisi SUPPORT, bukan sisi requester —
     * tiketnya sudah tidak di meja mereka (Returned ada di
     * Ticket::NOT_YET_RELEASED_STATUSES). Kontras inilah yang penting: giliran
     * bicara memang pindah ke requester, jadi requester justru pihak yang harus
     * bisa membaca dan membalas, bukan satu-satunya yang dibungkam.
     */
    public function test_support_justru_yang_ditutup_saat_tiket_dikembalikan(): void
    {
        [, $ticket] = $this->returnedTicketWithSupportComment();

        $agentUser = User::factory()->create(['name' => 'Denny Firmansyah', 'status' => 'active', 'helpdesk_access' => 'enabled']);
        $agent = SupportAgent::create(['name' => $agentUser->name, 'type' => 'bpo', 'is_active' => true, 'user_id' => $agentUser->id]);
        $ticket->update(['assigned_agent_id' => $agent->id]);

        $this->actingAsUserWithRoles($agentUser, 'support-bpo');
        $this->post(route('support-bpo.tickets.comments.store', $ticket), ['message' => 'Menunggu revisi Anda.'])
            ->assertStatus(403);

        $this->assertCount(1, $ticket->fresh()->comments);
    }

    /** @return array{0:User,1:Ticket} */
    private function returnedTicketWithSupportComment(): array
    {
        $requester = User::factory()->create(['status' => 'active', 'helpdesk_access' => 'enabled']);
        $this->actingAsUserWithRoles($requester, 'requester');

        $ticket = Ticket::create([
            'ticket_no' => 'INC-UJI-2026-'.random_int(1000, 9999),
            'title' => 'Tiket uji forum saat dikembalikan',
            'requester_name' => $requester->name,
            'requester_id' => $requester->id,
            'status' => 'Returned',
            'priority' => 'Medium',
            'sla_policy_id' => $this->slaPolicyId(),
            'response_time_minutes' => 480,
            'resolution_time_minutes' => 2880,
            'warning_threshold_percent' => 80,
            'response_due_at' => now()->addHours(8),
            'resolution_due_at' => now()->addDays(2),
            'warning_at' => now()->addDay(),
        ]);

        $supportUser = User::factory()->create(['name' => 'Agung Wijayanto']);
        TicketDiscussion::store(
            $ticket,
            $supportUser,
            'Support',
            'Support',
            ['message' => 'Mohon lampirkan tangkapan layar errornya.'],
            null,
        );

        return [$requester, $ticket->fresh()];
    }

    private ?int $slaPolicyId = null;

    private function slaPolicyId(): int
    {
        return $this->slaPolicyId ??= SlaPolicy::create([
            'policy_name' => 'Uji Forum Returned',
            'priority' => 'Medium',
            'service_type' => 'Incident',
            'response_time_minutes' => 480,
            'resolution_time_minutes' => 2880,
            'warning_threshold_percent' => 80,
            'status' => 'active',
        ])->id;
    }
}
