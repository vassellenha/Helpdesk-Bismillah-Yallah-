<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "start" action on the existing "ticket_support" module — Support/BPO's
 * "Kerjakan Sekarang" choice on an Open ticket (SupportController::start(),
 * SupportBpoController::start()).
 *
 * Uses Blueprint::change() rather than a raw MySQL-only `ALTER ... MODIFY`
 * (see 2026_08_06_090100_add_auth_login_to_audit_trails_enums.php for why:
 * SQLite's enum column carries a real CHECK constraint that a MySQL-only
 * guard leaves stale in the test database).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_trails', function (Blueprint $table) {
            $table->enum('action', [
                'create', 'update', 'activate', 'deactivate', 'assign_support', 'change_level', 'change_role',
                'approve', 'request_revision', 'reject', 'resolve', 'escalate', 'remind', 'reassign',
                'raise_priority', 'remind_rating', 'return', 'sync', 'login', 'start',
            ])->change();
        });
    }

    public function down(): void
    {
        Schema::table('audit_trails', function (Blueprint $table) {
            $table->enum('action', [
                'create', 'update', 'activate', 'deactivate', 'assign_support', 'change_level', 'change_role',
                'approve', 'request_revision', 'reject', 'resolve', 'escalate', 'remind', 'reassign',
                'raise_priority', 'remind_rating', 'return', 'sync', 'login',
            ])->change();
        });
    }
};
