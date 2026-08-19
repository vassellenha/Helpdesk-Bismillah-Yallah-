<?php

namespace Tests\Feature;

use App\Mail\TicketNotificationMail;
use App\Models\SlaPolicy;
use App\Models\SupportAgent;
use App\Models\Ticket;
use App\Models\TicketNotification;
use App\Models\User;
use App\Support\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Email mirror of the in-app bell (NotificationService::notify() →
 * NotificationMailer). The bell row is the record and must be written no matter
 * what; the email is a copy that only some types earn, so most of these tests
 * assert the two halves independently — a notification that sends no email
 * still has to exist in the bell.
 */
class NotificationEmailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        // Pinned rather than inherited from config/notifications.php: these
        // tests are about the mechanism, and should not start failing the day
        // someone decides ticket_resolved no longer deserves an email.
        config([
            'notifications.email.enabled' => true,
            'notifications.email.types' => ['ticket_resolved'],
        ]);
    }

    public function test_tipe_dalam_allowlist_dikirim_ke_alamat_penerima(): void
    {
        $user = $this->activeUser('penerima@adhi.co.id');
        $ticket = $this->ticket();

        NotificationService::notify($user, 'requester', $ticket, 'ticket_resolved', 'Tiket Diselesaikan', 'Tiket sudah selesai.');

        Mail::assertSent(TicketNotificationMail::class, fn (TicketNotificationMail $mail) => $mail->hasTo('penerima@adhi.co.id')
            && $mail->ticket->is($ticket)
            && $mail->body === 'Tiket sudah selesai.');
    }

    public function test_tipe_di_luar_allowlist_hanya_membunyikan_lonceng(): void
    {
        $user = $this->activeUser();

        NotificationService::notify($user, 'requester', $this->ticket(), 'discussion_message', 'Pesan Baru', 'Ada balasan.');

        Mail::assertNothingSent();
        $this->assertDatabaseCount('ticket_notifications', 1);
    }

    public function test_kill_switch_mematikan_email_tanpa_mematikan_lonceng(): void
    {
        config(['notifications.email.enabled' => false]);
        $user = $this->activeUser();

        NotificationService::notify($user, 'requester', $this->ticket(), 'ticket_resolved', 'Tiket Diselesaikan', 'Selesai.');

        Mail::assertNothingSent();
        $this->assertDatabaseCount('ticket_notifications', 1);
    }

    /**
     * Someone who has left the company keeps their bell history — it is part of
     * the ticket's record — but the company should stop mailing an address it
     * no longer controls.
     */
    public function test_user_nonaktif_tidak_dikirimi_email_tapi_lonceng_tetap_tercatat(): void
    {
        $user = $this->activeUser('keluar@adhi.co.id');
        $user->update(['status' => 'inactive']);

        NotificationService::notify($user, 'requester', $this->ticket(), 'ticket_resolved', 'Tiket Diselesaikan', 'Selesai.');

        Mail::assertNothingSent();
        $this->assertDatabaseCount('ticket_notifications', 1);
    }

    /**
     * `users.email` is NOT NULL, but nothing stops it being blank — the column
     * is filled from the company employee API (EmployeeSync), and a record with
     * no address there arrives as an empty string, not as a missing row.
     */
    public function test_user_tanpa_alamat_email_dilewati_tanpa_error(): void
    {
        $user = $this->activeUser();
        $user->forceFill(['email' => ''])->save();

        $notification = NotificationService::notify($user, 'requester', $this->ticket(), 'ticket_resolved', 'Tiket Diselesaikan', 'Selesai.');

        Mail::assertNothingSent();
        $this->assertInstanceOf(TicketNotification::class, $notification->fresh());
    }

    /**
     * A Team Lead teguran picks its own channels and reports which ones landed
     * (TeguranNotifier). If the bell mirror also mailed it, ticking "in-app"
     * alone would still send an email — silently overriding that choice.
     */
    public function test_teguran_tidak_ikut_dikirim_oleh_mirror(): void
    {
        config(['notifications.email.types' => ['sla_teguran', 'rating_teguran']]);
        $user = $this->activeUser();

        NotificationService::notify($user, 'requester', $this->ticket(), 'sla_teguran', 'Teguran SLA', 'Mohon segera diselesaikan.');
        NotificationService::notify($user, 'requester', null, 'rating_teguran', 'Teguran Rating', 'Rating Anda menurun.');

        Mail::assertNothingSent();
        $this->assertDatabaseCount('ticket_notifications', 2);
    }

    public function test_tautan_mengarah_ke_halaman_sesuai_peran_penerima_pada_tiket(): void
    {
        $requester = $this->activeUser('requester@adhi.co.id');
        $approver = $this->activeUser('approver@adhi.co.id');
        $agentUser = $this->activeUser('agent@adhi.co.id');
        $agent = SupportAgent::create([
            'name' => 'Agent BPO', 'type' => 'bpo', 'is_active' => true, 'user_id' => $agentUser->id,
        ]);

        $ticket = $this->ticket([
            'requester_id' => $requester->id,
            'approver_id' => $approver->id,
            'assigned_agent_id' => $agent->id,
        ]);

        foreach ([
            [$requester, route('requester.tickets.show', $ticket)],
            [$approver, route('approver.tickets.show', $ticket)],
            [$agentUser, route('support-bpo.tickets.show', $ticket)],
        ] as [$user, $expected]) {
            NotificationService::notify($user, 'requester', $ticket, 'ticket_resolved', 'Tiket Diselesaikan', 'Selesai.');

            Mail::assertSent(TicketNotificationMail::class, fn (TicketNotificationMail $mail) => $mail->hasTo($user->email) && $mail->actionUrl === $expected);
        }
    }

    /**
     * A rating teguran carries no ticket at all, and an outsider has no ticket
     * page to be sent to — both must land somewhere real rather than on a 403
     * or a broken route() call.
     */
    public function test_penerima_tanpa_peran_pada_tiket_diarahkan_ke_portal(): void
    {
        $outsider = $this->activeUser('orang-lain@adhi.co.id');

        NotificationService::notify($outsider, 'requester', $this->ticket(), 'ticket_resolved', 'Tiket Diselesaikan', 'Selesai.');

        Mail::assertSent(TicketNotificationMail::class, fn (TicketNotificationMail $mail) => $mail->actionUrl === route('portal.index'));
    }

    public function test_subjek_email_memuat_nomor_tiket(): void
    {
        $user = $this->activeUser();
        $ticket = $this->ticket();

        NotificationService::notify($user, 'requester', $ticket, 'ticket_resolved', 'Tiket Diselesaikan', 'Selesai.');

        Mail::assertSent(TicketNotificationMail::class, fn (TicketNotificationMail $mail) => $mail->envelope()->subject === "Tiket Diselesaikan · Tiket {$ticket->ticket_no}");
    }

    /**
     * Mail::fake() intercepts the mailable before the view is ever compiled, so
     * every assertion above would still pass with a broken Blade template.
     * These two render it for real — including the branch where there is no
     * ticket, which the ticket-detail table must not try to read through.
     */
    public function test_template_dirender_lengkap_untuk_notifikasi_bertiket(): void
    {
        $ticket = $this->ticket();

        $html = (new TicketNotificationMail(
            recipientName: 'Andi Pratama',
            title: 'Tiket Diselesaikan',
            body: 'Tiket sudah selesai.',
            ticket: $ticket,
            actionUrl: 'https://helpdesk.adhi.co.id/requester/tickets/1',
        ))->render();

        $this->assertStringContainsString('Andi Pratama', $html);
        $this->assertStringContainsString($ticket->ticket_no, $html);
        $this->assertStringContainsString('https://helpdesk.adhi.co.id/requester/tickets/1', $html);
        $this->assertStringContainsString('Buka Tiket', $html);
    }

    public function test_template_dirender_tanpa_tiket(): void
    {
        $html = (new TicketNotificationMail(
            recipientName: 'Andi Pratama',
            title: 'Teguran Rating',
            body: 'Rating Anda menurun.',
            ticket: null,
            actionUrl: 'https://helpdesk.adhi.co.id',
        ))->render();

        $this->assertStringContainsString('Rating Anda menurun.', $html);
        $this->assertStringContainsString('Buka Helpdesk', $html);
        $this->assertStringNotContainsString('No. Tiket', $html);
    }

    private function activeUser(?string $email = null): User
    {
        return User::factory()->create([
            'email' => $email ?? 'uji'.random_int(1000, 9999).'@adhi.co.id',
            'status' => 'active',
            'helpdesk_access' => 'enabled',
        ]);
    }

    /** @param array<string,mixed> $attributes */
    private function ticket(array $attributes = []): Ticket
    {
        $now = Carbon::now();

        return Ticket::create(array_merge([
            'ticket_no' => 'INC-UJI-2026-'.random_int(1000, 9999),
            'title' => 'Tiket uji notifikasi email',
            'requester_name' => 'Andi Pratama',
            'status' => 'Resolved',
            'priority' => 'Medium',
            'sla_policy_id' => $this->slaPolicyId(),
            'response_time_minutes' => 480,
            'resolution_time_minutes' => 2880,
            'warning_threshold_percent' => 80,
            'response_due_at' => $now->clone()->addHours(8),
            'resolution_due_at' => $now->clone()->addDays(2),
            'warning_at' => $now->clone()->addDays(1),
        ], $attributes));
    }

    private ?int $slaPolicyId = null;

    private function slaPolicyId(): int
    {
        return $this->slaPolicyId ??= SlaPolicy::create([
            'policy_name' => 'Uji Notifikasi Email',
            'priority' => 'Medium',
            'service_type' => 'Incident',
            'response_time_minutes' => 480,
            'resolution_time_minutes' => 2880,
            'warning_threshold_percent' => 80,
            'status' => 'active',
        ])->id;
    }
}
