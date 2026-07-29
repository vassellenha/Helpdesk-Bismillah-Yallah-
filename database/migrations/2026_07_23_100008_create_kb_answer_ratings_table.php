<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bintang 1–5 dari karyawan atas SATU jawaban EVA.
 *
 * unique(answer_log_id, rated_by) menegakkan "sekali nilai per jawaban" di
 * level database, bukan hanya di UI — di mockup aturan itu hanya dijaga
 * komponen, sehingga bisa dilanggar lewat jalur lain.
 *
 * Rata-rata rating per artikel DIHITUNG dari tabel ini, tidak pernah disalin
 * jadi kolom di kb_articles.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kb_answer_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('answer_log_id')->constrained('kb_answer_logs')->cascadeOnDelete();
            $table->foreignId('rated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedTinyInteger('stars');
            $table->string('reason')->nullable();
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->unique(['answer_log_id', 'rated_by'], 'kb_answer_ratings_once_per_rater');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kb_answer_ratings');
    }
};
