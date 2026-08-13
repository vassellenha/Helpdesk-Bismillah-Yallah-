<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    | OpenAI — dipakai EVA HANYA untuk memparafrase jawaban yang sudah
    | ditemukan di Knowledge Base (lihat OpenAiParaphraser). Bukan sumber
    | jawaban: kalau KB tidak punya materinya, EVA tetap menyerah dan menawarkan
    | draf tiket, bukan mengarang lewat model.
    |
    | `paraphrase_enabled` sengaja default false. Mengaktifkannya berarti
    | potongan jawaban KB — yang berasal dari SOP internal — dikirim ke server
    | OpenAI. Itu keputusan izin data, bukan keputusan teknis, jadi harus
    | dinyalakan secara sadar per lingkungan.
    */
    'openai' => [
        'key' => env('OPENAI_API_KEY'),
        'paraphrase_enabled' => (bool) env('EVA_PARAPHRASE_ENABLED', false),
        'model' => env('EVA_PARAPHRASE_MODEL', 'gpt-4o-mini'),

        // Widget EVA menunggu balasan ini secara sinkron. Lebih panjang dari
        // ini, karyawan lebih baik menerima teks KB apa adanya daripada
        // menatap layar menunggu.
        'timeout' => (int) env('EVA_PARAPHRASE_TIMEOUT', 8),

        // Merangkum beberapa potongan KB jadi satu jawaban (OpenAiSynthesizer).
        // Terpisah dari parafrase karena wewenangnya lebih besar: yang dikirim
        // beberapa dokumen sekaligus, bukan satu jawaban yang sudah terpilih.
        'synthesis_enabled' => (bool) env('EVA_SYNTHESIS_ENABLED', false),
    ],

];
