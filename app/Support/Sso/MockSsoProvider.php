<?php

namespace App\Support\Sso;

use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Offline stand-in for SINTA. Instead of leaving the app, "authorize" points
 * back at our own callback with a code that encodes which seeded employee is
 * signing in, so the entire round trip — state check, claim mapping, account
 * lookup, session — runs and can be tested before any credential exists.
 *
 * Only ever active while SSO_DRIVER=mock. It fabricates claims, so it must
 * never be reachable in production; the Integrasi page shows a MOCK badge and
 * the login screen says so plainly.
 */
class MockSsoProvider implements SsoProvider
{
    public const CODE_PREFIX = 'mock:';

    public function isConfigured(): bool
    {
        return true;
    }

    public function authorizeUrl(string $state, string $redirectUri): string
    {
        // No external portal to visit — hand the browser straight back to the
        // callback, carrying whichever employee was picked on the login screen.
        return $redirectUri.'?'.http_build_query([
            'code' => self::CODE_PREFIX.request()->query('as', ''),
            'state' => $state,
        ]);
    }

    public function claimsFor(string $code, string $redirectUri): ?array
    {
        if (! str_starts_with($code, self::CODE_PREFIX)) {
            return null;
        }

        $nip = substr($code, strlen(self::CODE_PREFIX));
        $user = User::where('nip', $nip)->first();

        if (! $user) {
            return null;
        }

        // Deliberately shaped like a real OIDC userinfo response, in the
        // placeholder claim names config/integrations.php maps from.
        return [
            'nip' => $user->nip,
            'email' => $user->email,
            'name' => $user->name,
        ];
    }

    /**
     * Employees the login screen can offer as a "sign in as" choice. Only
     * accounts the sync actually knows about, so the mock cannot log in as
     * somebody SINTA would never return.
     *
     * @return Collection<int,User>
     */
    public static function selectableUsers()
    {
        return User::with('roles')
            ->whereNotNull('nip')
            ->where('nip', '!=', '')
            ->active()
            ->orderBy('name')
            ->get();
    }
}
