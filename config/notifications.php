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
];
