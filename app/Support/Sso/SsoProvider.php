<?php

namespace App\Support\Sso;

/**
 * Two methods, one contract: every SSO backend (SINTA via OIDC, a SAML IdP, a
 * dev stub) is wrapped behind this so the rest of the app never knows which one
 * is active. Swapping providers is a one-line config change.
 */
interface SsoProvider
{
    /**
     * Where to send the browser to start the login. `$state` is the CSRF nonce
     * the caller stored in the session and will verify on the way back.
     */
    public function authorizeUrl(string $state, string $redirectUri): string;

    /**
     * Turn the code the portal handed back into that person's claims.
     *
     * Must not throw for a rejected login or an unreachable portal: return null
     * so the caller can show a message instead of a stack trace.
     *
     * @return array<string,mixed>|null raw claims, still in the portal's own
     *                                  field names — mapping is the caller's job
     */
    public function claimsFor(string $code, string $redirectUri): ?array;
}
