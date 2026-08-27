<?php

namespace App\Support\Sso;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Verifies a portal-initiated entry link (SINTA -> /auth/sso/entry).
 *
 * Everything here exists because the identity arrives inside a URL. A URL is
 * copied, logged by proxies, kept in browser history and pasted into chats, so
 * an unproven `?nip=` would let anyone sign in as anyone. Three separate things
 * are therefore required before a link is trusted:
 *
 *   1. a signature, proving SINTA produced it and nobody edited the NIP
 *   2. a timestamp, so a leaked link stops working within minutes
 *   3. single use, so a link that did leak cannot be replayed
 *
 * Returns the claims (same shape the OIDC provider returns, so
 * SsoAuthenticator::resolve() handles both identically) or null on any failure.
 * Failures are deliberately indistinguishable to the caller — a portal-initiated
 * link that says *why* it was rejected is a probing oracle.
 */
class SsoEntry
{
    /**
     * Params covered by the signature, in this exact order.
     *
     * Email is the whole identity ADHI's portal sends — no name, no NIP — so
     * the signed string stays short and there are no empty placeholders for
     * either side to get wrong. Changing this list invalidates every signature
     * the portal produces, so it moves only by agreement with them.
     */
    private const SIGNED = ['email', 'ts', 'nonce'];

    public static function enabled(): bool
    {
        return self::driver() !== 'disabled';
    }

    public static function driver(): string
    {
        return (string) (config('integrations.sso.entry.driver') ?? 'disabled');
    }

    /**
     * The exact string both sides sign, and its signature.
     *
     * Public so the `sso:entry-link` command builds test links through this
     * same code instead of repeating the format. A generator that drifts from
     * its verifier produces links that fail for reasons nobody can see, which
     * is the worst possible way to debug an integration.
     *
     * @param  array<string,string|int>  $params
     */
    public static function canonical(array $params): string
    {
        return collect(self::SIGNED)
            ->map(fn (string $k) => $k.'='.(string) ($params[$k] ?? ''))
            ->implode('&');
    }

    /** @param array<string,string|int> $params */
    public static function sign(array $params, string $secret): string
    {
        return hash_hmac('sha256', self::canonical($params), $secret);
    }

    /** @return array<string,mixed>|null */
    public static function verify(Request $request): ?array
    {
        return match (self::driver()) {
            'hmac' => self::verifyHmac($request),
            'plain' => self::verifyPlain($request),
            default => null,
        };
    }

    /**
     * TANPA PEMBUKTIAN APA PUN — email diambil apa adanya dari URL.
     *
     * Ini bukan skema keamanan, ini penundaan. Siapa pun yang bisa menebak
     * alamat email rekannya bisa mengetik ulang URL-nya dan menjadi orang itu:
     * tidak ada tanda tangan yang bisa gagal dicocokkan, tidak ada tenggat
     * yang bisa lewat, tidak ada nonce yang bisa habis. Satu-satunya yang
     * masih menahan adalah pemeriksaan sesudahnya — email harus punya akun
     * helpdesk dan akunnya harus aktif.
     *
     * Ada di sini karena portal SINTA belum menandatangani tautannya, dan
     * integrasinya perlu bisa diuji lebih dulu. Begitu mereka siap, pindah ke
     * SSO_ENTRY_DRIVER=hmac — jalurnya sudah ada di bawah dan tidak menuntut
     * satu baris pun perubahan di luar berkas ini.
     *
     * Setiap pemakaian dicatat sebagai warning, sengaja berisik: jalur ini
     * tidak boleh diam-diam ikut terbawa ke produksi tanpa ada yang sadar.
     *
     * @return array<string,mixed>|null
     */
    private static function verifyPlain(Request $request): ?array
    {
        // input(), bukan query(): SINTA mengirimkannya lewat body POST
        // (Auth Type REMOTE_LOGIN, User Param "email"), sementara tautan biasa
        // membawanya di query string. input() membaca keduanya.
        //
        // Password yang ikut dikirim portal (Pass Param) sengaja diabaikan —
        // helpdesk tidak pernah menyimpan kata sandi siapa pun, dan tidak punya
        // apa pun untuk membandingkannya.
        $email = trim((string) $request->input('email', ''));

        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        Log::warning('[SSO entry] Masuk lewat parameter TANPA tanda tangan — identitas tidak dibuktikan.', [
            'email' => $email,
            'ip' => $request->ip(),
        ]);

        return ['email' => $email];
    }

    /**
     * Shared-secret scheme:
     *
     *   sig = HMAC-SHA256("email=..&ts=..&nonce=..", secret)
     *
     * @return array<string,mixed>|null
     */
    private static function verifyHmac(Request $request): ?array
    {
        $secret = (string) config('integrations.sso.entry.secret');

        if ($secret === '') {
            Log::warning('[SSO entry] SSO_ENTRY_DRIVER=hmac but SSO_ENTRY_SECRET is empty — every link is refused.');

            return null;
        }

        $sig = (string) $request->query('sig', '');
        $nonce = (string) $request->query('nonce', '');
        $ts = (int) $request->query('ts', 0);

        if ($sig === '' || $nonce === '' || $ts <= 0) {
            return null;
        }

        // Expiry is checked before the signature so a stale-but-valid link
        // cannot be distinguished from a forged one by timing the response.
        $ttl = max(30, (int) config('integrations.sso.entry.ttl', 120));

        if (abs(time() - $ts) > $ttl) {
            Log::info('[SSO entry] Link ditolak: kedaluwarsa atau jam server beda jauh.', ['ts' => $ts]);

            return null;
        }

        $expected = self::sign($request->query(), $secret);

        // hash_equals, not ===: a plain comparison leaks how many leading bytes
        // matched, which is enough to reconstruct a signature byte by byte.
        if (! hash_equals($expected, $sig)) {
            Log::warning('[SSO entry] Link ditolak: tanda tangan tidak cocok.');

            return null;
        }

        // Single use. Kept a little longer than the TTL so a replay cannot slip
        // through in the gap between the nonce expiring and the link expiring.
        $key = 'sso_entry_nonce:'.hash('sha256', $nonce);

        if (! Cache::add($key, true, $ttl + 60)) {
            Log::warning('[SSO entry] Link ditolak: sudah dipakai (replay).');

            return null;
        }

        return array_filter([
            'email' => $request->query('email'),
        ], fn ($v) => filled($v));
    }
}
