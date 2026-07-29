<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * "remind_rating" — a Team Lead's rating teguran (reprimand about an agent's
 * overall satisfaction rating, not tied to a single ticket's SLA) — is a new
 * action alongside the existing "remind" (SLA teguran) in the team_lead module.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE audit_trails MODIFY action ENUM('create', 'update', 'activate', 'deactivate', 'assign_support', 'change_level', 'change_role', 'approve', 'request_revision', 'reject', 'resolve', 'escalate', 'remind', 'reassign', 'raise_priority', 'remind_rating') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE audit_trails MODIFY action ENUM('create', 'update', 'activate', 'deactivate', 'assign_support', 'change_level', 'change_role', 'approve', 'request_revision', 'reject', 'resolve', 'escalate', 'remind', 'reassign', 'raise_priority') NOT NULL");
    }
};
