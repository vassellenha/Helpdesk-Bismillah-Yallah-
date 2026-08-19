<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "logout" action pada module "auth" yang sudah ada — pasangan dari "login"
 * yang ditambahkan 2026_08_06_090100. Tanpa nilai ini konsol Admin hanya bisa
 * menjawab kapan seseorang masuk, tidak kapan ia keluar.
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
            ])->change();
        });
    }

    public function down(): void
    {
        Schema::table('audit_trails', function (Blueprint $table) {
            $table->enum('action', [
                'create', 'update', 'activate', 'deactivate', 'assign_support', 'change_level', 'change_role',
                'approve', 'request_revision', 'reject', 'resolve', 'escalate', 'remind', 'reassign',
                'raise_priority', 'remind_rating', 'return', 'sync', 'login', 'start', 'claim',
            ])->change();
        });
    }
};
