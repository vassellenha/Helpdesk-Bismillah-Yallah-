<?php

namespace App\Support\Sso;

use App\Models\User;
use App\Support\Auth\RefusedLoginAudit;
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

    /**
     * Resolves the configured provider — OIDC, satu-satunya yang ada.
     *
     * Provider tiruan yang dulu menemani ini sudah dihapus begitu helpdesk
     * masuk ke portal SINTA, dan bersamanya hilang pula seluruh penjagaan yang
     * dibutuhkannya: penolakan di environment production, bendera isMock(),
     * dan layar pemilih pegawai. Identitas yang dikarang sendiri tidak punya
     * tempat lagi di sini, jadi tidak ada lagi yang perlu dijaga.
     *
     * Konfigurasi yang belum lengkap TIDAK dilempar sebagai exception —
     * melakukannya akan menjatuhkan setiap halaman yang sekadar menampilkan
     * tombol masuk. OidcSsoProvider dengan konfigurasi kosong menjawab false
     * pada isConfigured(), dan pemanggilnya sudah punya jalur pesan
     * "Konfigurasi SSO belum lengkap" untuk kasus itu.
     */
    public static function provider(): SsoProvider
    {
        return new OidcSsoProvider(config('integrations.sso.oidc') ?? []);
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
            RefusedLoginAudit::record($user, 'sso');

            return [null, "Akun {$user->name} nonaktif: ".strtolower((string) $user->inactiveReason()).'.'];
        }

        return [$user, null];
    }

    /** Menyatukan identitas SSO dengan guard Laravel saat login SINTA berhasil. */
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

        // Baris audit "login" TIDAK ditulis di sini lagi. Auth::login() di atas
        // memicu event Login, dan App\Listeners\RecordLoginAudit yang
        // mencatatnya — satu tempat untuk semua pintu masuk, termasuk login
        // pengembangan yang tidak lewat kelas ini sama sekali. Menuliskannya di
        // sini juga akan menghasilkan dua baris untuk satu login.
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
