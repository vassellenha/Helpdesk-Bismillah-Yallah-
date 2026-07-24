<?php

namespace App\Support\WhatsApp;

/**
 * One method, one contract: every WhatsApp provider (Fonnte, Wablas, Meta
 * Cloud API, Twilio, …) is wrapped behind this so the rest of the app never
 * knows which one is active. Swapping providers is a one-line config change.
 */
interface WhatsAppGateway
{
    /**
     * Send a plain-text WhatsApp message. Must not throw for a delivery
     * failure — return false so callers can degrade gracefully.
     */
    public function send(string $phone, string $message): bool;
}
