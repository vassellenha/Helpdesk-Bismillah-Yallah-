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

/*
 | Penyapu log EVA yang lewat masa simpan (default 14 hari,
 | config `eva.log_retention_days`).
 |
 | Pukul 02:00, SETELAH snapshot coverage pukul 01:00 — urutannya menentukan,
 | bukan kebetulan. Snapshot memotret angka hari itu dari kb_answer_logs yang
 | masih utuh; kalau penyapu jalan lebih dulu, potretnya sudah kehilangan baris
 | tak terjawab yang seharusnya ikut terhitung, dan tren Coverage berlubang
 | satu hari tanpa ada yang tahu sebabnya.
 |
 | Harian, bukan tiap jam: yang disapu berumur dua minggu, jadi menyapunya 24
 | kali sehari tidak membuat satu baris pun hilang lebih cepat dalam ukuran
 | yang berarti.
 */
Schedule::command('eva:purge-expired-logs')
    ->dailyAt('02:00')
    ->withoutOverlapping();

/*
 | Sinkronisasi data pegawai dari API perusahaan.
 |
 | Terdaftar hanya kalau saklarnya menyala (config
 | `integrations.employee_directory.auto_sync.enabled`, bawaannya MATI). Bukan
 | dijadwalkan lalu dijaga di dalam perintahnya: perintah yang terdaftar tapi
 | selalu keluar diam-diam tetap muncul di `schedule:list`, dan siapa pun yang
 | membacanya akan mengira sinkronisasinya berjalan.
 |
 | Alasan saklarnya mati secara bawaan ada di config/integrations.php —
 | ringkasnya, sinkronisasi menimpa kolom `status` yang mengunci akses.
 |
 | `withoutOverlapping` karena sinkronisasi penuh menyentuh seluruh baris users;
 | dua yang berjalan bersamaan bisa saling menimpa di tengah jalan.
 */
$autoSync = config('integrations.employee_directory.auto_sync');

if ($autoSync['enabled'] ?? false) {
    Schedule::command('employees:sync')
        ->dailyAt($autoSync['at'] ?? '03:00')
        ->withoutOverlapping();
}

/*
 | Penutup otomatis tiket yang sudah Resolved tapi tidak dikonfirmasi requester
 | (tenggang di config `helpdesk.auto_close_resolved_after_days`, bawaan 3 hari).
 |
 | TIAP JAM, bukan harian. Yang dijanjikan ke requester adalah sebuah countdown
 | di layar tiketnya; begitu hitungannya menyentuh nol, tiket harus benar-benar
 | tertutup dalam waktu dekat. Penyapu harian membuat countdown menampilkan
 | "habis" sampai belasan jam sebelum statusnya berubah — hitungan yang terlihat
 | bohong lebih buruk daripada tidak ada hitungan sama sekali.
 |
 | Tiap jam juga cukup murah: saringannya sudah dilakukan di basis data, dan
 | jalan yang tidak menemukan apa-apa tidak menyentuh satu baris pun.
 |
 | Aman diulang: tiket yang sudah Closed keluar dari saringan, jadi tidak ada
 | penutupan ganda maupun notifikasi kembar.
 */
Schedule::command('tickets:auto-close')
    ->hourly()
    ->withoutOverlapping();
