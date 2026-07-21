<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_no')->unique();
            $table->string('title');
            $table->string('requester_name');
            $table->string('category')->nullable();

            // SLA snapshot: captured at creation time from the sla_policies row
            // referenced below, so later admin edits never alter this ticket's SLA.
            $table->foreignId('sla_policy_id')->constrained('sla_policies');
            $table->string('priority');
            $table->unsignedInteger('response_time_minutes');
            $table->unsignedInteger('resolution_time_minutes');
            $table->unsignedTinyInteger('warning_threshold_percent');
            $table->dateTime('response_due_at');
            $table->dateTime('resolution_due_at');
            $table->dateTime('warning_at');

            $table->string('status')->default('Open');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
