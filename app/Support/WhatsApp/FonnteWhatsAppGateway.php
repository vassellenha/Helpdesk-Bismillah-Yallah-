<?php

namespace App\Support\WhatsApp;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Fonnte gateway (fonnte.com) — the common low-cost Indonesian WhatsApp
 * gateway: a single authenticated POST with `target` + `message`. Provide
 * FONNTE_TOKEN in .env to activate; until then config keeps the "log" driver.
 */
class FonnteWhatsAppGateway implements WhatsAppGateway
{
    public function __construct(
        private ?string $token,
        private string $endpoint = 'https://api.fonnte.com/send',
    ) {}

    public function send(string $phone, string $message): bool
    {
        if (empty($this->token)) {
            Log::warning('[WhatsApp:fonnte] FONNTE_TOKEN kosong — teguran WA tidak terkirim.', ['to' => $phone]);

            return false;
        }

        try {
            $response = Http::withHeaders(['Authorization' => $this->token])
                ->asForm()
                ->post($this->endpoint, [
                    'target' => $phone,
                    'message' => $message,
                ]);

            if ($response->successful()) {
                return true;
            }

            Log::error('[WhatsApp:fonnte] Gagal kirim teguran WA.', ['to' => $phone, 'status' => $response->status(), 'body' => $response->body()]);
        } catch (\Throwable $e) {
            Log::error('[WhatsApp:fonnte] Exception saat kirim teguran WA.', ['to' => $phone, 'error' => $e->getMessage()]);
        }

        return false;
    }
}
