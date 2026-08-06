<?php

/*
|--------------------------------------------------------------------------
| Support IT & BPO — Bahasa Indonesia
|--------------------------------------------------------------------------
|
| Satu berkas untuk dua role: Support IT dan Support BPO memakai komponen yang
| sama, hanya BPO yang punya aksi tambahan "Eskalasi ke Tim IT".
|
| Nilai status tiket, prioritas, dan kategori tidak diterjemahkan di sini —
| itu nilai tersimpan di basis data. Hanya labelnya yang berganti bahasa.
|
*/

return [
    'nav' => [
        'dashboard' => 'Dashboard',
        'my_tickets' => 'Tiket Saya',
        'notifications' => 'Notifikasi',
        'no_notifications' => 'Belum ada notifikasi.',
    ],

    'dashboard' => [
        'title' => 'Dashboard',
        'assigned_to_me' => 'Ditugaskan ke Saya',
        'in_progress' => 'Sedang Dikerjakan',
        'near_sla' => 'Mendekati / Lewat SLA',
        'resolved' => 'Selesai',
        'queue' => 'Menunggu Bantuan Anda',
        'search' => 'Cari',
        'search_placeholder' => 'No. tiket, subjek…',
        'category' => 'Kategori',
        'priority' => 'Prioritas',
        'summary' => 'Ringkasan · :period Ini',
        'by_priority' => 'Tiket per Prioritas',
        'category_distribution' => 'Distribusi Kategori',
        'total' => 'Total :count',
        'no_rating' => 'Belum ada rating',
        'reviews' => ':count ulasan',
        'showing' => 'Menampilkan :shown dari :total tiket',
        'empty' => 'Tidak ada tiket yang cocok dengan filter ini.',
    ],

    'periods' => [
        'week' => 'Minggu',
        'month' => 'Bulan',
        'year' => 'Tahun',
    ],

    'filters' => [
        'all_category' => 'Semua Kategori',
        'all_priority' => 'Semua Prioritas',
    ],

    'history' => [
        'title' => 'Tiket Saya',
        'subtitle' => ':count tiket pernah ditugaskan ke Anda.',
        'search_placeholder' => 'Cari tiket, judul, atau layanan…',
        'all_service' => 'Semua Layanan',
        'showing' => 'Menampilkan :shown dari :total tiket',
        'empty' => 'Tidak ada tiket yang cocok dengan filter ini.',
        'periods' => [
            'last_30_days' => '30 Hari Terakhir',
            'last_3_months' => '3 Bulan Terakhir',
            'last_6_months' => '6 Bulan Terakhir',
            'this_year' => 'Tahun Ini',
        ],
    ],

    'cards' => [
        'all' => 'Semua',
        'total' => 'Total Tiket',
        'open' => 'Open',
        'in_progress' => 'In Progress',
        'resolved' => 'Resolved',
        'closed' => 'Closed',
    ],

    'columns' => [
        'id' => 'No. Tiket',
        'ticket' => 'Tiket',
        'subject' => 'Subjek',
        'service' => 'Layanan',
        'requester' => 'Requester',
        'priority' => 'Prioritas',
        'status' => 'Status',
        'sla' => 'SLA Penyelesaian',
        'created' => 'Dibuat',
    ],

    'detail' => [
        'back' => 'Tiket Saya',
        'mode' => 'Mode Support',
        'created_at' => 'Dibuat :at',
        'ticket_info' => 'Informasi Tiket',
        'no_description' => 'Tidak ada deskripsi.',
        'requester' => 'Requester',
        'unit' => 'Unit Kerja',
        'service' => 'Layanan',
        'contact' => 'Kontak',
        'attachments' => 'Lampiran',
        'forum' => 'Forum Diskusi',
        'forum_hint' => 'Percakapan antara Requester, Approver, dan Support terekam di sini.',
        'forum_empty' => 'Belum ada diskusi.',
        'forum_placeholder' => 'Tulis tanggapan untuk requester… (mis. minta detail transaksi, konfirmasi penyelesaian)',
        'send_reply' => 'Kirim Tanggapan',
        'sending' => 'Mengirim…',
        'send_failed' => 'Gagal mengirim pesan.',
        'action_failed' => 'Gagal mengirim tindakan.',
        'actions' => 'Aksi Penanganan',
        'btn_resolve' => 'Service Closed',
        'btn_escalate' => 'Eskalasi IT',
        'btn_return' => 'Returned',
        'actions_footnote' => 'Tindakan tercatat di riwayat status & audit trail. Requester menerima notifikasi.',
        'current_status' => 'Status saat ini',
        'pic' => 'PIC',
        'note_label' => 'Catatan (wajib)',
        'note_placeholder' => 'Tulis catatan penanganan…',
        'note_hint' => 'Isi catatan untuk mengaktifkan tombol di bawah.',
        'your_note' => 'Catatan Anda',
        'people' => 'Orang Terkait',
        'status_history' => 'Riwayat Status',
        'sla' => 'SLA',
        'escalated_banner' => 'Tiket Sudah Dieskalasi ke Tim IT.',
        'escalated_check' => 'Sudah dieskalasi ke Tim IT Lanjutan',
        'escalated_from_bpo' => 'Dieskalasi dari Support BPO',
        'reopened_banner' => 'Requester membuka kembali tiket ini',
    ],

    'start_modal' => [
        'title' => 'Mulai kerjakan tiket ini?',
        'body' => 'Tiket :id akan ditandai "In Progress". Anda bisa memulainya sekarang atau menundanya — tiket akan tetap Open sampai Anda memulainya.',
        'later' => 'Nanti',
        'now' => 'Kerjakan Sekarang',
        'starting' => 'Memulai…',
    ],

    'confirm' => [
        'sending' => 'Mengirim…',
        'no' => 'Tidak',
        'resolve' => [
            'title' => 'Tutup Layanan (Service Closed)?',
            'body' => 'Tiket :id akan ditandai selesai dan ditutup. Requester akan diminta memberi penilaian atas layanan. Tindakan ini tercatat di riwayat status.',
            'button' => 'Ya, Tutup Layanan',
        ],
        'escalate' => [
            'title' => 'Eskalasi ke Tim IT?',
            'body' => 'Tiket :id akan dieskalasi ke Tim IT Lanjutan untuk penanganan lebih dalam. Tindakan ini tercatat di riwayat status.',
            'button' => 'Ya, Eskalasi',
        ],
        'return' => [
            'title' => 'Kembalikan ke Requester?',
            'body' => 'Tiket :id akan dikembalikan ke requester untuk direvisi/dilengkapi. Requester akan menerima notifikasi dan bisa mengedit lalu mengirim ulang tiketnya. Tindakan ini tercatat di riwayat status.',
            'button' => 'Ya, Kembalikan',
        ],
    ],
];
