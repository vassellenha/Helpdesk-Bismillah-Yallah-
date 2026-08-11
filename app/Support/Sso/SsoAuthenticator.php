<?php

namespace App\Support\Sso;

use App\Models\AuditTrail;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
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

        if (self::mockRefusedInProduction()) {
            // Not an exception: throwing here would take down every page that
            // merely renders a login button. Handing back an unconfigured OIDC
            // provider routes into the "Konfigurasi SSO belum lengkap" path
            // that already exists, so nobody gets in and the screen explains
            // itself.
            Log::critical('[SSO] SSO_DRIVER=mock ditolak di environment production. Login SSO dimatikan sampai driver diperbaiki.');

            return new OidcSsoProvider([]);
        }

        return match ($config['driver'] ?? 'mock') {
            'oidc' => new OidcSsoProvider($config['oidc'] ?? []),
            default => new MockSsoProvider,
        };
    }

    public static function isMock(): bool
    {
        if (self::mockRefusedInProduction()) {
            return false;
        }

        return (config('integrations.sso.driver') ?? 'mock') !== 'oidc';
    }

    /**
     * Mock SSO fabricates identities — the login screen lets you pick any
     * employee and become them, with no password. That is exactly what makes
     * it useful for demos and catastrophic in production, and a wrong .env or
     * a forgotten default is all it takes to ship it. So production refuses
     * it outright rather than trusting the deploy to be careful.
     */
    private static function mockRefusedInProduction(): bool
    {
        return (config('integrations.sso.driver') ?? 'mock') !== 'oidc'
            && app()->isProduction();
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

        // An ordered list, tried in turn. SINTA's entry link identifies people
        // by username, while the OIDC token may carry NIP instead — one chain
        // serves both without either flow needing its own matching rule. A
        // plain string still works, so older config keeps functioning.
        $columns = collect((array) config('integrations.sso.match_by', 'username'))
            ->push(config('integrations.sso.fallback_match_by'))
            ->filter()
            ->unique()
            ->values();

        $primary = $columns->first() ?? 'username';
        $fallback = $columns->last();

        $user = null;

        foreach ($columns as $column) {
            if (blank($mapped[$column] ?? null)) {
                continue;
            }

            $user = User::where($column, $mapped[$column])->first();

            if ($user) {
                break;
            }
        }

        if (! $user) {
            $label = $mapped[$primary] ?? $mapped[$fallback] ?? 'identitas tanpa username/NPP/email';
            Log::warning('[SSO] Identitas tidak punya akun helpdesk.', ['claims' => $mapped]);

            return [null, "Akun untuk \"{$label}\" belum ada di helpdesk. Jalankan sinkronisasi data pegawai terlebih dahulu."];
        }

        if (! $user->isActive()) {
            return [null, "Akun {$user->name} nonaktif: ".strtolower((string) $user->inactiveReason()).'.'];
        }

        return [$user, null];
    }

    /**
     * The single moment any role — requester, approver, support IT, support
     * BPO, team lead, admin — actually opens the helpdesk, so it's the one
     * place a "login" audit entry belongs regardless of who logged in.
     */
    public static function login(User $user): void
    {
        // Guard Laravel ikut diisi, bukan hanya kunci sesi milik SSO sendiri.
        // Sejak seluruh rute dijaga middleware `auth`, sesi yang hanya tercatat
        // di kunci SSO tidak dianggap masuk sama sekali: orang yang baru saja
        // berhasil login lewat SINTA akan langsung dilempar balik ke halaman
        // masuk. Satu-satunya tempat kedua identitas itu perlu disatukan adalah
        // di sini, saat login benar-benar terjadi.
        Auth::login($user);

        session([
            self::SESSION_KEY => $user->id,
            self::SESSION_NAME => $user->name,
        ]);

        AuditTrail::record($user, [
            'module' => 'auth',
            'action' => 'login',
            'target_type' => 'user',
            'target_id' => $user->id,
            'target_name' => $user->name,
            'new_value' => ['roles' => $user->roles->pluck('name')->values()->all()],
            'description' => "{$user->name} login ke helpdesk.",
        ]);
    }

    public static function logout(): void
    {
        Auth::logout();
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
