<?php

/**
 * Cek kesiapan broadcast tiket "Lainnya" untuk SETIAP Layanan.
 *
 * Jalankan:  php artisan tinker cek-pic.php
 *
 * Kode broadcast tidak mengenal nama Layanan — dia membaca kolom
 * support_agent_id / it_agent_id di Subject aktif Layanan itu, lalu menyaring
 * yang akunnya benar-benar punya role Support BPO / Support IT. Skrip ini
 * memperlihatkan hasil saringan itu apa adanya, jadi Layanan yang "siap" di
 * sini pasti jalan penuh, dan yang tidak siap kelihatan kurangnya di mana.
 */

use App\Models\ServiceCatalogService;
use App\Models\ServiceCatalogSubject;

$siap = $sebagian = $kosong = [];

foreach (ServiceCatalogService::orderBy('name')->get() as $svc) {
    if (ServiceCatalogSubject::where('service_id', $svc->id)->where('is_active', true)->doesntExist()) {
        continue;
    }

    $bpo = $svc->activeBpoAgents()->get()->unique('user_id');
    $it = $svc->activeItAgents()->get()->unique('user_id');

    $baris = sprintf('%-28s BPO=%-2d IT=%-2d', $svc->name, $bpo->count(), $it->count());

    if ($bpo->isEmpty()) {
        $kosong[] = $baris.'  <- tiket "Lainnya" tidak diterima siapa pun';
    } elseif ($it->isEmpty()) {
        $sebagian[] = $baris.'  <- eskalasi tidak punya PIC IT';
    } else {
        $siap[] = $baris;
    }
}

echo "\n=== SIAP PENUH (".count($siap).") ===\n".implode("\n", $siap);
echo "\n\n=== TAHAP BPO SAJA (".count($sebagian).") ===\n".implode("\n", $sebagian);
echo "\n\n=== BELUM JALAN SAMA SEKALI (".count($kosong).") ===\n".implode("\n", $kosong)."\n";
