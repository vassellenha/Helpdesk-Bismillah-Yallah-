<?php

use App\Http\Middleware\EnsureEvaConsoleAccess;
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
            'eva.access' => EnsureEvaConsoleAccess::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Endpoint API mengembalikan JSON juga saat GAGAL, bukan hanya sukses.
        // Termasuk eva/api/* — konsol EVA memakai apiFetch yang mengharap JSON;
        // tanpa ini, error validasi dirender sebagai HTML dan frontend gagal
        // memparsenya. (Ditemukan oleh TrainingControllerTest.)
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*', '*/api/*'),
        );
    })->create();
