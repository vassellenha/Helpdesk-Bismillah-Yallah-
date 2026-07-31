<?php

namespace App\Http\Controllers;

use App\Support\Sso\MockSsoProvider;
use App\Support\Sso\SsoAuthenticator;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Login against ADHI's SINTA portal. Standard authorization-code round trip:
 * redirect() sends the browser out with a one-time `state`, callback() verifies
 * that same `state` came back before trusting anything in the request.
 *
 * The helpdesk stays usable without logging in — the mockup's fixed personas
 * still apply (see CurrentActor). Signing in narrows the app to the real person
 * wherever their roles allow it.
 */
class SsoController extends Controller
{
    private const STATE_KEY = 'sso_state';

    public function login()
    {
        return view('auth.login', [
            'isMock' => SsoAuthenticator::isMock(),
            'redirectUrl' => route('sso.redirect'),
            'employees' => SsoAuthenticator::isMock()
                ? MockSsoProvider::selectableUsers()->map(fn ($u) => [
                    'nip' => $u->nip,
                    'name' => $u->name,
                    'jabatan' => $u->jabatan,
                    'roles' => $u->roles->pluck('name')->all(),
                ])->values()
                : collect(),
            'currentUser' => SsoAuthenticator::user()?->only(['name', 'email']),
        ]);
    }

    public function redirect()
    {
        $provider = SsoAuthenticator::provider();

        // A misconfigured provider (e.g. SSO_DRIVER=oidc with no
        // SSO_AUTHORIZE_URL/SSO_CLIENT_ID yet) must not reach authorizeUrl():
        // it would build a URL with no host, and redirect()->away() sending
        // the browser to that relative target re-triggers this very route —
        // an infinite redirect loop instead of a readable error.
        if (! $provider->isConfigured()) {
            return redirect()->route('sso.login')
                ->with('sso_error', 'Konfigurasi SSO belum lengkap (SSO_CLIENT_ID / SSO_AUTHORIZE_URL kosong). Hubungi Administrator.');
        }

        // One-time nonce, checked in callback(): without it anyone could replay a
        // ?code they captured and have us accept it as a fresh login.
        $state = Str::random(40);
        session([self::STATE_KEY => $state]);

        return redirect()->away($provider->authorizeUrl($state, route('sso.callback')));
    }

    public function callback(Request $request)
    {
        $expected = session()->pull(self::STATE_KEY);

        if (blank($expected) || $request->query('state') !== $expected) {
            return redirect()->route('sso.login')
                ->with('sso_error', 'Sesi login tidak valid atau sudah kedaluwarsa. Silakan coba lagi.');
        }

        if ($request->filled('error')) {
            return redirect()->route('sso.login')
                ->with('sso_error', 'Portal SINTA menolak login: '.$request->query('error'));
        }

        $code = (string) $request->query('code');

        if (blank($code)) {
            return redirect()->route('sso.login')
                ->with('sso_error', 'Portal SINTA tidak mengirim authorization code.');
        }

        $claims = SsoAuthenticator::provider()->claimsFor($code, route('sso.callback'));

        if ($claims === null) {
            return redirect()->route('sso.login')
                ->with('sso_error', 'Gagal mengambil identitas dari portal SINTA. Cek konfigurasi dan log aplikasi.');
        }

        [$user, $error] = SsoAuthenticator::resolve($claims);

        if (! $user) {
            return redirect()->route('sso.login')->with('sso_error', $error);
        }

        SsoAuthenticator::login($user);

        return redirect()->route('portal.index')
            ->with('sso_success', "Masuk sebagai {$user->name}.");
    }

    public function logout()
    {
        SsoAuthenticator::logout();

        return redirect()->route('sso.login')->with('sso_success', 'Anda sudah keluar.');
    }
}
