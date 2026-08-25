<?php

declare(strict_types=1);

namespace Tests\Feature\Attachments;

use App\Models\Role;
use App\Models\SlaPolicy;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use App\Support\RoleRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Lubang yang sama dengan lampiran tiket, di pintu yang berbeda: berkas yang
 * ditempel pada komentar Forum Diskusi juga disimpan di disk publik dan
 * disajikan lewat /storage/... tanpa pemeriksaan apa pun.
 *
 * Justru di sinilah isinya sering paling sensitif — Support meminta tangkapan
 * layar error, Requester menempelkan potongan data yang bermasalah — dan
 * semuanya menumpuk di tiket yang seharusnya cuma dilihat segelintir orang.
 */
final class CommentAttachmentAccessTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $email, ?string $roleKey = 'requester'): User
    {
        $user = User::factory()->create([
            'email' => $email,
            'username' => $email,
            'status' => 'active',
            'helpdesk_access' => 'enabled',
        ]);

        if ($roleKey !== null) {
            $user->roles()->attach(Role::firstOrCreate(
                ['name' => RoleRegistry::roleNameFor($roleKey)],
                ['type' => 'system', 'status' => 'active'],
            )->id);
        }

        return $user;
    }

    private ?int $slaPolicyId = null;

    private function slaPolicyId(): int
    {
        return $this->slaPolicyId ??= SlaPolicy::create([
            'policy_name' => 'Uji Lampiran Komentar',
            'priority' => 'Low',
            'service_type' => 'Incident',
            'response_time_minutes' => 1440,
            'resolution_time_minutes' => 7200,
            'warning_threshold_percent' => 80,
            'status' => 'active',
        ])->id;
    }

    /** @return array{0:Ticket,1:TicketComment} */
    private function ticketWithComment(User $requester): array
    {
        Storage::fake('local');

        $ticket = Ticket::create([
            'ticket_no' => 'INC-UJI-2026-'.random_int(1000, 9999),
            'title' => 'Tiket uji lampiran komentar',
            'requester_id' => $requester->id,
            'requester_name' => $requester->name,
            'status' => 'Open',
            'priority' => 'Low',
            'sla_policy_id' => $this->slaPolicyId(),
            'response_time_minutes' => 1440,
            'resolution_time_minutes' => 7200,
            'warning_threshold_percent' => 80,
            'response_due_at' => now()->addDay(),
            'resolution_due_at' => now()->addDays(5),
            'warning_at' => now()->addDays(4),
        ]);

        Storage::disk('local')->put('ticket-comments/rahasia.png', 'isi-lampiran-komentar');

        $comment = $ticket->comments()->create([
            'author_name' => $requester->name,
            'author_role' => 'requester',
            'message' => 'Ini tangkapan layarnya.',
            'attachment_name' => 'rahasia.png',
            'attachment_path' => 'ticket-comments/rahasia.png',
        ]);

        return [$ticket, $comment];
    }

    private function url(Ticket $ticket, TicketComment $comment): string
    {
        return route('tickets.comment.attachment.show', [$ticket, $comment]);
    }

    public function test_pengunjung_yang_belum_login_ditolak(): void
    {
        [$ticket, $comment] = $this->ticketWithComment($this->user('pemilik@adhi.co.id'));

        $this->get($this->url($ticket, $comment))->assertRedirect(route('login'));
    }

    public function test_pemilik_tiket_bisa_membuka_lampiran_komentar(): void
    {
        $pemilik = $this->user('pemilik@adhi.co.id');
        [$ticket, $comment] = $this->ticketWithComment($pemilik);

        $response = $this->actingAs($pemilik)->get($this->url($ticket, $comment));

        $response->assertOk();
        $this->assertSame('isi-lampiran-komentar', $response->streamedContent());
    }

    public function test_requester_lain_ditolak(): void
    {
        [$ticket, $comment] = $this->ticketWithComment($this->user('pemilik@adhi.co.id'));

        $this->actingAs($this->user('orang.lain@adhi.co.id'))
            ->get($this->url($ticket, $comment))
            ->assertForbidden();
    }

    public function test_komentar_milik_tiket_lain_tidak_bisa_diambil_lewat_tiket_sendiri(): void
    {
        [, $komentarKorban] = $this->ticketWithComment($this->user('korban@adhi.co.id'));

        $penyerang = $this->user('penyerang@adhi.co.id');
        [$ticketPenyerang] = $this->ticketWithComment($penyerang);

        $this->actingAs($penyerang)
            ->get(route('tickets.comment.attachment.show', [$ticketPenyerang, $komentarKorban]))
            ->assertNotFound();
    }

    public function test_komentar_tanpa_lampiran_menjawab_404(): void
    {
        $pemilik = $this->user('pemilik@adhi.co.id');
        [$ticket] = $this->ticketWithComment($pemilik);

        $kosong = $ticket->comments()->create([
            'author_name' => $pemilik->name,
            'author_role' => 'requester',
            'message' => 'Komentar tanpa berkas.',
        ]);

        $this->actingAs($pemilik)->get($this->url($ticket, $kosong))->assertNotFound();
    }
}
