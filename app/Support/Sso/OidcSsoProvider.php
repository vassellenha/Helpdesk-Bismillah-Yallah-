<?php

namespace App\Support\Sso;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * SINTA over OAuth2 / OpenID Connect authorization-code flow — the shape almost
 * every corporate portal exposes: redirect to /authorize, receive a ?code, trade
 * it for an access token, then read the person's claims from /userinfo.
 *
 * Fill SSO_CLIENT_ID / SSO_CLIENT_SECRET and the three URLs in .env, then set
 * SSO_DRIVER=oidc. If SINTA turns out to speak SAML instead, that belongs in a
 * sibling class implementing the same interface — nothing else has to move.
 */
class OidcSsoProvider implements SsoProvider
{
    /** @param array<string,mixed> $config */
    public function __construct(private array $config) {}

    public function authorizeUrl(string $state, string $redirectUri): string
    {
        return rtrim((string) $this->config['authorize_url'], '?').'?'.http_build_query([
            'response_type' => 'code',
            'client_id' => $this->config['client_id'],
            'redirect_uri' => $redirectUri,
            'scope' => $this->config['scopes'] ?? 'openid profile email',
            'state' => $state,
        ]);
    }

    public function claimsFor(string $code, string $redirectUri): ?array
    {
        if (blank($this->config['token_url']) || blank($this->config['client_id'])) {
            Log::warning('[SSO:oidc] Konfigurasi belum lengkap — SSO_TOKEN_URL / SSO_CLIENT_ID kosong.');

            return null;
        }

        $timeout = (int) ($this->config['timeout'] ?? 15);

        try {
            $token = Http::timeout($timeout)->asForm()->acceptJson()->post($this->config['token_url'], [
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => $redirectUri,
                'client_id' => $this->config['client_id'],
                'client_secret' => $this->config['client_secret'],
            ]);

            if (! $token->successful()) {
                Log::error('[SSO:oidc] Tukar code gagal.', ['status' => $token->status()]);

                return null;
            }

            $accessToken = $token->json('access_token');

            if (blank($accessToken)) {
                Log::error('[SSO:oidc] Respons token tidak memuat access_token.');

                return null;
            }

            $userinfo = Http::timeout($timeout)
                ->withToken($accessToken)
                ->acceptJson()
                ->get($this->config['userinfo_url']);

            if (! $userinfo->successful()) {
                Log::error('[SSO:oidc] Ambil userinfo gagal.', ['status' => $userinfo->status()]);

                return null;
            }

            $claims = $userinfo->json();

            return is_array($claims) ? $claims : null;
        } catch (\Throwable $e) {
            Log::error('[SSO:oidc] Exception saat proses login.', ['error' => $e->getMessage()]);

            return null;
        }
    }
}
