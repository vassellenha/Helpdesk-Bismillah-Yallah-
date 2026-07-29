<?php

return [
    'company' => 'Adhi Karya',
    'product' => 'Helpdesk 2.0',

    /*
    |--------------------------------------------------------------------------
    | SLA
    |--------------------------------------------------------------------------
    |
    | Escalating a ticket hands it to a different team that has to pick the case
    | up from scratch, so the resolution deadline is extended rather than left to
    | breach on work the new owner never had time to do. The extension is a
    | percentage of the policy's own resolution window, which keeps it
    | proportional to priority — a Critical ticket gains far less clock time than
    | a Low one. The original resolution_time_minutes is never overwritten; the
    | granted minutes accumulate in tickets.sla_extension_minutes so the original
    | commitment and every extension stay separately auditable.
    |
    */
    'sla' => [
        'escalation_extension_percent' => (int) env('SLA_ESCALATION_EXTENSION_PERCENT', 50),
    ],

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
            'links' => [
                ['label' => 'Knowledge Admin Console', 'route' => 'dashboard.eva'],
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
