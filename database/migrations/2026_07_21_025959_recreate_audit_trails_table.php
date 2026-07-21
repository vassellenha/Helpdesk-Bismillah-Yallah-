<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('audit_trails');

        Schema::create('audit_trails', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->constrained('users');
            $table->enum('module', ['service_catalog', 'sla_configuration', 'user_role_management']);
            $table->enum('action', ['create', 'update', 'activate', 'deactivate', 'assign_support', 'change_level', 'change_role']);
            $table->string('target_type');
            $table->unsignedBigInteger('target_id')->nullable();
            $table->string('target_name');
            $table->json('old_value')->nullable();
            $table->json('new_value')->nullable();
            $table->text('description');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_trails');
    }
};
