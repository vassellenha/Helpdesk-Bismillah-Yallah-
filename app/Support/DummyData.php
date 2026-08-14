<?php

namespace App\Support;

/**
 * Single source of dummy data for the mockup. Every dashboard pulls from
 * here instead of hardcoding rows in Blade/React, so swapping this class
 * for real Eloquent queries later only touches one place.
 */
class DummyData
{
    public static function agents(): array
    {
        return [
            ['id' => 1, 'name' => 'Rizky Pratama', 'role' => 'Support', 'load' => 6, 'sla_ontime' => 92],
            ['id' => 2, 'name' => 'Dewi Anjani', 'role' => 'Support', 'load' => 4, 'sla_ontime' => 88],
            ['id' => 3, 'name' => 'Fajar Nugroho', 'role' => 'Support', 'load' => 8, 'sla_ontime' => 76],
            ['id' => 4, 'name' => 'Sinta Wulandari', 'role' => 'Support', 'load' => 3, 'sla_ontime' => 97],
        ];
    }

    public static function categories(): array
    {
        return ['Jaringan', 'Aplikasi', 'Hardware', 'Akses & Akun', 'Lainnya'];
    }

    public static function tickets(): array
    {
        return [
            [
                'id' => 'TCK-2026-0142',
                'title' => 'VPN tidak bisa connect dari site Balikpapan',
                'requester' => 'Budi Santoso',
                'category' => 'Jaringan',
                'priority' => 'High',
                'status' => 'Open',
                'assignee' => 'Rizky Pratama',
                'sla_due' => '2026-07-21 09:00',
                'created_at' => '2026-07-20 08:12',
            ],
            [
                'id' => 'TCK-2026-0141',
                'title' => 'Approval SPK tidak muncul di aplikasi ERP',
                'requester' => 'Maria Christin',
                'category' => 'Aplikasi',
                'priority' => 'Urgent',
                'status' => 'In Progress',
                'assignee' => 'Dewi Anjani',
                'sla_due' => '2026-07-20 15:00',
                'created_at' => '2026-07-20 07:40',
            ],
            [
                'id' => 'TCK-2026-0140',
                'title' => 'Laptop tidak bisa boot setelah update',
                'requester' => 'Andi Wijaya',
                'category' => 'Hardware',
                'priority' => 'Medium',
                'status' => 'Waiting Approval',
                'assignee' => 'Fajar Nugroho',
                'sla_due' => '2026-07-22 12:00',
                'created_at' => '2026-07-19 16:05',
            ],
            [
                'id' => 'TCK-2026-0139',
                'title' => 'Reset akses akun email tidak masuk',
                'requester' => 'Siti Nurhaliza',
                'category' => 'Akses & Akun',
                'priority' => 'Low',
                'status' => 'Resolved',
                'assignee' => 'Sinta Wulandari',
                'sla_due' => '2026-07-19 10:00',
                'created_at' => '2026-07-18 09:22',
            ],
            [
                'id' => 'TCK-2026-0138',
                'title' => 'Printer lantai 5 tidak bisa dipakai',
                'requester' => 'Budi Santoso',
                'category' => 'Hardware',
                'priority' => 'Medium',
                'status' => 'Closed',
                'assignee' => 'Rizky Pratama',
                'sla_due' => '2026-07-18 10:00',
                'created_at' => '2026-07-17 13:11',
            ],
            [
                'id' => 'TCK-2026-0137',
                'title' => 'Permintaan lisensi tambahan MS Project',
                'requester' => 'Maria Christin',
                'category' => 'Aplikasi',
                'priority' => 'Low',
                'status' => 'Waiting Approval',
                'assignee' => 'Dewi Anjani',
                'sla_due' => '2026-07-23 10:00',
                'created_at' => '2026-07-19 11:30',
            ],
        ];
    }

