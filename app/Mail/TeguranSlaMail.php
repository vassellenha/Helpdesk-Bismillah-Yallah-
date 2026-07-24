<?php

namespace App\Mail;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Email channel of a Team Lead SLA teguran. Uses the app's configured mailer
 * (MAIL_MAILER=log in dev, so it lands in the log rather than an inbox until
 * real SMTP credentials are set) — no delivery code changes when SMTP is added.
 */
class TeguranSlaMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Ticket $ticket,
        public string $agentName,
        public string $teamLeadName,
        public string $body,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Teguran SLA · Tiket {$this->ticket->ticket_no}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.teguran-sla',
        );
    }
}
