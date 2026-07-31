<?php

namespace App\Support\Sso;

use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * The bridge between "SINTA says this is who they are" and "this is their
 * helpdesk account".
 *
 * Never provisions. An identity with no matching users row is refused, because
 * creating people is the employee-directory sync's job (EmployeeSync) — if both
 * could create accounts they would eventually disagree about who exists. A
 * refusal here means "run the sync first", not "make something up".
 */
class SsoAuthenticator
{
    public const SESSION_KEY = 'sso_user_id';

    public const SESSION_NAME = 'sso_user_name';

    /** Resolves the configured provider. */
    public static function provider(): SsoProvider
    {
        $config = config('integrations.sso');

        return match ($config['driver'] ?? 'mock') {
            'oidc' => new OidcSsoProvider($config['oidc'] ?? []),
            default => new MockSsoProvider,
        };
    }

    public static function isMock(): bool
    {
        return (config('integrations.sso.driver') ?? 'mock') !== 'oidc';
    }

    /**
     * Translate the portal's claims into our own field names.
     *
     * @param  array<string,mixed>  $claims
     * @return array<string,mixed>
     */
    public static function mapClaims(array $claims): array
    {
        $mapped = [];

        foreach (config('integrations.sso.claim_map', []) as $claim => $column) {
            if (! array_key_exists($claim, $claims)) {
                continue;
            }

            $value = $claims[$claim];
            $mapped[$column] = is_string($value) ? trim($value) : $value;
        }

        return $mapped;
    }

    /**
     * The local account this identity belongs to, or null with a reason.
     *
     * @param  array<string,mixed>  $claims
     * @return array{0:?User,1:?string} [user, error message]
     */
    public static function resolve(array $claims): array
    {
        $mapped = self::mapClaims($claims);
        $primary = config('integrations.sso.match_by', 'nip');
        $fallback = config('integrations.sso.fallback_match_by', 'email');

        $user = null;

        foreach ([$primary, $fallback] as $column) {
            if (blank($mapped[$column] ?? null)) {
                continue;
            }

            $user = User::where($column, $mapped[$column])->first();

            if ($user) {
                break;
            }
        }

        if (! $user) {
            $label = $mapped[$primary] ?? $mapped[$fallback] ?? 'identitas tanpa NIP/email';
            Log::warning('[SSO] Identitas tidak punya akun helpdesk.', ['claims' => $mapped]);

            return [null, "Akun untuk \"{$label}\" belum ada di helpdesk. Jalankan sinkronisasi data pegawai terlebih dahulu."];
        }

        if (! $user->isActive()) {
            return [null, "Akun {$user->name} nonaktif: ".strtolower((string) $user->inactiveReason()).'.'];
        }

        return [$user, null];
    }

    public static function login(User $user): void
    {
        session([
            self::SESSION_KEY => $user->id,
            self::SESSION_NAME => $user->name,
        ]);
    }

    public static function logout(): void
    {
        session()->forget([self::SESSION_KEY, self::SESSION_NAME]);
    }

    /**
     * The signed-in user, or null when nobody is. Re-checks that the account is
     * still active on every call — an Admin disabling access, or the sync marking
     * someone resigned, must take effect without waiting for them to log out.
     */
    public static function user(): ?User
    {
        $id = session(self::SESSION_KEY);

        if (! $id) {
            return null;
        }

        $user = User::find($id);

        if (! $user || ! $user->isActive()) {
            self::logout();

            return null;
        }

        return $user;
    }
}
