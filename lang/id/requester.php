<?php

/*
|--------------------------------------------------------------------------
| Requester — Bahasa Indonesia
|--------------------------------------------------------------------------
|
| Pilot pertama i18n. Hanya layar Requester yang sudah dipindahkan ke sini;
| role lain menyusul bertahap supaya tiap tahap bisa diuji utuh.
|
| Nilai status tiket (Draft/Open/Resolved/…) SENGAJA tidak diterjemahkan di
| sini: itu nilai yang tersimpan di kolom tickets.status dan dibandingkan di
| banyak tempat. Labelnya diterjemahkan terpisah lewat status.php.
|
*/

return [
    'my_tickets' => 'Tiket Saya',
    'subtitle' => 'Lacak riwayat dan progres setiap tiket yang Anda kirim.',

    'search_placeholder' => 'Cari tiket, judul, atau layanan…',
    'showing' => 'Menampilkan :shown dari :total tiket',
    'empty' => 'Tidak ada tiket yang cocok dengan filter ini.',

    'columns' => [
        'id' => 'No. Tiket',
        'title' => 'Subjek',
        'category' => 'Kategori',
        'priority' => 'Prioritas',
        'status' => 'Status',
        'sla' => 'SLA',
        'created' => 'Dibuat',
    ],

    'filters' => [
        'all_service' => 'Semua Layanan',
        'all_subcategory' => 'Semua Sub Kategori',
        'all_category' => 'Semua Kategori Masalah',
        'all_priority' => 'Semua Prioritas',
    ],

    'periods' => [
        'last_30_days' => '30 Hari Terakhir',
        'last_3_months' => '3 Bulan Terakhir',
        'last_6_months' => '6 Bulan Terakhir',
        'this_year' => 'Tahun Ini',
    ],

    'returned_banner' => ':count tiket dikembalikan Tim Support untuk diperbaiki.',
    'returned_hint' => 'Buka tiketnya, baca catatan Support, lalu tekan “Edit & Resubmit” untuk mengirim ulang.',

    'nav' => [
        'notifications' => 'Notifikasi',
        'no_notifications' => 'Belum ada notifikasi.',
    ],

    'dashboard' => [
        'active_tickets' => 'Tiket Aktif',
        'awaiting_approval' => 'Menunggu Approval',
        'needs_response' => 'Butuh Respons Saya',
        'resolved' => 'Selesai',
        'closed' => 'Ditutup',
        'hint_awaiting_confirmation' => 'Menunggu konfirmasi Anda',
        'hint_last_6_months' => '6 bulan terakhir',
        'hint_waiting_response' => 'Menunggu respons Anda',
        'hint_all_caught_up' => 'Semua sudah ditangani',
    ],

    'sla_table' => [
        'title' => 'Tiket Mendekati Batas SLA',
        'subtitle' => 'Diurutkan dari sisa waktu paling sedikit',
        'search' => 'Cari tiket…',
        'empty' => 'Tidak ada tiket yang mendekati batas SLA.',
        'view_all' => 'Lihat semua tiket →',
    ],

    'charts' => [
        'created_vs_resolved' => 'Tiket Dibuat vs Selesai',
        'created' => 'Dibuat',
        'resolved' => 'Selesai',
        'sla_distribution' => 'Distribusi SLA',
        'sla_active' => 'SLA aktif',
    ],

    'detail' => [
        'status_history' => 'Riwayat Status',
        'service' => 'Layanan',
        'category' => 'Kategori',
        'subject' => 'Subjek',
        'close' => 'Tutup',
        'unassigned' => 'Belum ditugaskan',
        'support_team' => 'Tim Support',
        'resolved_banner' => 'Tiket Anda Telah Diselesaikan',
        'confirm_title' => 'Konfirmasi Penyelesaian',
        'confirm_question' => 'Apakah masalah Anda sudah teratasi?',
        'not_yet' => 'Belum',
        'not_yet_hint' => 'Masih ada kendala',
        'yes_done' => 'Ya, Sudah',
        'yes_done_hint' => 'Beri penilaian & tutup',
        'rate_question' => 'Bagaimana penilaian Anda atas layanan ini?',
        'rate_hint' => 'Ketuk bintang untuk menilai',
        'note_required' => 'Catatan untuk Tim Support *',
        'note_optional' => 'Catatan untuk Tim Support (opsional)',
        'reopened' => 'Dibuka kembali — dikirim ke Tim Support',
    ],

    'bulk' => [
        'selected' => ':count tiket dipilih',
        'delete' => 'Hapus Terpilih',
        'deleting' => 'Menghapus…',
        'confirm_title' => 'Hapus :count tiket :label?',
        'confirm_body' => 'Tiket yang dipilih akan dihapus permanen. Tindakan ini tidak bisa dibatalkan.',
        'label_draft' => 'draft',
        'label_returned' => 'yang dikembalikan',
        'no' => 'Tidak',
        'yes' => 'Ya, Hapus',
        'failed' => ':count tiket gagal dihapus. Coba lagi.',
    ],
    'priority_help' => [
        'Low' => 'Permintaan rutin. Pekerjaan Anda tetap bisa berjalan sambil menunggu.',
        'Medium' => 'Mengganggu pekerjaan Anda, tapi masih ada cara lain untuk sementara.',
        'High' => 'Pekerjaan Anda atau tim berhenti dan tidak ada cara lain.',
        'Critical' => 'Layanan mati total, banyak orang terdampak, atau menyentuh pelanggan/produksi.',
        'sla' => 'Target: respons :response · selesai :resolution',
        'hour' => ':count jam',
        'hours' => ':count jam',
        'day' => ':count hari',
        'days' => ':count hari',
        'inactive' => 'SLA untuk prioritas ini sedang dinonaktifkan Admin.',
    ],

    'sla_help' => [
        'ontrack' => 'Masih aman. Batas waktu penyelesaian belum lewat dan belum masuk ambang peringatan.',
        'warning' => 'Waktunya hampir habis. Sudah melewati ambang peringatan, tapi batas waktunya belum terlampaui.',
        'breach' => 'Batas waktu penyelesaian sudah terlampaui. Tiketnya tetap dikerjakan, tapi sudah tercatat lewat target.',
    ],

];
