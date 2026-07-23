<?php

namespace App\Support;

use App\Models\Ticket;
use App\Models\TicketNotification;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Central place that creates ticket_notifications rows. There is no queue
 * worker or scheduler wired up in this app, so SLA warning/breach alerts
 * aren't pushed by a cron job — they're synced lazily whenever the
 * requester loads a page that lists their tickets (see syncSlaAlerts()),
 * which is enough for a single-user demo without a background process.
 */
class NotificationService
{
    public static function notify(User $user, ?Ticket $ticket, string $type, string $title, string $message): TicketNotification
    {
        return TicketNotification::create([
            'user_id' => $user->id,
            'ticket_id' => $ticket?->id,
            'type' => $type,
            'title' => $title,
            'message' => $message,
        ]);
    }

    public static function syncSlaAlerts(Collection $tickets, User $user): void
    {
        $active = $tickets->whereIn('status', Ticket::ACTIVE_STATUSES);

        foreach (['warning', 'breach'] as $kind) {
            $type = "sla_{$kind}";
            $matching = $active->filter(fn (Ticket $t) => $t->sla_kind === $kind);

            if ($matching->isEmpty()) {
                continue;
            }

            $alreadyNotified = TicketNotification::where('user_id', $user->id)
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
    ];

    /**
     * Formats a user's notification feed for the top-bar bell — shared by
     * every page under a role layout so the unread count and list never
     * drift between screens. $ticketRoute/$readRoute let each role point
     * the feed at its own ticket-detail and mark-read endpoints.
     */
    public static function present(
        User $user,
        int $limit = 20,
        string $ticketRoute = 'requester.tickets.show',
        string $readRoute = 'requester.notifications.read'
    ): array {
        return TicketNotification::where('user_id', $user->id)
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
    }
}
