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
use Illuminate\Mail\Mailable;
use Illuminate\Support\Collection;

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
     * @return array<int,string> channels that were delivered successfully
     */
    public static function send(User $teamLead, SupportAgent $agent, Ticket $ticket, string $message, array $channels): array
    {
        $recipientUser = $agent->user; // may be null — most seeded agents aren't linked to a users row
        $email = $recipientUser?->email ?? $agent->email;
        $phone = $recipientUser?->phone;
        $delivered = [];

        if (in_array('inapp', $channels, true) && $recipientUser) {
            NotificationService::notify(
                $recipientUser,
                NotificationService::roleForAgent($agent),
                $ticket,
                'sla_teguran',
                'Teguran SLA',
                $message,
            );
            $delivered[] = 'inapp';
        }

        if (in_array('email', $channels, true) && $email) {
            if (self::deliver($email, new TeguranSlaMail($ticket, $agent->name, $teamLead->name, $message), '[Teguran]')) {
                $delivered[] = 'email';
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
     * @return array<int,string> channels that were delivered successfully
     */
    public static function sendRating(User $teamLead, SupportAgent $agent, float $rating, int $ratingCount, string $message, array $channels): array
    {
        $recipientUser = $agent->user;
        $email = $recipientUser?->email ?? $agent->email;
        $phone = $recipientUser?->phone;
        $delivered = [];

        if (in_array('inapp', $channels, true) && $recipientUser) {
            NotificationService::notify(
                $recipientUser,
                NotificationService::roleForAgent($agent),
                null,
                'rating_teguran',
                'Teguran Rating',
                $message,
            );
            $delivered[] = 'inapp';
        }

        if (in_array('email', $channels, true) && $email) {
            if (self::deliver($email, new TeguranRatingMail($agent->name, $teamLead->name, $rating, $ratingCount, $message), '[Teguran Rating]')) {
                $delivered[] = 'email';
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
     * Hands one teguran email off for delivery, reporting whether it was
     * accepted. Shared by both fan-outs so the SLA and rating teguran cannot
     * end up with different delivery behaviour.
     */
    private static function deliver(string $email, Mailable $mail, string $tag): bool
    {
        // Queueing, the sync fallback and the swallow-and-log behaviour all live
        // in MailDispatcher now, shared with the bell's email mirror. Kept as a
        // named method here because the two fan-outs above both call it, and
        // routing them through one line is what guarantees an SLA teguran and a
        // rating teguran can never end up with different delivery behaviour.
        return MailDispatcher::send($email, $mail, $tag);
    }

    /**
     * Kalimat hasil untuk layar Team Lead.
     *
     * Memisahkan yang SUDAH terjadi dari yang baru DIJADWALKAN, dan itu bukan
     * kehalusan bahasa. Lonceng in-app benar-benar sudah terisi saat fungsi ini
     * dipanggil; email baru dititipkan ke antrean, dan pengiriman SMTP-nya
     * terjadi belakangan di worker. Kalau SMTP-nya bermasalah, kegagalan itu
     * mendarat di `failed_jobs` — jauh dari mata orang yang menekan tombolnya.
     *
     * Kalimat lama berbunyi "Teguran terkirim via email" untuk kedua keadaan.
     * Selama berhari-hari MAIL_MAILER di produksi masih `log`, kalimat itu
     * tampil setiap kali dan tidak sekali pun keliru secara teknis — emailnya
     * memang "terkirim", cuma ke berkas log. Justru itu yang membuat masalahnya
     * tidak ketahuan: layar tidak pernah memberi alasan untuk curiga.
     *
     * @param  array<int,string>  $delivered
     */
    public static function resultMessage(array $delivered, string $prefix = 'Teguran'): string
    {
        if ($delivered === []) {
            return __('teamlead.teguran.none', ['prefix' => $prefix]);
        }

        $channels = implode(', ', $delivered);

        // Antrean `sync` mengirim inline, jadi di sana "terkirim" memang benar.
        $ditunda = in_array('email', $delivered, true) && config('queue.default') !== 'sync';

        return $ditunda
            ? __('teamlead.teguran.queued', ['prefix' => $prefix, 'channels' => $channels])
            : __('teamlead.teguran.sent', ['prefix' => $prefix, 'channels' => $channels]);
    }

    private static function gateway(): WhatsAppGateway
    {
        $driver = config('notifications.whatsapp.driver', 'log');

        return match ($driver) {
            'fonnte' => new FonnteWhatsAppGateway(
                config('notifications.whatsapp.fonnte.token'),
                config('notifications.whatsapp.fonnte.endpoint'),
            ),
            default => new LogWhatsAppGateway,
        };
    }

    /**
     * Recent teguran history for the Team Lead dashboard, read back from the
     * in-app notifications table (type = sla_teguran).
     *
     * @return Collection<int,TicketNotification>
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
