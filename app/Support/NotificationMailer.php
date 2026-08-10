<?php

namespace App\Support;

use App\Mail\TicketNotificationMail;
use App\Models\Ticket;
use App\Models\User;

/**
 * Mirrors an in-app bell notification into the recipient's inbox.
 *
 * Hooked into NotificationService::notify() rather than into each of the ~25
 * call sites that raise a notification. That is the whole point: every event
 * that already rings the bell is automatically a candidate for email, and a new
 * event added next month inherits the behaviour without anyone remembering to
 * wire it up. What stops that from flooding mailboxes is the allowlist in
 * `notifications.email.types` — silence is the default, and each type that
 * emails out was opted in on purpose.
 */
class NotificationMailer
{
    /**
     * Notification types that already have their own email path and must never
     * be emailed from here as well.
     *
     * A Team Lead teguran fans out across in-app/email/WhatsApp in
     * TeguranNotifier, picking channels per send and reporting which ones
     * landed. Its in-app leg calls NotificationService::notify() like everything
     * else, so without this guard a teguran sent with the email channel ticked
     * would arrive twice — and one sent with email deliberately unticked would
     * arrive anyway, quietly overriding the Team Lead's choice.
     *
     * Guarded here rather than merely left out of the config default, because
     * the failure only shows up in someone's inbox after the fact.
     */
    private const HANDLED_ELSEWHERE = ['sla_teguran', 'rating_teguran'];

    /**
     * @return bool whether an email was accepted for delivery
     */
    public static function maybeSend(User $user, ?Ticket $ticket, string $type, string $title, string $message): bool
    {
        if (! config('notifications.email.enabled', false)) {
            return false;
        }

        if (in_array($type, self::HANDLED_ELSEWHERE, true)) {
            return false;
        }

        if (! in_array($type, config('notifications.email.types', []), true)) {
            return false;
        }

        // A deactivated account keeps its notification rows — the bell history
        // stays intact for whoever reads it later — but must stop receiving
        // mail. Someone who left the company should not keep getting ticket
        // updates at an address the company no longer controls.
        if (! $user->isActive()) {
            return false;
        }

        $email = trim((string) $user->email);

        if ($email === '') {
            return false;
        }

        return MailDispatcher::send($email, new TicketNotificationMail(
            recipientName: $user->name,
            title: $title,
            body: $message,
            ticket: $ticket,
            actionUrl: self::linkFor($user, $ticket),
        ), '[Notifikasi]');
    }

    /**
     * Where the email's button should send this particular recipient.
     *
     * Every role has its own ticket-detail page, and opening the wrong one is
     * not a cosmetic mistake: the Support screens answer 403 to anyone who is
     * not the assigned agent. So the route is derived from the role the
     * recipient actually plays *on this ticket* — which the ticket itself
     * records — rather than from their account's roles, because one person can
     * be a requester on their own ticket and the PIC on somebody else's.
     *
     * Anything unrecognised falls back to the portal home instead of guessing:
     * a landing page that is merely one click short beats a 403.
     */
    private static function linkFor(User $user, ?Ticket $ticket): string
    {
        if (! $ticket) {
            return route('portal.index');
        }

        if ($ticket->requester_id === $user->id) {
            return route('requester.tickets.show', $ticket);
        }

        if ($ticket->approver_id === $user->id) {
            return route('approver.tickets.show', $ticket);
        }

        $agent = $ticket->assignedAgent;

        if ($agent && $agent->user_id === $user->id) {
            return $agent->type === 'bpo'
                ? route('support-bpo.tickets.show', $ticket)
                : route('support.tickets.show', $ticket);
        }

        return route('portal.index');
    }
}
