<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Aksi baru: `assign_team_lead` — Admin membagi cakupan pengawasan dengan
 * menugaskan seorang Team Lead BPO ke satu Subkategori katalog.
 *
 * SENGAJA BUKAN memakai ulang `assign_support`, meski migrasi 2026_09_02
 * menjadikan pemakaian ulang sebagai gaya rumah untuk menghemat ALTER TABLE.
 * `assign_support` sudah berlabel "Ubah Support" di tiga tempat
 * (AuditTrailController::actionLabel, AuditTrailConsole.jsx, dan berkas
 * bahasa admin) dan maknanya pergantian PIC yang menangani tiket. Menugaskan Team
 * Lead adalah perubahan PETA PENGAWASAN — siapa berwenang memindahkan PIC,
 * menegur, dan membaca tiket siapa. Pertanyaan yang akan diajukan orang
 * ("siapa yang mengubah cakupan Team Lead, kapan") harus bisa dijawab dengan
 * satu filter; kalau ia bersembunyi di balik "Ubah Support", satu-satunya
 * cara menemukannya adalah membaca deskripsi satu per satu. Itu justru
 * pertanggungjawaban yang jadi alasan fiturnya ada.
 *
 * `target_type` tidak perlu migrasi — kolom itu string bebas, bukan enum.
 */
return new class extends Migration
{
    private const MODULE = ['service_catalog', 'sla_configuration', 'user_role_management', 'ticket_approval', 'ticket_support', 'team_lead', 'ticket_management', 'integration', 'auth', 'ticket_requester', 'knowledge'];

    private const ACTION_LAMA = ['create', 'update', 'activate', 'deactivate', 'assign_support', 'change_level', 'change_role', 'approve', 'request_revision', 'reject', 'resolve', 'escalate', 'remind', 'reassign', 'raise_priority', 'remind_rating', 'return', 'sync', 'login', 'start', 'claim', 'logout', 'auto_close', 'delete', 'login_failed', 'comment', 'close', 'reopen', 'publish', 'reindex', 'restore'];

    private const ACTION_TAMBAHAN = ['assign_team_lead'];

    public function up(): void
    {
        $this->ubah([...self::ACTION_LAMA, ...self::ACTION_TAMBAHAN]);
    }

    public function down(): void
    {
        // Baris bernilai baru dibuang lebih dulu — tanpa ini penyempitan
        // enum-nya ditolak dan rollback gagal di tengah.
        DB::table('audit_trails')->whereIn('action', self::ACTION_TAMBAHAN)->delete();

        $this->ubah(self::ACTION_LAMA);
    }

    /**
     * Dua jalur, sama seperti migrasi 2026_09_02: MySQL/MariaDB menyimpannya
     * sebagai ENUM yang hanya bisa diubah lewat ALTER TABLE ... MODIFY,
     * sedangkan SQLite (yang dipakai tes) menyimpannya sebagai VARCHAR
     * ber-CHECK yang harus dibangun ulang lewat ->change().
     *
     * @param  list<string>  $action
     */
    private function ubah(array $action): void
    {
        if (in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            $daftar = fn (array $nilai) => "'".implode("','", $nilai)."'";

            DB::statement('ALTER TABLE audit_trails MODIFY action ENUM('.$daftar($action).') NOT NULL');

            return;
        }

        Schema::table('audit_trails', function (Blueprint $table) use ($action) {
            $table->enum('action', $action)->change();
        });
    }
};