    public static function notifications(): array
    {
        return [
            ['id' => 1, 'title' => 'Tiket TCK-2026-0142 dieskalasi', 'time' => '5 menit lalu', 'unread' => true],
            ['id' => 2, 'title' => 'Approval TCK-2026-0137 menunggu persetujuan Anda', 'time' => '32 menit lalu', 'unread' => true],
            ['id' => 3, 'title' => 'SLA TCK-2026-0140 mendekati batas waktu', 'time' => '1 jam lalu', 'unread' => false],
            ['id' => 4, 'title' => 'Tiket TCK-2026-0138 ditutup oleh Rizky Pratama', 'time' => 'Kemarin', 'unread' => false],
        ];
    }

    public static function slaPerformance(): array
    {
        return [
            ['week' => 'W1', 'on_time' => 88, 'breach' => 12],
            ['week' => 'W2', 'on_time' => 91, 'breach' => 9],
            ['week' => 'W3', 'on_time' => 84, 'breach' => 16],
            ['week' => 'W4', 'on_time' => 95, 'breach' => 5],
        ];
    }

    public static function ticketVolumeByCategory(): array
    {
        return [
            ['category' => 'Jaringan', 'total' => 18],
            ['category' => 'Aplikasi', 'total' => 27],
            ['category' => 'Hardware', 'total' => 14],
            ['category' => 'Akses & Akun', 'total' => 9],
            ['category' => 'Lainnya', 'total' => 5],
        ];
    }

    public static function approvalQueue(): array
    {
        return [
            [
                'id' => 'APR-2026-0056',
                'ticket_id' => 'TCK-2026-0137',
                'title' => 'Permintaan lisensi tambahan MS Project',
                'requester' => 'Maria Christin',
                'submitted_at' => '2026-07-19 11:30',
                'level' => 'Level 1 - Manager IT',
                'status' => 'Pending',
            ],
            [
                'id' => 'APR-2026-0055',
                'ticket_id' => 'TCK-2026-0140',
                'title' => 'Penggantian laptop rusak',
                'requester' => 'Andi Wijaya',
                'submitted_at' => '2026-07-19 16:05',
                'level' => 'Level 2 - Kepala Divisi',
                'status' => 'Pending',
            ],
            [
                'id' => 'APR-2026-0054',
                'ticket_id' => 'TCK-2026-0130',
                'title' => 'Akses VPN untuk vendor eksternal',
                'requester' => 'Denny Firmansyah',
                'submitted_at' => '2026-07-17 09:00',
                'level' => 'Level 1 - Manager IT',
                'status' => 'Approved',
            ],
        ];
    }

    public static function knowledgeArticles(): array
    {
        return [
            ['id' => 1, 'title' => 'Cara reset password akun email korporat', 'views' => 482, 'status' => 'Published'],
            ['id' => 2, 'title' => 'Troubleshooting VPN gagal connect', 'views' => 331, 'status' => 'Published'],
            ['id' => 3, 'title' => 'Prosedur pengajuan perangkat baru', 'views' => 210, 'status' => 'Draft'],
            ['id' => 4, 'title' => 'FAQ approval matrix per divisi', 'views' => 156, 'status' => 'Published'],
        ];
    }

    public static function unansweredQuestions(): array
    {
        return [
            ['question' => 'Bagaimana cara mengajukan cuti lewat sistem HR?', 'asked' => 14],
            ['question' => 'Kenapa aplikasi absensi sering logout sendiri?', 'asked' => 9],
            ['question' => 'Siapa PIC untuk masalah jaringan di site Medan?', 'asked' => 6],
        ];
    }

    public static function auditTrail(): array
    {
        return [
            ['waktu' => '08 Jul 2026, 10:15', 'pengguna' => 'Marcell Laforteza', 'aktivitas' => 'Memperbarui SLA Incident High', 'modul' => 'Konfigurasi SLA'],
            ['waktu' => '07 Jul 2026, 09:42', 'pengguna' => 'Marcell Laforteza', 'aktivitas' => 'Menambahkan kategori tiket baru', 'modul' => 'Service Catalog'],
            ['waktu' => '08 Jul 2026, 16:40', 'pengguna' => 'Karina', 'aktivitas' => 'Menyetujui permintaan Laptop Baru', 'modul' => 'Approval Matrix'],
            ['waktu' => '07 Jul 2026, 08:55', 'pengguna' => 'Marcell Laforteza', 'aktivitas' => 'Memperbarui approval matrix', 'modul' => 'Approval Matrix'],
        ];
    }

