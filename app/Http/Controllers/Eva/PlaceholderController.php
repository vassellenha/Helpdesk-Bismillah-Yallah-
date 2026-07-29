<?php

namespace App\Http\Controllers\Eva;

use App\Http\Controllers\Controller;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Layar yang belum dibangun.
 *
 * Sengaja bukan halaman kosong: menampilkan nama layar dan menyatakan terus
 * terang bahwa ia belum ada, supaya tidak ada yang mengira layar itu rusak
 * atau menghabiskan waktu mencari bug yang tidak ada.
 */
class PlaceholderController extends Controller
{
    public function show(string $key): View
    {
        $item = $this->findNavItem($key);

        abort_if($item === null, 404);

        return view('eva.placeholder', [
            'label' => $item['label'],
        ]);
    }

    private function findNavItem(string $key): ?array
    {
        return (new Collection(config('eva.nav')))
            ->flatMap(fn (array $group) => $group['items'])
            ->firstWhere('key', $key);
    }
}
