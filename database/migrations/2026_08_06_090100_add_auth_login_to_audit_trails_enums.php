<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "auth" module + "login" action — one audit row every time SsoAuthenticator
 * actually establishes a session (SsoController::callback()/entry()), for any
 * role: requester, approver, support IT, support BPO, team lead, admin.
 *
 * Unlike the earlier "add X to audit_trails enums" migrations in this repo
 * (which run a raw `ALTER TABLE ... MODIFY` guarded to MySQL only, on the
 * belief that SQLite's enum column carries no CHECK constraint to update),
 * this one uses Blueprint::change() so it lands on every driver. SQLite DOES
 * bake the enum list into a CHECK constraint at table-creation time — those
 * earlier migrations are a no-op there, leaving the test database's
 * audit_trails.module/action constraints stuck at their original 2026-07-21
 * value lists. That gap only stayed invisible because no test previously
 * exercised AuditTrail::record() with one of the later-added values.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_trails', function (Blueprint $table) {
            $table->enum('module', [
                'service_catalog', 'sla_configuration', 'user_role_management', 'ticket_approval',
                'ticket_support', 'team_lead', 'ticket_management', 'integration', 'auth',
            ])->change();

            $table->enum('action', [
                'create', 'update', 'activate', 'deactivate', 'assign_support', 'change_level', 'change_role',
                'approve', 'request_revision', 'reject', 'resolve', 'escalate', 'remind', 'reassign',
                'raise_priority', 'remind_rating', 'return', 'sync', 'login',
            ])->change();
        });
    }

    public function down(): void
    {
        Schema::table('audit_trails', function (Blueprint $table) {
            $table->enum('module', [
                'service_catalog', 'sla_configuration', 'user_role_management', 'ticket_approval',
                'ticket_support', 'team_lead', 'ticket_management', 'integration',
            ])->change();

            $table->enum('action', [
                'create', 'update', 'activate', 'deactivate', 'assign_support', 'change_level', 'change_role',
                'approve', 'request_revision', 'reject', 'resolve', 'escalate', 'remind', 'reassign',
                'raise_priority', 'remind_rating', 'return', 'sync',
            ])->change();
        });
    }
};
