<?php

namespace App\Mail;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The email twin of an in-app bell notification.
 *
 * Deliberately generic: one Mailable covers every notification type instead of
 * a class per event. The bell already carries a title and a human-written
 * message for each type, and those two strings are the whole content of the
 * email — minting `TicketResolvedMail`, `TicketApprovedMail`, and a dozen more
 * would duplicate that copy in a second place and let the two drift apart.
 * Which types actually reach an inbox is a config question, not a class one —
 * see `notifications.email.types`.
 */
class TicketNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $recipientName,
        public string $title,
        public string $body,
        public ?Ticket $ticket,
        public string $actionUrl,
    ) {}

    public function envelope(): Envelope
    {
        // The ticket number belongs in the subject, not just the body: it is
        // what people search their mailbox for, and what makes two updates on
        // different tickets distinguishable in a threaded inbox.
        return new Envelope(
            subject: $this->ticket
                ? "{$this->title} · Tiket {$this->ticket->ticket_no}"
                : $this->title,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.ticket-notification',
        );
    }
}
