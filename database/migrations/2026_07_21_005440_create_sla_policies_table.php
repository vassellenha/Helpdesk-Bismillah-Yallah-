<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sla_policies', function (Blueprint $table) {
            $table->id();
            $table->string('policy_name');
            $table->enum('priority', ['Critical', 'High', 'Medium', 'Low']);
            $table->enum('service_type', ['Incident', 'Service Request', 'Access Request'])->default('Incident');
            $table->unsignedInteger('response_time_minutes');
            $table->unsignedInteger('resolution_time_minutes');
            $table->unsignedTinyInteger('warning_threshold_percent');
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sla_policies');
    }
};
