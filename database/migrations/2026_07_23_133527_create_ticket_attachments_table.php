<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ticket_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('path');
            $table->timestamps();
        });

        // Carry forward any single attachment already stored on a ticket
        // before this table existed, so existing tickets don't lose them.
        DB::table('tickets')
            ->whereNotNull('attachment_path')
            ->select('id', 'attachment_name', 'attachment_path', 'created_at', 'updated_at')
            ->orderBy('id')
            ->each(function ($ticket) {
                DB::table('ticket_attachments')->insert([
                    'ticket_id' => $ticket->id,
                    'name' => $ticket->attachment_name,
                    'path' => $ticket->attachment_path,
                    'created_at' => $ticket->created_at,
                    'updated_at' => $ticket->updated_at,
                ]);
            });

        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn(['attachment_name', 'attachment_path']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->string('attachment_name')->nullable();
            $table->string('attachment_path')->nullable();
        });

        Schema::dropIfExists('ticket_attachments');
    }
};
