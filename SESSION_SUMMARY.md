# Ringkasan Sesi — Helpdesk 2.0

## 1. Topik yang Dibahas
- Setup awal: sinkronisasi DB, migration/seeder, fix `php artisan serve` (path PHP salah di `.claude/launch.json`).
- **Admin Ticket Management**: dibangun dari nol (sebelumnya cuma placeholder) — tabel, filter, statistik, detail modal, export CSV, read-only untuk Admin.
- **Sinkronisasi Requester ↔ Admin**: tiket baru dari Requester langsung muncul di Admin; hapus tiket orphan/dummy; scope hanya tiket requester asli.
- **SLA**: format tampilan disamakan Admin↔Requester, hapus field "Jenis Layanan" (tidak dipakai), priority nonaktif tidak bisa dipilih di New Ticket.
- **Service Catalog**: filter Layanan/Sub Category yang tidak ada Subject aktif disembunyikan dari Requester; validasi backend Subject aktif; propagasi rename Layanan/Sub Category/Subject ke tiket existing.
- **Edit Draft ticket** oleh Requester (endpoint baru + prefill form).
- **Attachment**: fix `storage:link` & `APP_URL` (port salah) supaya file bisa diakses.
- **QA audit menyeluruh** (Admin↔Requester): hapus card "Approval Matrix" (sudah di-deprecate), fix Service Catalog count di dashboard, fix subcategory filter.
- **Model Support Level Service Catalog** (perubahan terbesar terakhir): Level 2 = **2 orang sekaligus** (BPO + IT), bukan pilih salah satu. Level diturunkan dari data checklist BPO/IT di file Excel `REV Insiden & Service List Issue for Helpdesk 2.0.xlsx`.

## 2. Keputusan/Kesimpulan Final
- **Ticket Management Admin**: read-only total (tidak ada assign/reassign/ubah status), Team Lead & Support cuma placeholder UI (disabled).
- **PIC tiket dihitung LIVE** dari relasi `catalog_subject_id → ServiceCatalogSubject → supportAgent/itAgent`, bukan snapshot beku — otomatis ikut berubah kalau Admin ganti Support di Service Catalog (berlaku juga saat Layanan/Subcategory/Subject di-rename).
- **Level Support** sekarang 3 opsi nyata: `Level 1 — Support BPO`, `Level 1 — Support IT`, `Level 2 — Support BPO & IT` (2 dropdown terpisah saat Level 2, masing-masing 1 orang).
- Kolom baru `it_agent_id` di `service_catalog_subjects` (`support_agent_id` = sisi BPO).
- Data 123/124 Subject Incident+Service Request sudah dikoreksi levelnya sesuai Excel asli.
- **Belum dikerjakan / perlu keputusan user**: sheet "USER ACCESS" di Excel punya kolom ke-3 **"HC"** (3 tim, bukan 2) — Access Request (VPN, AKUN APLIKASI, PERUBAHAN AKSES APLIKASI) belum dikoreksi, menunggu keputusan apakah mau model 3-tim.
- User & Role Management dan Approval Matrix: **tidak boleh disentuh** (TBD/sudah dihapus permanen).

## 3. File Penting yang Terakhir Diubah
- Migration: tambah `it_agent_id` ke `service_catalog_subjects`; tambah `catalog_subject_id` ke `tickets`.
- `app/Models/ServiceCatalogSubject.php`, `app/Models/Ticket.php`
- `app/Http/Controllers/ServiceCatalogController.php` (dual-agent CRUD + propagasi nama)
- `app/Http/Controllers/Admin/TicketManagementController.php` (PIC live, stats, export)
- `app/Http/Controllers/TicketDetailController.php` (People card dual-support)
- `app/Http/Controllers/TicketController.php`, `app/Http/Controllers/CatalogController.php`, `app/Http/Controllers/AdminController.php`
- `resources/js/components/admin/ServiceCatalogFormModal.jsx` (Level 3 opsi + 2 dropdown terpisah)
- `resources/js/components/admin/ServiceCatalogConsole.jsx`, `ServiceCatalogDetailModal.jsx`
- `resources/js/components/admin/TicketManagementConsole.jsx`, `resources/js/components/requester/TicketDetail.jsx`, `NewTicketModal.jsx`, `MyTicketsPage.jsx`
- `resources/views/admin/dashboard.blade.php`, `resources/views/layouts/requester.blade.php`, `.env` (APP_URL)
