<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ringkasan sebuah dokumen. Satu dokumen melahirkan SATU artikel — unique di
 * source_document_id yang menegakkannya, sehingga indeks ulang tidak pernah
 * menggandakan artikel (cacat attachArticle di mockup).
 *
 * Tidak ada kolom `helpful` / rata-rata rating di sini: angka itu diagregasi
 * dari kb_answer_ratings supaya satu konsep hanya punya satu sumber.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kb_articles', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('summary')->nullable();
            $table->longText('body')->nullable();

            $table->foreignId('source_document_id')->nullable()
                ->constrained('kb_documents')->nullOnDelete();
            $table->foreignId('catalog_subject_id')->nullable()
                ->constrained('service_catalog_subjects')->nullOnDelete();

            $table->string('status', 24)->default('draft');
            $table->boolean('is_eva_visible')->default(true);
            $table->string('tags')->nullable();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('source_document_id', 'kb_articles_one_per_document');
            $table->index('catalog_subject_id', 'kb_articles_subject_index');
            // FULLTEXT hanya di driver yang mendukung (MySQL/MariaDB). SQLite —
            // dipakai test :memory: — tidak punya FULLTEXT; indeksnya dilewati
            // supaya migrasi jalan, dan pencarian FULLTEXT sengaja tak diuji di
            // sana. Logika yang tak butuh fullText (SubjectMatcher, pengaturan)
            // tetap bisa diuji penuh setelah ini.
            if (in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true)) {
                $table->fullText(['title', 'summary', 'body'], 'kb_articles_fulltext');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kb_articles');
    }
};
