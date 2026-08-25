<?php

namespace App\Support;

use App\Models\SupportAgent;
use App\Models\Ticket;
use App\Models\TicketNotification;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Central place that creates ticket_notifications rows.
 *
 * SLA warning/breach alerts are not pushed by the scheduler: they're synced
 * lazily whenever a user loads a page that lists their tickets (see
 * syncSlaAlerts()). That's a deliberate limit worth knowing about — an alert
 * exists from the moment someone looks, not from the moment the clock passes
 * the threshold — and it is why those two types are kept out of the email
 * allowlist in `config/notifications.php`.
 */
class NotificationService
{
    /**
     * $role — untuk PERAN APA notifikasi ini dibuat ('requester', 'approver',
     * 'support', 'support-bpo', 'team-lead'). Wajib, dan sengaja diletakkan
     * tepat setelah $user: pemanggil lama yang belum diperbarui mengirim
     * Ticket ke posisi ini dan langsung gagal dengan TypeError, alih-alih
     * diam-diam menulis baris tanpa peran yang baru ketahuan salah di layar.
     *
     * Satu orang bisa memegang beberapa peran. Notifikasi yang ia terima
     * SEBAGAI approver tidak boleh muncul saat ia sedang membuka layar
     * requester — lihat present().
     */
    public static function notify(User $user, string $role, ?Ticket $ticket, string $type, string $title, string $message): TicketNotification
    {
        $notification = TicketNotification::create([
            'user_id' => $user->id,
            'role' => $role,
            'ticket_id' => $ticket?->id,
            'type' => $type,
            'title' => $title,
            'message' => $message,
        ]);

        // The bell row is the record; the email is a copy of it. Mirroring here
        // — one function, rather than at each of the ~25 call sites that raise a
        // notification — is what keeps the two from drifting: there is no way to
        // ring the bell and forget the inbox. NotificationMailer decides whether
        // this particular type is worth an email, and never throws if it isn't
        // deliverable, so a mail outage cannot fail the action that caused it.
        NotificationMailer::maybeSend($user, $ticket, $type, $title, $message);

        return $notification;
    }

    /**
     * Whether the ticket has actually been handed to Support yet.
     *
     * Mirrors the guard the Support controllers use to answer 403/422, so a
     * notification can never point at a ticket its recipient is forbidden to
     * open. Keep the two in step: this is the same list, read from the model.
     */
    public static function releasedToSupport(Ticket $ticket): bool
    {
        return ! in_array($ticket->status, Ticket::NOT_YET_RELEASED_STATUSES, true);
    }

    /**
     * Routes a notification to whichever real person is actually PIC on
     * this ticket right now (`assigned_agent_id` → support_agents.user_id),
     * not a fixed persona — a Subject can route to any of several IT/BPO
     * agents (see CurrentActor::support()/supportBpo()), so "notify
     * Support" only makes sense per-ticket, never as one hardcoded user.
     * Silently no-ops if the ticket has no PIC yet or that agent has no
     * linked login — just a defensive guard, not an error condition.
     *
     * Also no-ops while the ticket hasn't been released to Support yet.
     * `assigned_agent_id` is frozen at creation time, so a ticket sitting
     * with an Approver already names a PIC — notifying them here would ring
     * a bell for a ticket the Support controllers answer 403 for.
     */
    public static function notifyAssignedAgent(Ticket $ticket, string $type, string $title, string $message): ?TicketNotification
    {
        if (! self::releasedToSupport($ticket)) {
            return null;
        }

        $agent = $ticket->assignedAgent;
        if (! $agent || ! $agent->user_id) {
            return null;
        }

        $user = User::find($agent->user_id);
        if (! $user) {
            return null;
        }

        return self::notify($user, self::roleForAgent($agent), $ticket, $type, $title, $message);
    }

    /**
     * Peran lonceng untuk seorang PIC. Support IT dan Support BPO punya layar,
     * antrean, dan lonceng masing-masing, jadi keduanya tidak boleh berbagi
     * satu kunci peran — dibedakan oleh kolom `type` di support_agents.
     */
    public static function roleForAgent(SupportAgent $agent): string
    {
        return $agent->type === 'bpo' ? 'support-bpo' : 'support';
    }

    /**
     * A Discussion reply (Requester/Approver/Support all post into the same
     * thread — see TicketComment) is a distinct notification type from an
     * Approval decision (ticket_approved/ticket_rejected/ticket_reopened):
     * a decision changes the ticket's status, a reply is just a message.
     * Keeping them separate lets each render/read differently in the bell.
     * Notifies every other participant currently attached to the ticket
     * (requester, approver, assigned agent) except whoever just posted.
     */
    public static function notifyDiscussionParticipants(Ticket $ticket, User $author, string $authorRole, string $message): void
    {
        $preview = Str::limit($message, 120);

        // The PIC only joins the conversation once the ticket actually reaches
        // Support. Before that the thread belongs to the Requester and Approver,
        // and the (already frozen) agent must not be pulled into it.
        $agentUserId = self::releasedToSupport($ticket) ? $ticket->assignedAgent?->user_id : null;

        // Tiap peserta diberi tahu SEBAGAI perannya sendiri di tiket ini —
        // requester menerimanya di lonceng Requester, approver di lonceng
        // Approver, PIC di lonceng Support-nya. Satu pesan diskusi yang sama
        // karena itu bisa menghasilkan baris dengan peran berbeda-beda.
        $agent = self::releasedToSupport($ticket) ? $ticket->assignedAgent : null;

        collect([
            ['user' => $ticket->requester, 'role' => 'requester'],
            ['user' => $ticket->approver, 'role' => 'approver'],
            ['user' => $agentUserId ? User::find($agentUserId) : null, 'role' => $agent ? self::roleForAgent($agent) : 'support'],
        ])
            ->filter(fn (array $p) => $p['user'] instanceof User)
            ->reject(fn (array $p) => $p['user']->id === $author->id)
            ->unique(fn (array $p) => $p['user']->id.'|'.$p['role'])
            ->each(fn (array $p) => self::notify(
                $p['user'],
                $p['role'],
                $ticket,
                'discussion_message',
                'Pesan Baru di Diskusi Tiket',
                "{$author->name} ({$authorRole}) di tiket {$ticket->ticket_no}: {$preview}"
            ));
    }

