<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "delete" — Admin menghapus SLA Policy dari Konsol Konfigurasi SLA.
 *
 * Sampai sekarang tidak ada satu pun aksi Admin yang benar-benar membuang
 * baris, jadi enumnya tidak pernah perlu nilai ini. Penghapusan justru yang
 * paling wajib bisa ditelusuri: setelah barisnya hilang, catatan audit adalah
 * satu-satunya yang tersisa untuk menjawab siapa yang menghapus dan apa isinya.
 *
 * Blueprint::change(), bukan `ALTER ... MODIFY` khusus MySQL — lihat
 * 2026_08_06_110000_add_start_to_audit_trails_action_enum.php untuk alasannya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_trails', function (Blueprint $table) {
            $table->enum('action', [
                'create', 'update', 'activate', 'deactivate', 'assign_support', 'change_level', 'change_role',
                'approve', 'request_revision', 'reject', 'resolve', 'escalate', 'remind', 'reassign',
                'raise_priority', 'remind_rating', 'return', 'sync', 'login', 'start', 'claim', 'logout',
                'auto_close', 'delete',
            ])->change();
        });
    }

    public function down(): void
    {
        Schema::table('audit_trails', function (Blueprint $table) {
            $table->enum('action', [
                'create', 'update', 'activate', 'deactivate', 'assign_support', 'change_level', 'change_role',
                'approve', 'request_revision', 'reject', 'resolve', 'escalate', 'remind', 'reassign',
                'raise_priority', 'remind_rating', 'return', 'sync', 'login', 'start', 'claim', 'logout',
                'auto_close',
            ])->change();
        });
    }
};
