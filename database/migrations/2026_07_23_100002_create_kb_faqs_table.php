<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FAQ ditulis admin dan LANGSUNG tayang — tidak ada alur review (aturan #2).
 * Karena itu tidak ada kolom `status`: satu-satunya gerbang adalah
 * is_eva_visible.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kb_faqs', function (Blueprint $table) {
            $table->id();
            $table->string('question', 500);
            $table->text('answer');

            $table->foreignId('catalog_subject_id')->nullable()
                ->constrained('service_catalog_subjects')->nullOnDelete();

            $table->boolean('is_eva_visible')->default(true);
            $table->string('tags')->nullable();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('catalog_subject_id', 'kb_faqs_subject_index');
            // Lihat catatan di migrasi kb_articles: FULLTEXT dilewati di SQLite.
            if (in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true)) {
                $table->fullText(['question', 'answer'], 'kb_faqs_fulltext');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kb_faqs');
    }
};
