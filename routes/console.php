<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 | Titik riwayat grafik tren Coverage EVA.
 |
 | HARIAN, walaupun grafiknya bulanan. Hari yang terlewat tidak bisa direkam
 | ulang — angkanya sudah berubah — sedangkan seberapa kasar tampilannya bisa
 | diubah kapan saja di CoverageCalculator::trend(). Jadi yang dipilih di sini
 | adalah resolusi terhalus yang murah, bukan resolusi yang kebetulan sedang
 | ditampilkan.
 |
 | Aman dijalankan berkali-kali: satu baris per tanggal (updateOrCreate).
 */
Schedule::command('eva:snapshot-coverage')
    ->dailyAt('01:00')
    ->withoutOverlapping();

/*
 | Penyapu dokumen yang macet di `processing`.
 |
 | Tiap 5 menit, bukan harian: yang ditunggu di sini adalah admin yang sedang
 | menatap layar Documents. Menunggu semalam untuk tahu unggahannya gagal sama
 | saja dengan tidak diberi tahu.
 |
 | Ambang umurnya sendiri (config `eva.stuck_after_minutes`) yang menentukan
 | dokumen mana yang layak divonis — perintah ini boleh sering jalan justru
 | karena ia tidak menyentuh dokumen yang masih wajar berjalan.
 */
Schedule::command('eva:sweep-stuck-documents')
    ->everyFiveMinutes()
    ->withoutOverlapping();
