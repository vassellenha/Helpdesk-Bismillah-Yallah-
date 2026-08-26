<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Melarang browser menyimpan halaman milik pengguna yang sedang login.
 *
 * Logout sudah mematikan sesinya dengan benar: permintaan baru ke halaman mana
 * pun dialihkan ke layar Masuk. Yang bocor bukan aksesnya, melainkan
 * TAMPILANNYA. `Cache-Control: no-cache` — bawaan Laravel untuk respons
 * bersesi — hanya menyuruh browser memvalidasi ulang sebelum memakai cache
 * HTTP, dan sama sekali tidak menyentuh back-forward cache. Chrome menyimpan
 * halaman terakhir apa adanya di memori lalu memulihkannya utuh begitu tombol
 * Back ditekan.
 *
 * Di komputer bersama, orang berikutnya cukup menekan Back untuk membaca
 * daftar tiket, nama, unit kerja, dan jumlah notifikasi orang sebelumnya. Ia
 * tidak bisa berbuat apa-apa dari sana — setiap tindakan mental ke layar Masuk
 * — tapi ia bisa membacanya, dan untuk daftar tiket internal itu sudah cukup.
 *
 * `no-store` satu-satunya arahan yang membuat browser tidak menyimpan
 * salinannya sama sekali, termasuk di bfcache.
 *
 * Hanya dipasang saat ADA yang login. Layar Masuk dan halaman portal untuk
 * tamu tidak membawa apa pun yang perlu dirahasiakan, dan justru itulah
 * halaman yang paling sering dibuka ulang.
 */
class NoStoreWhenAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->user() !== null) {
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0, private');
        }

        return $response;
    }
}
