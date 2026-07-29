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
                ['label' => 'Approval Workspace', 'route' => 'dashboard.approver'],
            ],
            'cta' => 'Approval Workspace →',
        ],
        'support' => [
            'key' => 'support',
            'initials' => 'S',
            'label' => 'Support',
            'description' => 'Menangani tiket masuk sesuai aplikasi/PIC, mengelola progres penyelesaian.',
            'links' => [
                ['label' => 'Support Workspace', 'route' => 'dashboard.support'],
            ],
            'cta' => 'Support Workspace →',
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
    'admin_nav' => [
        ['label' => 'Dashboard', 'route' => 'admin.dashboard'],
        ['label' => 'User & Role Management', 'route' => 'admin.users'],
        ['label' => 'Konfigurasi SLA', 'route' => 'admin.sla'],
        ['label' => 'Audit Trail Viewer', 'route' => 'admin.audit-trail'],
        ['label' => 'Service Catalog & Kategori Tiket', 'route' => 'admin.service-catalog'],
        ['label' => 'Ticket Management', 'route' => 'admin.ticket-management'],
    ],
];
