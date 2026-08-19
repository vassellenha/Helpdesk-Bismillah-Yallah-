<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Notifikasi milik satu PERAN, bukan milik satu orang.
 *
 * Sebelumnya baris notifikasi hanya menyimpan user_id, dan lonceng di setiap
 * layar menampilkan seluruh baris milik orang itu. Untuk pemegang dua peran —
 * Marcell (Administrator + Requester), Karina (Approver + Requester) — artinya
 * notifikasi approval ikut muncul saat ia sedang di layar Requester.
 *
 * Baris lama diisi ulang dari HUBUNGANNYA dengan tiket, bukan ditebak dari
 * jenis notifikasi: penerima yang merupakan requester tiket itu jelas menerima
 * sebagai Requester, dan seterusnya. Tebakan berdasarkan jenis akan meleset
 * justru pada jenis yang dipakai lebih dari satu peran (discussion_message,
 * sla_warning) — yaitu jenis yang paling sering muncul.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_notifications', function (Blueprint $table) {
            // Nullable: baris lama diisi di bawah, dan baris yang tidak bisa
            // ditentukan perannya lebih baik dibiarkan kosong daripada
            // diberi peran karangan yang memunculkannya di lonceng yang salah.
            $table->string('role')->nullable()->after('user_id')->index();
        });

        $this->backfill();
    }

    /**
     * Isi ulang berdasarkan peran penerima PADA TIKET tersebut.
     *
     * Dikerjakan dua langkah — SELECT id dulu, baru UPDATE dengan daftar id
     * biasa — bukan satu UPDATE ber-subquery ke tabel yang sedang ditulis.
     * Pola subquery itu ditolak MySQL dengan error 1093 ("You can't specify
     * target table for update in FROM clause"); MariaDB kebetulan
     * memaafkannya, jadi migrasi yang lolos di mesin pengembang bisa gagal di
     * server. Bentuk dua langkah ini berlaku di MySQL, MariaDB, PostgreSQL,
     * maupun SQLite.
     *
     * Diproses per 500 id supaya klausa IN tidak membengkak pada instalasi
     * yang notifikasinya sudah banyak.
     */
    private function backfill(): void
    {
        // 1. Penerima adalah requester tiketnya.
        $this->assign('requester', fn ($q) => $q
            ->join('tickets as t', 't.id', '=', 'n.ticket_id')
            ->whereColumn('t.requester_id', 'n.user_id'));

        // 2. Penerima adalah approver tiketnya.
        $this->assign('approver', fn ($q) => $q
            ->join('tickets as t', 't.id', '=', 'n.ticket_id')
            ->whereColumn('t.approver_id', 'n.user_id'));

        // 3. Penerima adalah PIC tiketnya — Support IT atau Support BPO,
        //    dibedakan oleh kolom `type` di support_agents.
        foreach (['it' => 'support', 'bpo' => 'support-bpo'] as $agentType => $roleKey) {
            $this->assign($roleKey, fn ($q) => $q
                ->join('tickets as t', 't.id', '=', 'n.ticket_id')
                ->join('support_agents as a', 'a.id', '=', 't.assigned_agent_id')
                ->whereColumn('a.user_id', 'n.user_id')
                ->where('a.type', $agentType));

        }

        // 4. Sisanya: notifikasi tanpa tiket (mis. teguran rating dari Team
        //    Lead) yang perannya tidak bisa disimpulkan dari data. Dibiarkan
        //    NULL — lihat catatan di atas.
    }

    /**
     * Tandai baris yang cocok dengan $filter sebagai milik $role.
     *
     * @param  \Closure(\Illuminate\Database\Query\Builder):\Illuminate\Database\Query\Builder  $filter
     */
    private function assign(string $role, \Closure $filter): void
    {
        $ids = $filter(
            DB::table('ticket_notifications as n')->whereNull('n.role')
        )->pluck('n.id');

        foreach ($ids->chunk(500) as $chunk) {
            DB::table('ticket_notifications')
                ->whereIn('id', $chunk->all())
                ->update(['role' => $role]);
        }
    }

    public function down(): void
    {
        Schema::table('ticket_notifications', function (Blueprint $table) {
            $table->dropIndex(['role']);
            $table->dropColumn('role');
        });
    }
};
