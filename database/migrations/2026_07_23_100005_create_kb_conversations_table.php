<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Percakapan karyawan dengan EVA dan hasil akhirnya (Log Percakapan).
 *
 * ticket_reference hanyalah CATATAN nomor tiket yang diterbitkan sistem
 * Helpdesk setelah karyawan mengirim drafnya sendiri — EVA tidak pernah
 * menulis ke tabel tiket (aturan #4). Karena itu ini string, bukan FK.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kb_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('requester_name')->nullable();
            $table->string('department')->nullable();
            $table->string('outcome', 24)->default('open');
            $table->string('ticket_reference')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamps();

            $table->index('outcome');
            $table->index('started_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kb_conversations');
    }
};