    public static function ticketCategoryDistribution(): array
    {
        return [
            ['label' => 'Incident', 'value' => 42, 'color' => '#2563eb'],
            ['label' => 'Service Request', 'value' => 35, 'color' => '#f59e0b'],
            ['label' => 'Access Request', 'value' => 23, 'color' => '#10b981'],
        ];
    }

    public static function slaStatus(): array
    {
        return [
            ['label' => 'Within SLA', 'percent' => 86, 'color' => '#10b981'],
            ['label' => 'Warning', 'percent' => 9, 'color' => '#f59e0b'],
            ['label' => 'Breach', 'percent' => 5, 'color' => '#ef4444'],
        ];
    }

    public static function slaTrendByPriority(): array
    {
        return [
            ['month' => 'Feb', 'Critical' => 4, 'High' => 8, 'Medium' => 12, 'Low' => 4],
            ['month' => 'Mar', 'Critical' => 5, 'High' => 10, 'Medium' => 14, 'Low' => 5],
            ['month' => 'Apr', 'Critical' => 3, 'High' => 9, 'Medium' => 11, 'Low' => 4],
            ['month' => 'Mei', 'Critical' => 7, 'High' => 13, 'Medium' => 16, 'Low' => 8],
            ['month' => 'Jun', 'Critical' => 6, 'High' => 15, 'Medium' => 19, 'Low' => 9],
            ['month' => 'Jul', 'Critical' => 8, 'High' => 17, 'Medium' => 21, 'Low' => 9],
        ];
    }

    public static function avgResolutionByCategory(): array
    {
        return [
            ['label' => 'Incident Infrastruktur', 'hours' => 6.8],
            ['label' => 'Incident Aplikasi', 'hours' => 5.2],
            ['label' => 'Service Request', 'hours' => 14.6],
            ['label' => 'Access Request', 'hours' => 8.1],
            ['label' => 'Permintaan Perangkat', 'hours' => 22.4],
        ];
    }

    public static function ticketTrendByCategory(): array
    {
        return [
            ['month' => 'Feb', 'Incident' => 92, 'Service Request' => 70, 'Access Request' => 42],
            ['month' => 'Mar', 'Incident' => 98, 'Service Request' => 80, 'Access Request' => 48],
            ['month' => 'Apr', 'Incident' => 88, 'Service Request' => 86, 'Access Request' => 56],
            ['month' => 'Mei', 'Incident' => 118, 'Service Request' => 98, 'Access Request' => 60],
            ['month' => 'Jun', 'Incident' => 128, 'Service Request' => 110, 'Access Request' => 68],
            ['month' => 'Jul', 'Incident' => 138, 'Service Request' => 122, 'Access Request' => 80],
        ];
    }

    public static function topServiceCatalog(): array
    {
        return [
            ['label' => 'Reset Password', 'total' => 186],
            ['label' => 'SAP Login Error', 'total' => 142],
            ['label' => 'Permintaan Laptop', 'total' => 118],
            ['label' => 'Akses VPN', 'total' => 96],
            ['label' => 'Laptop Baru', 'total' => 74],
        ];
    }

    public static function roles(): array
    {
        return [
            ['key' => 'requester', 'name' => 'Requester', 'type' => 'System Role', 'status' => 'Aktif', 'description' => 'Membuat & memantau tiket sendiri, mengakses Knowledge Base, memberi rating/feedback.'],
            ['key' => 'approver', 'name' => 'Approver', 'type' => 'System Role', 'status' => 'Aktif', 'description' => 'Dikelola melalui Service Catalog', 'locked' => true],
            ['key' => 'support-it', 'name' => 'Support IT', 'type' => 'System Role', 'status' => 'Aktif', 'description' => 'Menangani tiket layanan/aplikasi yang berada di bawah kepemilikan Tim IT.'],
            ['key' => 'support-bpo', 'name' => 'Support BPO', 'type' => 'System Role', 'status' => 'Aktif', 'description' => 'Menangani tiket layanan/aplikasi yang berada di bawah kepemilikan Business Process Owner (unit bisnis).'],
            ['key' => 'team-lead', 'name' => 'Team Lead', 'type' => 'System Role', 'status' => 'Aktif', 'description' => 'Mengawasi layanan dan memantau seluruh tiket pada layanan yang menjadi tanggung jawabnya.'],
            ['key' => 'administrator', 'name' => 'Administrator', 'type' => 'System Role', 'status' => 'Aktif', 'description' => 'Akses konfigurasi penuh: user, role, SLA, service catalog, kategori, approval matrix, audit, integrasi.'],
            ['key' => 'knowledge-administrator', 'name' => 'Knowledge Administrator', 'type' => 'System Role', 'status' => 'Aktif', 'description' => 'Mengelola artikel, FAQ, approval knowledge, AI knowledge training, dan analytics.'],
        ];
    }

