<?php

namespace App\Http\Controllers;

use App\Support\CurrentActor;
use App\Support\RoleRegistry;
use Illuminate\View\View;

class PortalController extends Controller
{
    /**
     * Pemilih role — kini hanya menampilkan role yang BENAR-BENAR dipegang
     * orang yang masuk.
     *
     * Sebelumnya halaman ini menyerahkan seluruh config('helpdesk.roles') apa
     * adanya, jadi setiap pengunjung melihat ketujuh kartu dan bisa mengklik
     * mana pun. Itu masuk akal selagi belum ada login. Sekarang kartu ke layar
     * yang bukan haknya hanya berujung 403, dan menawarkannya membuat orang
     * mengira aksesnya rusak, bukan memang tidak ada.
     */
    public function index(): View
    {
        $user = CurrentActor::user();

        $held = $user
            ? RoleRegistry::switcherEntriesFor($user)->pluck('key')->all()
            : [];

        return view('portal.select-role', [
            'roles' => array_filter(
                RoleRegistry::all(),
                fn (array $meta) => in_array($meta['key'], $held, true),
            ),
            'user' => $user,
        ]);
    }
}
