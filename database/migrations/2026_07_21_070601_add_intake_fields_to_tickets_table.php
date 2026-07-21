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
            $table->string('service_name')->nullable()->after('title');
            $table->string('subcategory_name')->nullable()->after('service_name');
            $table->string('subject_name')->nullable()->after('subcategory_name');
            $table->string('issue_category')->nullable()->after('subject_name');
            $table->text('description')->nullable()->after('issue_category');
            $table->string('attachment_name')->nullable()->after('description');
            $table->foreignId('requester_id')->nullable()->after('requester_name')->constrained('users')->nullOnDelete();
            $table->foreignId('approver_id')->nullable()->after('requester_id')->constrained('users')->nullOnDelete();
            $table->boolean('is_draft')->default(false)->after('status');
            $table->dateTime('resolved_at')->nullable()->after('is_draft');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('requester_id');
            $table->dropConstrainedForeignId('approver_id');
            $table->dropColumn([
                'service_name', 'subcategory_name', 'subject_name', 'issue_category',
                'description', 'attachment_name', 'is_draft', 'resolved_at',
            ]);
        });
    }
};
