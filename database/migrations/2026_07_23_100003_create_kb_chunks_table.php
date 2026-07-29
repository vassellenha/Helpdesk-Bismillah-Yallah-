<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Potongan teks dokumen. Kolom vektor sengaja BELUM dibuat — embedding akan
 * ditaruh di tabel terpisah saat pindah ke PostgreSQL/pgvector. Jangan
 * menambah tipe VECTOR MySQL 9 di sini.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kb_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('kb_documents')->cascadeOnDelete();
            $table->unsignedInteger('ordinal')->default(0);
            $table->text('content');
            $table->timestamps();

            $table->unique(['document_id', 'ordinal'], 'kb_chunks_document_ordinal');
            // Lihat catatan di migrasi kb_articles: FULLTEXT dilewati di SQLite.
            if (in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true)) {
                $table->fullText(['content'], 'kb_chunks_fulltext');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kb_chunks');
    }
};