    public static function permissionModules(): array
    {
        return [
            'Portal & Ticket', 'Service Catalog', 'Approval', 'Knowledge Base', 'SLA', 'Audit Trail',
            'Dashboard', 'Master Data', 'User Management', 'Role Management', 'System Configuration',
            'Notification', 'Integration', 'API', 'Analytics',
        ];
    }

    public static function permissionActions(): array
    {
        return ['Read', 'Create', 'Update', 'Delete', 'Assign', 'Approve', 'Export', 'Import', 'Configure'];
    }

    public static function users(): array
    {
        return [
            ['id' => 1, 'name' => 'Marcell Laforteza', 'nip' => '19870114001', 'email' => 'marcell.laforteza@adhi.co.id', 'whatsapp' => '+6281234567890', 'unit' => 'Dept. Strategi Korporasi', 'jabatan' => 'Administrator Sistem', 'kode_proyek' => '-', 'nama_proyek' => '-', 'roles' => ['Administrator', 'Requester'], 'status' => 'Aktif', 'last_login' => '08 Jul 2026, 10:15', 'joined_at' => '12 Januari 2023'],
            ['id' => 2, 'name' => 'Karina Putri', 'nip' => '19900322014', 'email' => 'karina.putri@adhi.co.id', 'whatsapp' => '+6281298765432', 'unit' => 'Dept. Pengendali Operasi', 'jabatan' => 'Manager Dept. Pengendali Operasi', 'kode_proyek' => '-', 'nama_proyek' => '-', 'roles' => ['Approver', 'Team Lead', 'Requester'], 'status' => 'Aktif', 'last_login' => '08 Jul 2026, 09:40', 'joined_at' => '03 Maret 2022'],
            ['id' => 3, 'name' => 'Rizky Hidayat', 'nip' => '19880609027', 'email' => 'rizky.hidayat@adhi.co.id', 'whatsapp' => '+6281322233344', 'unit' => 'Dept. Supply Chain Management', 'jabatan' => 'Team Lead', 'kode_proyek' => '-', 'nama_proyek' => '-', 'roles' => ['Team Lead', 'Requester'], 'status' => 'Aktif', 'last_login' => '08 Jul 2026, 08:10', 'joined_at' => '19 Juni 2021'],
            ['id' => 4, 'name' => 'Dimas Kurniawan', 'nip' => '10021190', 'email' => 'dimas.kurniawan@adhi.co.id', 'whatsapp' => '+6281811166677', 'unit' => 'Dept. Keuangan', 'jabatan' => 'Team Lead', 'kode_proyek' => '-', 'nama_proyek' => '-', 'roles' => ['Team Lead', 'Requester'], 'status' => 'Aktif', 'last_login' => '08 Jul 2026, 07:55', 'joined_at' => '02 Februari 2020'],
            ['id' => 5, 'name' => 'Raka Mahendra', 'nip' => '19891117033', 'email' => 'raka.mahendra@adhi.co.id', 'whatsapp' => '+6281911177788', 'unit' => 'Dept. Infrastruktur I', 'jabatan' => 'Team Lead', 'kode_proyek' => '-', 'nama_proyek' => '-', 'roles' => ['Team Lead', 'Requester'], 'status' => 'Aktif', 'last_login' => '07 Jul 2026, 17:30', 'joined_at' => '25 Mei 2021'],
            ['id' => 6, 'name' => 'Fajar Nugraha', 'nip' => '19850228041', 'email' => 'fajar.nugraha@adhi.co.id', 'whatsapp' => '+6281433344455', 'unit' => 'Satuan Pengawas Internal', 'jabatan' => 'Team Lead', 'kode_proyek' => '-', 'nama_proyek' => '-', 'roles' => ['Team Lead', 'Requester'], 'status' => 'Aktif', 'last_login' => '08 Jul 2026, 07:40', 'joined_at' => '14 Agustus 2019'],
            ['id' => 7, 'name' => 'Nina Amelia', 'nip' => '19920504052', 'email' => 'nina.amelia@adhi.co.id', 'whatsapp' => '+6281544455566', 'unit' => 'Adhi Learning Center', 'jabatan' => 'Knowledge Administrator', 'kode_proyek' => '-', 'nama_proyek' => '-', 'roles' => ['Team Lead', 'Knowledge Administrator', 'Requester'], 'status' => 'Aktif', 'last_login' => '07 Jul 2026, 15:12', 'joined_at' => '09 September 2022'],
            ['id' => 8, 'name' => 'Aditya Dwi Nugraha', 'nip' => '10027761', 'email' => 'aditya.nugraha@adhi.co.id', 'whatsapp' => '+6281611144455', 'unit' => 'Dept. Strava', 'jabatan' => 'IT Support Staff', 'kode_proyek' => '-', 'nama_proyek' => '-', 'roles' => ['Support IT', 'Requester'], 'status' => 'Aktif', 'last_login' => '08 Jul 2026, 08:50', 'joined_at' => '30 Oktober 2023'],
            ['id' => 9, 'name' => 'Siti Nurhaliza', 'nip' => '19930712063', 'email' => 'siti.nurhaliza@adhi.co.id', 'whatsapp' => '+6281755566677', 'unit' => 'Dept. SDM', 'jabatan' => 'HR Staff', 'kode_proyek' => '-', 'nama_proyek' => '-', 'roles' => ['Requester'], 'status' => 'Nonaktif', 'last_login' => '20 Jun 2026, 11:05', 'joined_at' => '11 Januari 2024'],
            ['id' => 10, 'name' => 'Budi Santoso', 'nip' => '19940815074', 'email' => 'budi.santoso@adhi.co.id', 'whatsapp' => '+6281866677788', 'unit' => 'Dept. Proyek Balikpapan', 'jabatan' => 'Site Engineer', 'kode_proyek' => 'PRJ-BPP-01', 'nama_proyek' => 'Pembangunan Jalan Tol Balikpapan', 'roles' => ['Requester'], 'status' => 'Aktif', 'last_login' => '06 Jul 2026, 13:22', 'joined_at' => '05 Mei 2024'],
            ['id' => 11, 'name' => 'Maria Christin', 'nip' => '19910925085', 'email' => 'maria.christin@adhi.co.id', 'whatsapp' => '+6281977788899', 'unit' => 'Dept. Keuangan', 'jabatan' => 'Finance Staff', 'kode_proyek' => '-', 'nama_proyek' => '-', 'roles' => ['Requester'], 'status' => 'Nonaktif', 'last_login' => '15 Jun 2026, 09:18', 'joined_at' => '21 Juli 2022'],
            ['id' => 12, 'name' => 'Denny Firmansyah', 'nip' => '19960130096', 'email' => 'denny.firmansyah@adhi.co.id', 'whatsapp' => '+6282188899900', 'unit' => 'Dept. Supply Chain Management', 'jabatan' => 'Procurement Staff', 'kode_proyek' => '-', 'nama_proyek' => '-', 'roles' => ['Support BPO', 'Requester'], 'status' => 'Aktif', 'last_login' => '08 Jul 2026, 06:45', 'joined_at' => '17 Maret 2023'],
        ];
    }

    public static function unitOrganisasi(): array
    {
        return [
            'Dept. Strategi Korporasi', 'Dept. Pengendali Operasi', 'Dept. Supply Chain Management',
            'Dept. Keuangan', 'Dept. Infrastruktur I', 'Satuan Pengawas Internal', 'Adhi Learning Center',
            'Dept. SDM',
        ];
    }
}
