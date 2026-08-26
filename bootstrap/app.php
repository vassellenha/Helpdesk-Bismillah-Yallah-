<?php

use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\NoStoreWhenAuthenticated;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => EnsureRole::class,
        ]);

        /*
        | Halaman milik pengguna yang login tidak boleh disimpan browser.
        |
        | Logout sudah mematikan sesinya, tapi Chrome memulihkan halaman
        | terakhir apa adanya dari back-forward cache saat tombol Back ditekan
        | — dan `no-cache` bawaan Laravel tidak menyentuh cache itu sama
        | sekali. Di komputer bersama, orang berikutnya cukup menekan Back
        | untuk membaca daftar tiket orang sebelumnya.
        */
        $middleware->web(append: [
            NoStoreWhenAuthenticated::class,
        ]);

        // Ke mana tamu diantar saat menyentuh rute ber-`auth`. Defaultnya
        // `route('login')` — yang memang selalu terdaftar (lihat
        // Auth\LoginController) — tapi permintaan JSON tidak boleh dialihkan
        // sama sekali: React island yang memanggil endpoint di balik sesi yang
        // kedaluwarsa harus menerima 401 yang bisa dibaca kodenya, bukan HTML
        // halaman login berstatus 200 yang gagal di-parse res.json().
        $middleware->redirectGuestsTo(
            fn (Request $request) => $request->expectsJson() ? null : route('login'),
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Endpoint API mengembalikan JSON juga saat GAGAL, bukan hanya sukses.
        // Termasuk eva/api/* — konsol EVA memakai apiFetch yang mengharap JSON;
        // tanpa ini, error validasi dirender sebagai HTML dan frontend gagal
        // memparsenya. (Ditemukan oleh TrainingControllerTest.)
        /*
        | Passing a closure here REPLACES Laravel's default rule, it does not
        | add to it — so listing only api/* silently took JSON away from every
        | other endpoint the React islands call. Those live under /support,
        | /admin, /team-lead …, so an abort(422, 'reason') came back as an HTML
        | error page, res.json() threw, and the UI could only show a bare
        | "Request failed (422)" while the actual reason sat in the discarded
        | HTML. expectsJson() restores the default; api/* stays forced.
        */
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*', '*/api/*') || $request->expectsJson(),
        );
    })->create();
