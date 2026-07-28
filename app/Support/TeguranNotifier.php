<?php

namespace App\Support;

use App\Mail\TeguranRatingMail;
use App\Mail\TeguranSlaMail;
use App\Models\SupportAgent;
use App\Models\Ticket;
use App\Models\TicketNotification;
use App\Models\User;
use App\Support\WhatsApp\FonnteWhatsAppGateway;
use App\Support\WhatsApp\LogWhatsAppGateway;
use App\Support\WhatsApp\WhatsAppGateway;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Multi-channel delivery for a Team Lead's SLA teguran. One call fans out to
 * whichever of the three channels the Team Lead picked — in-app bell, email,
 * WhatsApp — and reports back which ones actually went through, so the UI and
 * the audit trail can record the real outcome. Never throws for a delivery
 * failure: a dead SMTP host or missing WA token degrades to a logged warning.
 *
 * This is deliberately separate from NotificationService (which only creates
 * in-app rows) so the existing single-channel flow is left untouched.
 */
class TeguranNotifier
{
    /**
     * @param  array<int,string>  $channels  subset of inapp|email|whatsapp
     * @return array<int,string>  channels that were delivered successfully
     */
    public static function send(User $teamLead, SupportAgent $agent, Ticket $ticket, string $message, array $channels): array
    {
        $recipientUser = $agent->user; // may be null — most seeded agents aren't linked to a users row
        $email = $recipientUser?->email ?? $agent->email;
        $phone = $recipientUser?->whatsapp;
        $delivered = [];

        if (in_array('inapp', $channels, true) && $recipientUser) {
            NotificationService::notify(
                $recipientUser,
                $ticket,
                'sla_teguran',
                'Teguran SLA',
                $message,
            );
            $delivered[] = 'inapp';
        }

        if (in_array('email', $channels, true) && $email) {
            try {
                Mail::to($email)->send(new TeguranSlaMail($ticket, $agent->name, $teamLead->name, $message));
                $delivered[] = 'email';
            } catch (\Throwable $e) {
                Log::error('[Teguran] Email gagal terkirim.', ['to' => $email, 'error' => $e->getMessage()]);
            }
        }

        if (in_array('whatsapp', $channels, true) && $phone) {
            if (self::gateway()->send($phone, "*Teguran SLA · {$ticket->ticket_no}*\n\n{$message}")) {
                $delivered[] = 'whatsapp';
            }
        }

        return $delivered;
    }

    /**
     * Same multi-channel fan-out as send(), but for a Team Lead's rating
     * teguran — a reprimand about an agent's overall satisfaction rating,
     * not tied to any single ticket, so it carries no Ticket at all.
     *
     * @param  array<int,string>  $channels  subset of inapp|email|whatsapp
     * @return array<int,string>  channels that were delivered successfully
     */
    public static function sendRating(User $teamLead, SupportAgent $agent, float $rating, int $ratingCount, string $message, array $channels): array
    {
        $recipientUser = $agent->user;
        $email = $recipientUser?->email ?? $agent->email;
        $phone = $recipientUser?->whatsapp;
        $delivered = [];

        if (in_array('inapp', $channels, true) && $recipientUser) {
            NotificationService::notify(
                $recipientUser,
                null,
                'rating_teguran',
                'Teguran Rating',
                $message,
            );
            $delivered[] = 'inapp';
        }

        if (in_array('email', $channels, true) && $email) {
            try {
                Mail::to($email)->send(new TeguranRatingMail($agent->name, $teamLead->name, $rating, $ratingCount, $message));
                $delivered[] = 'email';
            } catch (\Throwable $e) {
                Log::error('[Teguran Rating] Email gagal terkirim.', ['to' => $email, 'error' => $e->getMessage()]);
            }
        }

        if (in_array('whatsapp', $channels, true) && $phone) {
            if (self::gateway()->send($phone, "*Teguran Rating · {$agent->name}*\n\n{$message}")) {
                $delivered[] = 'whatsapp';
            }
        }

        return $delivered;
    }

    /**
     * Resolves the active WhatsApp gateway from config — swap the driver in
     * config/notifications.php (or .env WA_DRIVER) to change providers.
     */
    private static function gateway(): WhatsAppGateway
    {
        $driver = config('notifications.whatsapp.driver', 'log');

        return match ($driver) {
            'fonnte' => new FonnteWhatsAppGateway(
                config('notifications.whatsapp.fonnte.token'),
                config('notifications.whatsapp.fonnte.endpoint'),
            ),
            default => new LogWhatsAppGateway(),
        };
    }

    /**
     * Recent teguran history for the Team Lead dashboard, read back from the
     * in-app notifications table (type = sla_teguran).
     *
     * @return \Illuminate\Support\Collection<int,TicketNotification>
     */
    public static function recent(int $limit = 15)
    {
        return TicketNotification::where('type', 'sla_teguran')
            ->with(['ticket:id,ticket_no,title', 'user:id,name'])
            ->latest('created_at')
            ->take($limit)
            ->get();
    }
}
