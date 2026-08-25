<?php

return [
    'company' => 'Adhi Karya',
    'product' => 'Helpdesk 2.0',

    /*
    |--------------------------------------------------------------------------
    | Login pengembangan — TANPA PASSWORD. Wajib mati di produksi.
    |--------------------------------------------------------------------------
    |
    | Masuk cukup dengan email ATAU nomor telepon. Tidak ada password sama
    | sekali: gunanya berpindah-pindah identitas dengan cepat untuk menguji hak
    | akses tiap role, dan meminta password hanya menambah langkah tanpa
    | menambah kepastian apa pun di mesin sendiri.
    |
    | KONSEKUENSINYA HARUS DIBACA UTUH: siapa pun yang tahu email seseorang bisa
    | MENJADI orang itu. Tidak ada lapisan kedua. Karena itu saklar ini bukan
    | sekadar pengatur kenyamanan — ia SATU-SATUNYA hal yang memisahkan alat uji
    | ini dari pembajakan akun. Produksi masuk lewat SSO SINTA, titik.
    |
    | Dibaca dari APP_ENV lewat env(), BUKAN app()->isProduction(): berkas config
    | ikut ter-cache oleh `config:cache`, dan memanggil container saat cache
    | dibuat akan membekukan nilai lingkungan tempat cache itu dibuat.
    |
    | Jangan pernah menyetel HELPDESK_DEV_LOGIN=true di .env produksi.
    */
    'dev_login' => env('HELPDESK_DEV_LOGIN', env('APP_ENV', 'production') !== 'production'),

    /*
    |--------------------------------------------------------------------------
    | Tenggang penutupan otomatis tiket yang sudah Resolved
    |--------------------------------------------------------------------------
    |
    | Berapa HARI tiket berstatus Resolved menunggu konfirmasi requester sebelum
    | ditutup sendiri oleh `tickets:auto-close`. Dihitung dari `resolved_at`,
    | bukan dari tanggal dibuat — dan reopen mengosongkan resolved_at, jadi
    | tiket yang dibuka kembali otomatis keluar dari hitungan.
    |
    | Angka 0 (atau negatif) MEMATIKAN fitur ini sepenuhnya; ia tidak berarti
    | "tutup seketika". Ini disengaja: nilai yang salah ketik lebih baik membuat
    | penyapu diam daripada menutup seluruh antrean tiket dalam satu jalan.
    |
    | Tiket yang tertutup lewat jalur ini TIDAK punya satisfaction_rating —
    | tidak ada yang memberi nilai — sehingga ia tidak ikut terhitung di CSAT
    | Team Lead. Itu memang yang diinginkan: rating karangan lebih buruk
    | daripada rating yang kosong.
    */
    'auto_close_resolved_after_days' => (int) env('HELPDESK_AUTO_CLOSE_DAYS', 3),

    /*
    | Masa simpan notifikasi lonceng.
    |
    | Dua ambang, bukan satu. Yang sudah dibaca adalah mayoritas dan sudah
    | selesai tugasnya, jadi dibuang lebih cepat. Yang belum dibaca masih
    | berupa sinyal yang belum sempat dilihat orangnya — pegawai yang cuti
    | sebulan tidak boleh pulang ke lonceng bersih padahal ada yang terlewat —
    | jadi ditahan lebih lama.
    |
    | Menghapusnya tidak menghilangkan informasi: catatan permanennya ada di
    | Audit Trail dan pada tiketnya sendiri. Notifikasi hanya pemberitahuan.
    |
    | Angka 0 (atau negatif) MEMATIKAN penyapuan untuk kelompok itu, sama
    | seperti auto_close di atas — nilai yang salah ketik lebih baik membuat
    | penyapu diam daripada mengosongkan lonceng seluruh perusahaan.
    */
    'notification_retention' => [
        'read_days' => (int) env('HELPDESK_NOTIFICATION_READ_DAYS', 30),
        'unread_days' => (int) env('HELPDESK_NOTIFICATION_UNREAD_DAYS', 90),
    ],

    // Centralized role metadata used by the role-select portal and the
    // sidebar navigation. Swap the dummy `route` targets for real
    // controller actions as each workspace is wired to the database.
    //
    // `role` adalah nama baris di tabel `roles` yang diwakili kunci ini. Itu
    // satu-satunya jembatan antara kunci URL/tampilan ('support-bpo') dan
    // identitas yang tersimpan di basis data ('Support BPO'); middleware
    // `role:`, CurrentActor, dan switch role semuanya membacanya dari sini
    // supaya peta itu tidak ditulis ulang di tiga tempat berbeda.
    'roles' => [
        'requester' => [
            'key' => 'requester',
            'role' => 'Requester',
            'initials' => 'R',
            'label' => 'Requester',
            'description' => 'Membuat tiket baru, memantau status permintaan, dan melihat riwayat tiket sendiri.',
            'links' => [
                ['label' => 'Dashboard', 'route' => 'dashboard.requester'],
                ['label' => 'My Tickets', 'route' => 'requester.tickets'],
            ],
            'cta' => 'Dashboard · My Tickets →',
        ],
        'approver' => [
            'key' => 'approver',
            'role' => 'Approver',
            'initials' => 'A',
            'label' => 'Approver',
            'description' => 'Meninjau dan menyetujui atau menolak permintaan yang membutuhkan approval.',
            'links' => [
                ['label' => 'Approval Inbox', 'route' => 'dashboard.approver'],
                ['label' => 'My Tickets', 'route' => 'approver.tickets'],
            ],
            'cta' => 'Approval Inbox · My Tickets →',
        ],
        'support' => [
            'key' => 'support',
            'role' => 'Support IT',
            'initials' => 'SI',
            'label' => 'Support IT',
            'description' => 'Menangani tiket teknis (termasuk eskalasi dari Support BPO), mengelola progres penyelesaian.',
            'links' => [
                ['label' => 'Dashboard', 'route' => 'dashboard.support'],
                ['label' => 'My Tickets', 'route' => 'support.tickets'],
            ],
            'cta' => 'Dashboard · My Tickets →',
        ],
        'support-bpo' => [
            'key' => 'support-bpo',
            'role' => 'Support BPO',
            'initials' => 'SB',
            'label' => 'Support BPO',
            'description' => 'Menangani tiket masuk lini pertama sesuai aplikasi/PIC, eskalasi ke Support IT bila perlu penanganan lebih dalam.',
            'links' => [
                ['label' => 'Dashboard', 'route' => 'dashboard.support-bpo'],
                ['label' => 'My Tickets', 'route' => 'support-bpo.tickets'],
            ],
            'cta' => 'Dashboard · My Tickets →',
        ],
        'team-lead' => [
            'key' => 'team-lead',
            'role' => 'Team Lead',
            'initials' => 'T',
            'label' => 'Team Lead',
            'description' => 'Memantau performa tim support dan menangani eskalasi SLA.',
            'links' => [
                ['label' => 'Dashboard', 'route' => 'dashboard.team-lead'],
            ],
            'cta' => 'Dashboard →',
        ],
        'admin' => [
            'key' => 'admin',
            'role' => 'Administrator',
            'initials' => 'Ad',
            'label' => 'Admin',
            'description' => 'Konfigurasi user & role, SLA, approval matrix, katalog layanan, dan audit trail.',
            'links' => [
                ['label' => 'Admin Console', 'route' => 'admin.dashboard'],
            ],
            'cta' => 'Admin Console →',
        ],
        'eva' => [
            'key' => 'eva',
            'role' => 'Knowledge Administrator',
            'initials' => 'EV',
            'label' => 'EVA Knowledge',
            'description' => 'Mengelola pengetahuan yang dipakai asisten virtual EVA — artikel, FAQ, dokumen, pertanyaan tak terjawab, dan rekomendasi tipe tiket.',
            // Menunjuk ke konsol EVA yang sesungguhnya, BUKAN `dashboard.eva`.
            // Route itu masih ada dan masih merender mockup lama (KnowledgeConsole
            // + DummyData) — memilih peran EVA dari portal dulu mendarat di sana,
            // dan layar mockup itu tampak seperti konsol yang gagal memuat data.
            'links' => [
                ['label' => 'Knowledge Admin Console', 'route' => 'eva.coverage'],
            ],
            'cta' => 'Knowledge Admin Console →',
        ],
    ],

    // Top navigation for the Admin Console layout (resources/views/layouts/admin.blade.php).
    // Labels live in lang/{id,en}/admin.php under admin.nav.<key> and are resolved
    // in the view, not here: config is cached by `config:cache`, so calling __()
    // at this point would freeze one language into the cache file.
    'admin_nav' => [
        ['key' => 'dashboard', 'route' => 'admin.dashboard'],
        ['key' => 'users', 'route' => 'admin.users'],
        ['key' => 'integrations', 'route' => 'admin.integrations'],
        ['key' => 'sla', 'route' => 'admin.sla'],
        ['key' => 'audit', 'route' => 'admin.audit-trail'],
        ['key' => 'catalog', 'route' => 'admin.service-catalog'],
        ['key' => 'tickets', 'route' => 'admin.ticket-management'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Singkatan layanan untuk nomor tiket
    |--------------------------------------------------------------------------
    |
    | Dipakai TicketNumber::serviceCode(). Layanan yang TIDAK terdaftar di sini
    | disingkat otomatis: seluruh spasi dan tanda baca dibuang, lalu dibesarkan
    | — "ADELE" tetap "ADELE", "iBLAST" jadi "IBLAST".
    |
    | Daftar ini hanya untuk nama yang penyingkatan otomatisnya jadi terlalu
    | panjang atau tidak dikenali orang. Singkatan otomatis tidak bisa menebak
    | istilah yang sudah biasa dipakai di kantor; itu pengetahuan organisasi,
    | bukan aturan teks. Karena itu daftarnya ditulis tangan.
    |
    | ATURAN: hanya huruf dan angka. Satu tanda hubung saja akan merusak
    | pembacaan nomor urut, yang diambil dari segmen terakhir nomor tiket.
    |
    | Kalau ada singkatan di bawah yang bukan istilah yang dipakai sehari-hari,
    | ganti saja — lalu jalankan `php artisan tickets:renumber` supaya tiket
    | lama ikut menyesuaikan.
    */
    'service_codes' => [
        'ADHI MAN-POWER' => 'MANPOWER',
        'AKUN APLIKASI' => 'AKUN',
        'PERUBAHAN AKSES APLIKASI' => 'AKSES',
        'ARINA (DASHBOARD)' => 'ARINA',
        'Asset Management System' => 'AMS',
        'Sahabat APP' => 'SAHABAT',
        'SILO APPS' => 'SILO',
        'APB ERP' => 'APB',
        'APG ERP' => 'APG',
        'EA ADHI' => 'EA',
    ],
];
