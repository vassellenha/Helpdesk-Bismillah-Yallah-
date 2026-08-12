<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\RoleRegistry;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gerbang role di depan setiap grup rute: `->middleware(['auth', 'role:support'])`.
 *
 * Argumennya adalah KUNCI role (kunci config/helpdesk.php), bukan nama barisnya
 * di basis data — 'support', bukan 'Support IT'. Penerjemahannya lewat
 * RoleRegistry, dan kunci yang salah tulis melempar, tidak diam-diam lolos.
 *
 * Beberapa kunci boleh dirangkai (`role:support,team-lead`) dan berlaku SALAH
 * SATU: layar yang memang dipakai dua role sekaligus tidak perlu dua grup rute
 * yang isinya sama.
 *
 * Pasangannya `auth`, dan urutannya penting: `auth` dulu supaya tamu diantar ke
 * halaman masuk, bukan ditolak 403 seolah-olah mereka sudah masuk sebagai orang
 * yang salah.
 */
final class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$keys): Response
    {
        $user = $request->user();

        // Pertahanan berlapis, bukan jalur normal: `auth` sudah menangkap tamu
        // lebih dulu. Yang dijaga di sini adalah rute yang lupa memasang `auth`
        // — tanpa baris ini, tamu di rute seperti itu akan lolos gerbang role
        // karena tidak ada role yang bisa dibandingkan.
        if ($user === null) {
            abort(401);
        }

        $allowed = array_map(
            fn (string $key) => RoleRegistry::roleNameFor($key),
            $keys,
        );

        foreach ($allowed as $roleName) {
            if ($user->roles->contains('name', $roleName)) {
                return $next($request);
            }
        }

        abort(403, 'Akun Anda tidak punya role '.implode(' atau ', $allowed).'.');
    }
}
