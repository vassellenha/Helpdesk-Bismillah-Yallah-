<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "claim" action on the existing "ticket_support" module — the moment a BPO
 * PIC's first reply/action on a broadcast "Lainnya" ticket takes ownership
 * of it (App\Support\TicketBroadcast::claimIfUnclaimed()).
 *
 * Blueprint::change(), not a raw MySQL-only `ALTER ... MODIFY` — see
 * 2026_08_06_110000_add_start_to_audit_trails_action_enum.php for why.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_trails', function (Blueprint $table) {
            $table->enum('action', [
                'create', 'update', 'activate', 'deactivate', 'assign_support', 'change_level', 'change_role',
                'approve', 'request_revision', 'reject', 'resolve', 'escalate', 'remind', 'reassign',
                'raise_priority', 'remind_rating', 'return', 'sync', 'login', 'start', 'claim',
            ])->change();
        });
    }

    public function down(): void
    {
        Schema::table('audit_trails', function (Blueprint $table) {
            $table->enum('action', [
                'create', 'update', 'activate', 'deactivate', 'assign_support', 'change_level', 'change_role',
                'approve', 'request_revision', 'reject', 'resolve', 'escalate', 'remind', 'reassign',
                'raise_priority', 'remind_rating', 'return', 'sync', 'login', 'start',
            ])->change();
        });
    }
};
