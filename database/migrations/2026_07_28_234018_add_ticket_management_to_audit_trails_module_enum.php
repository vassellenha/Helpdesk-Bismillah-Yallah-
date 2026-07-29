<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Admin's Ticket Management console gains its first mutating action — the
 * per-ticket "exclude rating from stats" toggle — so it needs its own
 * module on the shared Audit Trail, mirroring the ticket_approval /
 * ticket_support / team_lead additions before it. The action itself reuses
 * the existing activate/deactivate values, no action enum change needed.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE audit_trails MODIFY module ENUM('service_catalog', 'sla_configuration', 'user_role_management', 'ticket_approval', 'ticket_support', 'team_lead', 'ticket_management') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE audit_trails MODIFY module ENUM('service_catalog', 'sla_configuration', 'user_role_management', 'ticket_approval', 'ticket_support', 'team_lead') NOT NULL");
    }
};
