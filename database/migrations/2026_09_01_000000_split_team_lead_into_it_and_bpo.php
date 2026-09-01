<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Team Lead dipecah menjadi dua desk: Team Lead IT dan Team Lead BPO.
 *
 * Sampai sekarang hanya ada satu role "Team Lead", dan di kodenya role itu
 * sebenarnya SUDAH khusus IT (TeamLeadController mengunci cakupannya ke agent
 * bertipe 'it'). Jadi baris yang ada tidak dihapus lalu dibuat ulang — ia
 * DIGANTI NAMA menjadi "Team Lead IT", karena itu memang identitas yang selama
 * ini ia jalankan. Pivot role_user menunjuk role_id, bukan nama, sehingga
 * setiap pemegangnya tetap masuk tanpa kehilangan akses satu detik pun.
 *
 * Menghapus-lalu-membuat akan mengosongkan pivot itu (cascadeOnDelete) dan
 * seluruh Team Lead di produksi kehilangan aksesnya sampai Admin memberikannya
 * satu per satu — kerugian yang tidak dibayar oleh apa pun.
 *
 * Siapa yang seharusnya BPO tidak bisa ditebak dari data: tidak ada kolom yang
 * menghubungkan seorang Team Lead ke desk mana pun. Karena itu semua pemegang
 * lama menjadi Team Lead IT, dan pemindahannya dikerjakan Admin lewat layar
 * User & Role.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('roles')
            ->where('name', 'Team Lead')
            ->update(['name' => 'Team Lead IT', 'updated_at' => now()]);

        // Idempoten: migrasi yang diulang di server yang sudah terpasang tidak
        // boleh menabrak indeks unik pada roles.name.
        if (! DB::table('roles')->where('name', 'Team Lead BPO')->exists()) {
            DB::table('roles')->insert([
                'name' => 'Team Lead BPO',
                'type' => 'system',
                'status' => 'active',
                'locked' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // role_user memakai cascadeOnDelete, jadi baris pivotnya ikut terhapus
        // — tidak ada yatim yang tertinggal menunjuk role yang tidak ada.
        DB::table('roles')->where('name', 'Team Lead BPO')->delete();

        DB::table('roles')
            ->where('name', 'Team Lead IT')
            ->update(['name' => 'Team Lead', 'updated_at' => now()]);
    }
};
