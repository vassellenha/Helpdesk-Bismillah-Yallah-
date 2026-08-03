<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies the language the user picked, stored in the session by
 * LocaleController. Session rather than a users column so the choice works
 * before anyone signs in — the mockup is browsable with no login at all.
 *
 * An unknown or missing value falls back to config('app.locale'), so a stale
 * session from an older build can never leave the app half-translated.
 */
class SetLocale
{
    /** Locales the interface actually ships translations for. */
    public const SUPPORTED = ['id', 'en'];

    public const SESSION_KEY = 'locale';

    public function handle(Request $request, Closure $next): Response
    {
        $locale = session(self::SESSION_KEY);

        if (in_array($locale, self::SUPPORTED, true)) {
            App::setLocale($locale);
        }

        return $next($request);
    }
}
