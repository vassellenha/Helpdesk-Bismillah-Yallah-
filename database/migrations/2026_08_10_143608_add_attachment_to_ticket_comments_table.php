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
        Schema::table('ticket_comments', function (Blueprint $table) {
            $table->string('attachment_name')->nullable()->after('message');
            $table->string('attachment_path')->nullable()->after('attachment_name');
            $table->string('attachment_mime_type')->nullable()->after('attachment_path');
            $table->unsignedBigInteger('attachment_size_bytes')->nullable()->after('attachment_mime_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ticket_comments', function (Blueprint $table) {
            $table->dropColumn(['attachment_name', 'attachment_path', 'attachment_mime_type', 'attachment_size_bytes']);
        });
    }
};
