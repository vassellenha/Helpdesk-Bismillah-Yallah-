<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Satu giliran bicara. `role` = user | eva.
 * source_type/source_id menunjuk artikel atau FAQ yang dikutip EVA — polymorphic
 * ringan, tidak di-FK karena sumbernya boleh dihapus tanpa merusak riwayat.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kb_conversation_turns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('kb_conversations')->cascadeOnDelete();
            $table->unsignedInteger('ordinal')->default(0);
            $table->string('role', 16);
            $table->text('message');
            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->unsignedTinyInteger('confidence')->nullable();
            $table->boolean('is_clarifying')->default(false);
            $table->timestamps();

            $table->unique(['conversation_id', 'ordinal'], 'kb_turns_conversation_ordinal');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kb_conversation_turns');
    }
};
