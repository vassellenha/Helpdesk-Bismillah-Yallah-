<?php

namespace App\Support;

use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * The single door every outbound email in this app leaves through.
 *
 * Two rules live here rather than at each call site, because both are the kind
 * of thing that only hurts once it has been forgotten in one place:
 *
 * 1. Mail is queued, never sent inline. A real SMTP handshake to Gmail measured
 *    6.5 seconds on this app — long enough that the user's browser sits waiting
 *    on it, and a slow or unreachable mail host turns into a hung request and a
 *    second click. The `sync` fallback keeps a machine with no worker running
 *    honest: it sends inline rather than silently parking the message in `jobs`.
 *
 * 2. A delivery failure is never allowed to break the action that triggered it.
 *    Notifying someone is a side effect of resolving a ticket, not part of it —
 *    a dead SMTP host must not roll back the resolve. Failures degrade to a
 *    logged error, and the caller is told so it can record the real outcome.
 */
class MailDispatcher
{
    /**
     * @param  string  $tag  short bracketed prefix identifying the caller in the log
     * @return bool whether the message was accepted for delivery
     */
    public static function send(string $email, Mailable $mail, string $tag): bool
    {
        try {
            if (config('queue.default') === 'sync') {
                Mail::to($email)->send($mail);
            } else {
                // afterCommit(): several notifications are created inside a
                // DB::transaction() (SupportController::start() via
                // SupportGreeting, for one). Without this the mail job is
                // enqueued the moment it is dispatched, so a transaction that
                // rolls back afterwards still emails the user about something
                // that never happened. Today the database queue driver happens
                // to roll the job row back with it — but that is an accident of
                // the driver sharing the connection, and it stops being true the
                // day QUEUE_CONNECTION becomes redis.
                Mail::to($email)->queue($mail->afterCommit());
            }

            return true;
        } catch (\Throwable $e) {
            Log::error($tag.' Email gagal terkirim.', ['to' => $email, 'error' => $e->getMessage()]);

            return false;
        }
    }
}
