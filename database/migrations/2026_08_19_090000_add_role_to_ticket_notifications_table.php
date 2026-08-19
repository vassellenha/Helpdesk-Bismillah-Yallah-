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
     * Dikerjakan dengan UPDATE ... FROM sederhana per kasus, bukan memuat
     * seluruh baris ke PHP: tabel ini tumbuh seiring pemakaian dan migrasi
     * harus tetap selesai di basis data produksi.
     */
    private function backfill(): void
    {
        // 1. Penerima adalah requester tiketnya.
        DB::table('ticket_notifications')
            ->whereNull('role')
            ->whereIn('id', fn ($q) => $q->select('n.id')
                ->from('ticket_notifications as n')
                ->join('tickets as t', 't.id', '=', 'n.ticket_id')
                ->whereColumn('t.requester_id', 'n.user_id'))
            ->update(['role' => 'requester']);

        // 2. Penerima adalah approver tiketnya.
        DB::table('ticket_notifications')
            ->whereNull('role')
            ->whereIn('id', fn ($q) => $q->select('n.id')
                ->from('ticket_notifications as n')
                ->join('tickets as t', 't.id', '=', 'n.ticket_id')
                ->whereColumn('t.approver_id', 'n.user_id'))
            ->update(['role' => 'approver']);

        // 3. Penerima adalah PIC tiketnya — Support IT atau Support BPO,
        //    dibedakan oleh kolom `type` di support_agents.
        foreach (['it' => 'support', 'bpo' => 'support-bpo'] as $agentType => $roleKey) {
            DB::table('ticket_notifications')
                ->whereNull('role')
                ->whereIn('id', fn ($q) => $q->select('n.id')
                    ->from('ticket_notifications as n')
                    ->join('tickets as t', 't.id', '=', 'n.ticket_id')
                    ->join('support_agents as a', 'a.id', '=', 't.assigned_agent_id')
                    ->whereColumn('a.user_id', 'n.user_id')
                    ->where('a.type', $agentType))
                ->update(['role' => $roleKey]);
        }

        // 4. Sisanya: notifikasi tanpa tiket (mis. teguran rating dari Team
        //    Lead) yang perannya tidak bisa disimpulkan dari data. Dibiarkan
        //    NULL — lihat catatan di atas.
    }

    public function down(): void
    {
        Schema::table('ticket_notifications', function (Blueprint $table) {
            $table->dropIndex(['role']);
            $table->dropColumn('role');
        });
    }
};
