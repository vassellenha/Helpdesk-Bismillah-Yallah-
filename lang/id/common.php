<?php

declare(strict_types=1);

/*
| Label yang dipakai komponen React BERSAMA — komponen yang muncul di layar
| Requester, Approver, Support, Team Lead, maupun Admin.
|
| Grup ini dikirim pada SETIAP halaman (lihat partials/translations.blade.php),
| jadi komponen bersama tidak lagi bergantung pada grup milik satu peran.
| Sebelumnya popup bersama memakai kunci admin.common.close, dan di layar
| non-admin — tempat grup 'admin' tidak dikirim — label itu terbaca sebagai
| kunci mentah "admin.common.close". Ditemukan saat UAT test case 7.
*/

return [
    'close' => 'Tutup',

    // Dipakai komponen Pagination bersama pada daftar tiket tiap peran.
    'pagination' => [
        'prev' => '← Sebelumnya',
        'next' => 'Berikutnya →',
        'page' => 'Halaman :page dari :total',
        'showing' => 'Menampilkan :from–:to dari :total tiket',
        'empty' => 'Menampilkan 0 dari 0 tiket',
    ],
];
