<?php

namespace App\Http\Controllers;

use App\Http\Middleware\SetLocale;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LocaleController extends Controller
{
    /**
     * Stores the chosen language for this session. Returns JSON because the
     * switcher is a React island; it reloads the page afterwards so the
     * server-rendered half comes back in the new language too.
     */
    public function update(Request $request): JsonResponse
    {
        $locale = $request->input('locale');

        // Checked by hand instead of $request->validate(): bootstrap/app.php
        // only renders exceptions as JSON for api/* paths, so a validation
        // failure here would come back as an HTML redirect and the fetch that
        // sent it would choke parsing it.
        if (! in_array($locale, SetLocale::SUPPORTED, true)) {
            return response()->json([
                'message' => 'Bahasa tidak dikenal.',
                'supported' => SetLocale::SUPPORTED,
            ], 422);
        }

        session([SetLocale::SESSION_KEY => $locale]);

        return response()->json(['locale' => $locale]);
    }
}
