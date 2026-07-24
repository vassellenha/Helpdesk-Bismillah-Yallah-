<?php

namespace App\Support\WhatsApp;

use Illuminate\Support\Facades\Log;

/**
 * Default gateway for local/demo use: no external account needed. It records
 * the exact message that *would* be sent, so the Team Lead teguran flow is
 * fully exercisable before any real WhatsApp provider is wired up.
 */
class LogWhatsAppGateway implements WhatsAppGateway
{
    public function send(string $phone, string $message): bool
    {
        Log::info('[WhatsApp:log] Teguran WA', [
            'to' => $phone,
            'message' => $message,
        ]);

        return true;
    }
}
