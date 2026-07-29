<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Email channel of a Team Lead rating teguran — a reprimand tied to an
 * agent's overall satisfaction rating rather than a single ticket's SLA.
 * Uses the app's configured mailer (MAIL_MAILER=log in dev), same as
 * TeguranSlaMail.
 */
class TeguranRatingMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $agentName,
        public string $teamLeadName,
        public float $rating,
        public int $ratingCount,
        public string $body,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Teguran Rating · {$this->agentName}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.teguran-rating',
        );
    }
}
