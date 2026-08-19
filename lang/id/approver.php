<?php

/*
|--------------------------------------------------------------------------
| Approver — Bahasa Indonesia
|--------------------------------------------------------------------------
|
| Tahap kedua i18n, mengikuti pola file requester di lang/id dan lang/en.
|
| Nilai status tiket (Open/Resolved/…) dan keputusan approval
| (approved/rejected/revision_requested) TIDAK diterjemahkan di sini — itu
| nilai yang tersimpan di database dan dibandingkan di banyak tempat. Yang
| diterjemahkan hanya labelnya.
|
*/

return [
    'nav' => [
        'notifications' => 'Notifikasi',
        'no_notifications' => 'Belum ada notifikasi.',
    ],

    'inbox' => [
        'summary' => 'Ringkasan :period',
        'periods' => [
            'week' => 'Minggu',
            'month' => 'Bulan',
            'year' => 'Tahun',
        ],
        'title' => 'Kotak Masuk Approval',
        'pending' => 'Menunggu Persetujuan Anda',
        'search' => 'Cari',
        'search_placeholder' => 'No. tiket, subjek…',
        'category' => 'Kategori',
        'priority' => 'Prioritas',
        'trend' => 'Tren Keputusan Approval',
        'priority_distribution' => 'Distribusi Prioritas · :period Ini',
        'trend_sub' => 'Sepanjang :period ini',
        'approved' => 'Disetujui',
        'rejected_revision' => 'Ditolak & Revisi',
        'stat_pending' => 'Menunggu Persetujuan',
        'stat_approved_month' => 'Disetujui Bulan Ini',
        'stat_rejected_month' => 'Ditolak Bulan Ini',
        'stat_oldest' => 'Menunggu Terlama',
    ],

    'history' => [
        'title' => 'Tiket Saya',
        'search_placeholder' => 'Cari tiket, judul, atau layanan…',
        'empty' => 'Tidak ada tiket yang cocok dengan filter ini.',
        'all_service' => 'Semua Layanan',
    ],

    'cards' => [
        'total' => 'Total Tiket',
        'waiting' => 'Menunggu Keputusan',
        'open' => 'Open',
        'in_progress' => 'Sedang Dikerjakan',
        'resolved' => 'Selesai',
        'closed' => 'Ditutup',
    ],

    'columns' => [
        'id' => 'Tiket',
        'service' => 'Layanan',
        'status' => 'Status',
        'decision' => 'Keputusan',
        'note' => 'Catatan',
        'forwarded_to' => 'Diteruskan Ke',
        'created_at' => 'Waktu',
    ],

    'periods' => [
        'last_30_days' => '30 Hari Terakhir',
        'last_3_months' => '3 Bulan Terakhir',
        'last_6_months' => '6 Bulan Terakhir',
        'this_year' => 'Tahun Ini',
    ],

    'detail' => [
        'mode' => 'Mode Approval',
        'ticket_info' => 'Informasi Tiket',
        'requester' => 'Requester',
        'unit' => 'Unit Kerja',
        'catalog_service' => 'Layanan Katalog',
        'contact' => 'Kontak',
        'attachments' => 'Lampiran',
        'forum' => 'Forum Diskusi',
        'forum_empty' => 'Belum ada diskusi.',
        'forum_placeholder' => 'Tulis pertanyaan atau feedback untuk requester…',
        'decision_panel' => 'Panel Keputusan',
        'your_decision' => 'Keputusan Anda',
        'note_label' => 'Catatan / Feedback',
        'note_placeholder' => 'Wajib diisi sebelum menyetujui, meminta perbaikan, atau menolak',
        'note_required' => 'Catatan wajib diisi sebelum melanjutkan.',
        'your_note' => 'Catatan Anda',
        'forwarded_to' => 'Diteruskan ke: ',
        'status_history' => 'Riwayat Status',
        'sla' => 'SLA',
        'people' => 'Orang Terkait',
    ],

    'confirm' => [
        'sending' => 'Mengirim…',
        'no' => 'Tidak',
        'approved' => [
            'title' => 'Setujui permohonan ini?',
            'body' => 'Tiket :id akan disetujui dan diteruskan ke tahap berikutnya (Support).',
            'button' => 'Ya, Setujui',
        ],
        'revision_requested' => [
            'title' => 'Minta perbaikan?',
            'body' => 'Tiket :id akan dikembalikan ke requester untuk diperbaiki sesuai catatan Anda.',
            'button' => 'Ya, Minta Perbaikan',
        ],
        'rejected' => [
            'title' => 'Tolak permohonan ini?',
            'body' => 'Tiket :id akan ditolak dan ditutup. Requester akan menerima notifikasi.',
            'button' => 'Ya, Tolak',
        ],
    ],
];
