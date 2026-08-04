<?php

/*
|--------------------------------------------------------------------------
| Alur Tiket — Bahasa Indonesia
|--------------------------------------------------------------------------
|
| Dipakai App\Support\TicketFlow dan dirender di detail tiket SEMUA role,
| jadi teksnya cukup ditulis sekali di sini. String sudah diterjemahkan di
| server, sehingga komponen React tidak perlu kamus tambahan.
|
*/

return [
    'title' => 'Riwayat Status',
    'no_pic' => 'belum ada PIC',

    'stage' => [
        'requester' => 'Requester',
        'approver' => 'Approver',
        'no_approval' => 'Tanpa Approval',
        'support' => 'Support',
        'done' => 'Selesai',
    ],

    'sub' => [
        'submitted' => 'Pengajuan',
        'approval' => 'Persetujuan',
        'direct' => 'Langsung ke Support',
        'handling' => 'Penanganan',
        'handovers' => 'Penanganan · :count kali alih PIC',
        'awaiting_confirmation' => 'Menunggu konfirmasi requester',
        'closed' => 'Ditutup',
    ],

    'note' => [
        'draft' => 'Masih draft — belum dikirim requester.',
        'waiting' => 'Menunggu keputusan :approver.',
        'returned_approver' => 'Dikembalikan approver ke requester untuk diperbaiki.',
        'returned_support' => 'Dikembalikan Support ke requester untuk dilengkapi.',
        'rejected' => 'Ditolak approver — tiket tidak diteruskan ke Support.',
        'with_support' => 'Sedang ditangani Support — :pic.',
        'resolved' => 'Sudah diselesaikan Support, menunggu konfirmasi requester.',
        'closed' => 'Tiket selesai dan ditutup.',
    ],
];
