<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            // escalate() overwrites assigned_agent_id with the IT agent's row,
            // so the BPO agent who handed it off would otherwise have no way
            // left to find the ticket again — this keeps that record around
            // so it can stay in their queue as a read/comment-only item
            // instead of vanishing the moment IT takes over.
            $table->foreignId('escalated_by_agent_id')->nullable()->after('escalation_note')
                ->constrained('support_agents')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('escalated_by_agent_id');
        });
    }
};
