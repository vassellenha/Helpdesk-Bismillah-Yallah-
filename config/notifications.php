<?php

return [

    /*
    |--------------------------------------------------------------------------
    | WhatsApp Gateway
    |--------------------------------------------------------------------------
    |
    | Laravel cannot deliver WhatsApp on its own, so outbound WA messages go
    | through a pluggable gateway. The default "log" driver writes the message
    | to the Laravel log so the whole flow works locally with zero credentials;
    | switch to "fonnte" (or add your own driver) and fill in the token once a
    | real gateway account exists — no other code has to change.
    |
    */

    'whatsapp' => [

        'driver' => env('WA_DRIVER', 'log'),

        'fonnte' => [
            'token' => env('FONNTE_TOKEN'),
            'endpoint' => env('FONNTE_ENDPOINT', 'https://api.fonnte.com/send'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default channels for a Team Lead SLA teguran
    |--------------------------------------------------------------------------
    */
    'teguran_channels' => ['inapp', 'email', 'whatsapp'],

    /*
    |--------------------------------------------------------------------------
    | Email mirror of the in-app bell
    |--------------------------------------------------------------------------
    |
    | Every notification NotificationService raises is a candidate for email;
    | only the types listed below actually reach an inbox. An allowlist rather
    | than a blocklist on purpose — a notification type added later stays silent
    | until someone decides it is worth an interruption, which is the safe way
    | round when the cost of being wrong is a few hundred unwanted emails.
    |
    | The kill switch exists for the first deploy. Turn email off, let the app
    | settle, then turn it on: SLA alerts in particular are backfilled lazily
    | when a user opens a page (NotificationService::syncSlaAlerts), so flipping
    | this on over an existing ticket backlog is what a burst would look like.
    |
    */

    'email' => [

        'enabled' => env('NOTIFY_EMAIL_ENABLED', true),

        /*
         | Chosen so that every email either (a) confirms something the
         | recipient just did, or (b) asks them to act. Deliberately left out:
         |
         | - discussion_message — by far the highest-volume type; a busy ticket
         |   would email every participant on each reply, with no threading or
         |   digest to absorb it. The bell already covers it.
         | - sla_warning / sla_breach — see the note above about lazy backfill.
         |   SLA pressure also already has a human email path: the Team Lead's
         |   teguran, which is a deliberate act rather than a clock ticking over.
         | - ticket_closed / decision_recorded / history_updated — informational
         |   endings; nothing is asked of the recipient.
         |
         | Adding one back is a single line here.
         */
        'types' => [
            'ticket_created',       // requester's own submission, and a PIC being handed a ticket
            'waiting_decision',     // approver: action required
            'ticket_approved',
            'ticket_rejected',
            'ticket_resolved',
            'ticket_reopened',      // returned for revision: action required
            'ticket_escalated',
            'ticket_incoming_escalation',
        ],
    ],
];