    /**
     * $role ikut menentukan DUA hal, bukan satu: peran yang dicatat pada baris
     * baru, dan cakupan penyaring duplikatnya di bawah. Tanpa yang kedua,
     * peringatan SLA yang sudah terkirim ke lonceng Requester akan membungkam
     * peringatan untuk tiket yang sama di lonceng Support — dua orang berbeda
     * yang sama-sama perlu tahu.
     */
    public static function syncSlaAlerts(Collection $tickets, User $user, string $role): void
    {
        $active = $tickets->whereIn('status', Ticket::ACTIVE_STATUSES);

        foreach (['warning', 'breach'] as $kind) {
            $type = "sla_{$kind}";
            $matching = $active->filter(fn (Ticket $t) => $t->sla_kind === $kind);

            if ($matching->isEmpty()) {
                continue;
            }

            $alreadyNotified = TicketNotification::where('user_id', $user->id)
                ->where('role', $role)
                ->where('type', $type)
                ->whereIn('ticket_id', $matching->pluck('id'))
                ->pluck('ticket_id')
                ->all();

            foreach ($matching as $ticket) {
                if (in_array($ticket->id, $alreadyNotified, true)) {
                    continue;
                }

                $label = $kind === 'breach' ? 'sudah melewati batas SLA' : 'mendekati batas waktu SLA';

                self::notify(
                    $user,
                    $role,
                    $ticket,
                    $type,
                    $kind === 'breach' ? 'SLA Breach' : 'SLA Warning',
                    "Tiket {$ticket->ticket_no} \"{$ticket->title}\" {$label}."
                );
            }
        }
    }

    private const ICONS = [
        'ticket_created' => 'M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8Z M14 3v5h5',
        'ticket_reopened' => 'M21 11.5a8.5 8.5 0 0 1-8.5 8.5c-1.5 0-3-.4-4.2-1.1L3 20l1.1-5.3A8.5 8.5 0 1 1 21 11.5Z',
        'ticket_closed' => 'M20 6 9 17l-5-5',
        'sla_warning' => 'M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18Z M12 7v5l3 3',
        'sla_breach' => 'M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18Z M12 7v5l3 3',
        'waiting_decision' => 'M9 12h6 M9 16h6 M9 8h6 M7 3h10a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z',
        'decision_recorded' => 'M9 12l2 2 4-5 M21 12a9 9 0 1 1-9-9',
        'history_updated' => 'M4 10h16 M6 10V7a4 4 0 0 1 8 0v3 M4 10h16v8a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-8Z',
        'ticket_approved' => 'M9 12l2 2 4-5 M21 12a9 9 0 1 1-9-9',
        'ticket_rejected' => 'M18 6 6 18 M6 6l12 12',
        'ticket_resolved' => 'M9 12l2 2 4-5 M21 12a9 9 0 1 1-9-9',
        'ticket_escalated' => 'M12 19V5 M5 12l7-7 7 7',
        'ticket_incoming_escalation' => 'M12 5v14 M19 12l-7 7-7-7',
        'discussion_message' => 'M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5Z',
        'rating_teguran' => 'm12 2 3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2Z',
    ];

    /**
     * Formats a user's notification feed for the top-bar bell — shared by
     * every page under a role layout so the unread count and list never
     * drift between screens. $ticketRoute/$readRoute let each role point
     * the feed at its own ticket-detail and mark-read endpoints.
     *
     * Returns the newest $limit notifications under 'items' plus the FULL
     * unread total under 'unreadCount'. The two are counted separately on
     * purpose: the panel stays short, but the badge must not be capped by
     * the panel's window or a busy requester reads 16 where 57 are waiting.
     *
     * @return array{items: array<int, array<string, mixed>>, unreadCount: int}
     */
    public static function present(
        User $user,
        string $role,
        int $limit = 20,
        string $ticketRoute = 'requester.tickets.show',
        string $readRoute = 'requester.notifications.read'
    ): array {
        $unreadCount = TicketNotification::where('user_id', $user->id)
            ->where('role', $role)
            ->whereNull('read_at')
            ->count();

        $items = TicketNotification::where('user_id', $user->id)
            // Inti perbaikannya. Tanpa baris ini, pemegang dua peran melihat
            // notifikasi approval-nya di lonceng Requester dan sebaliknya.
            ->where('role', $role)
            ->with('ticket:id,ticket_no')
            ->latest('created_at')
            ->take($limit)
            ->get()
            ->map(fn (TicketNotification $n) => [
                'id' => $n->id,
                'type' => $n->type,
                'text' => $n->message,
                'time' => $n->created_at->diffForHumans(),
                'unread' => $n->read_at === null,
                'icon' => self::ICONS[$n->type] ?? self::ICONS['ticket_created'],
                'href' => $n->ticket ? route($ticketRoute, $n->ticket) : null,
                'markReadUrl' => route($readRoute, $n),
            ])
            ->values()
            ->all();

        return ['items' => $items, 'unreadCount' => $unreadCount];
    }
}
