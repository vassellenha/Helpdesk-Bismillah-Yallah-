<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Makes the escalation-time bonus a per-policy, admin-editable value instead
 * of the single app-wide SLA_ESCALATION_EXTENSION_PERCENT env var — a
 * Critical policy and a Low policy have no reason to grant the same bonus
 * when a ticket under either one gets escalated.
 *
 * Backfilled to 50% of each policy's own resolution_time_minutes, matching
 * the default the env var used, so existing policies keep behaving exactly
 * as before until an admin edits the new field.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sla_policies', function (Blueprint $table) {
            $table->unsignedInteger('escalation_extension_minutes')->default(0)->after('resolution_time_minutes');
        });

        DB::statement('UPDATE sla_policies SET escalation_extension_minutes = ROUND(resolution_time_minutes * 0.5)');
    }

    public function down(): void
    {
        Schema::table('sla_policies', function (Blueprint $table) {
            $table->dropColumn('escalation_extension_minutes');
        });
    }
};
