<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Setiap pertanyaan yang masuk ke EVA dicatat di sini — WAJIB sejak hari
 * pertama. Inilah satu-satunya sumber untuk Top Questions, content gaps,
 * Unanswered Questions, dan deflection rate. Tanpa tabel ini semua angka itu
 * jadi daftar beku seperti di mockup.
 *
 * `outcome`: answered | clarify | no_answer | ticket_draft
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kb_answer_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->nullable()
                ->constrained('kb_conversations')->nullOnDelete();
            $table->string('question', 500);
            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->foreignId('catalog_subject_id')->nullable()
                ->constrained('service_catalog_subjects')->nullOnDelete();
            $table->unsignedTinyInteger('confidence')->default(0);
            $table->string('outcome', 24);
            $table->foreignId('asked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('outcome');
            $table->index(['source_type', 'source_id'], 'kb_answer_logs_source_index');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kb_answer_logs');
    }
};
