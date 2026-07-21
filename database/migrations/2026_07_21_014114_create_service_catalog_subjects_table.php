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
        Schema::create('service_catalog_subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('issue_category_id')->constrained('issue_categories');
            $table->foreignId('service_id')->constrained('service_catalog_services');
            $table->foreignId('subcategory_id')->constrained('service_catalog_subcategories');
            $table->string('name');
            $table->boolean('requires_approval')->default(false);
            $table->foreignId('support_agent_id')->nullable()->constrained('support_agents')->nullOnDelete();
            $table->unsignedTinyInteger('support_level')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['issue_category_id', 'subcategory_id', 'name'], 'subject_unique_per_subcategory');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_catalog_subjects');
    }
};
