<?php

return [
    'company' => 'Adhi Karya',
    'product' => 'Helpdesk 2.0',

    // Centralized role metadata used by the role-select portal and the
    // sidebar navigation. Swap the dummy `route` targets for real
    // controller actions as each workspace is wired to the database.
    'roles' => [
        'requester' => [
            'key' => 'requester',
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
];
