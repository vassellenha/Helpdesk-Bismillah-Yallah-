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
        Schema::table('service_catalog_subjects', function (Blueprint $table) {
            // `support_agent_id` remains the BPO-side agent (or the sole
            // agent for a single-team Subject); this holds the IT-side
            // agent, populated whenever Level 2 ("Support BPO & IT")
            // requires both teams handling the Subject together.
            $table->foreignId('it_agent_id')->nullable()->after('support_agent_id')
                ->constrained('support_agents')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('service_catalog_subjects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('it_agent_id');
        });
    }
};
