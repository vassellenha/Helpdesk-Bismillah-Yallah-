<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Menambal lubang di Audit Trail: dua modul dan enam aksi baru.
 *
 * Sampai sekarang jejak audit hanya mencatat pekerjaan Admin, Approver,
 * Support, dan Team Lead. Segala yang dilakukan REQUESTER atas tiketnya
 * sendiri — membuat, mengubah, MENGHAPUS, menutup, membuka kembali, mencabut
 * lampiran — tidak meninggalkan satu baris pun. Begitu juga seluruh pengelolaan
 * Knowledge oleh EVA: artikel, dokumen, FAQ, dan sinonim.
 *
 * Yang paling berbahaya penghapusan tiket: sesudahnya tidak ada tiket untuk
 * diperiksa DAN tidak ada catatan bahwa ia pernah ada.
 *
 * Modul dipilih menurut RUANG KERJA pelakunya, bukan menurut objek yang
 * disentuh. Komentar pada satu tiket yang sama karena itu bisa tercatat sebagai
 * ticket_requester, ticket_support, ticket_approval, atau team_lead — dan itu
 * memang yang ingin dibaca saat menelusuri: siapa berbicara dari kursi mana.
 *
 * `activate`/`deactivate` sengaja dipakai ulang untuk sembunyi/tampilkan
 * artikel dan FAQ alih-alih menambah nilai baru: maknanya sudah sama persis,
 * dan tiap nilai enum tambahan adalah satu ALTER TABLE lagi di produksi.
 */
return new class extends Migration
{
    private const MODULE_LAMA = ['service_catalog', 'sla_configuration', 'user_role_management', 'ticket_approval', 'ticket_support', 'team_lead', 'ticket_management', 'integration', 'auth'];

    private const MODULE_TAMBAHAN = ['ticket_requester', 'knowledge'];

    private const ACTION_LAMA = ['create', 'update', 'activate', 'deactivate', 'assign_support', 'change_level', 'change_role', 'approve', 'request_revision', 'reject', 'resolve', 'escalate', 'remind', 'reassign', 'raise_priority', 'remind_rating', 'return', 'sync', 'login', 'start', 'claim', 'logout', 'auto_close', 'delete', 'login_failed'];

    private const ACTION_TAMBAHAN = ['comment', 'close', 'reopen', 'publish', 'reindex', 'restore'];

    public function up(): void
    {
        $this->ubah(
            [...self::MODULE_LAMA, ...self::MODULE_TAMBAHAN],
            [...self::ACTION_LAMA, ...self::ACTION_TAMBAHAN],
        );
    }

    public function down(): void
    {
        // Baris yang memakai nilai baru dibuang lebih dulu. Tanpa ini
        // penyempitan enum-nya ditolak, dan rollback gagal di tengah —
        // meninggalkan basis data pada keadaan yang tidak dituju siapa pun.
        DB::table('audit_trails')->whereIn('module', self::MODULE_TAMBAHAN)->delete();
        DB::table('audit_trails')->whereIn('action', self::ACTION_TAMBAHAN)->delete();

        $this->ubah(self::MODULE_LAMA, self::ACTION_LAMA);
    }

    /**
     * @param  list<string>  $module
     * @param  list<string>  $action
     */
    private function ubah(array $module, array $action): void
    {
        /*
         | Dua jalur, karena batasannya memang dua macam.
         |
         | MySQL/MariaDB menyimpannya sebagai tipe ENUM dan hanya bisa diubah
         | lewat ALTER TABLE ... MODIFY. Driver lain — SQLite yang dipakai tes
         | repo ini — menyimpannya sebagai VARCHAR dengan CHECK constraint,
         | dan di sana `->change()` yang membangun ulang kolomnya.
         |
         | Migrasi enum sebelumnya di repo ini melewati SQLite begitu saja
         | dengan anggapan kolomnya tak berbatas di sana. Anggapan itu keliru:
         | CHECK-nya nyata, dan nilai baru ditolak dengan
         | "CHECK constraint failed" — gagal di tes, bukan di produksi, jadi
         | tidak pernah ketahuan sampai ada aksi yang benar-benar baru.
         */
        if (in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            $daftar = fn (array $nilai) => "'".implode("','", $nilai)."'";

            DB::statement('ALTER TABLE audit_trails MODIFY module ENUM('.$daftar($module).') NOT NULL');
            DB::statement('ALTER TABLE audit_trails MODIFY action ENUM('.$daftar($action).') NOT NULL');

            return;
        }

        Schema::table('audit_trails', function (Blueprint $table) use ($module, $action) {
            $table->enum('module', $module)->change();
            $table->enum('action', $action)->change();
        });
    }
};
