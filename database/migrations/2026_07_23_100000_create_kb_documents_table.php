<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hulu seluruh Knowledge Base: berkas asli yang diunggah admin.
 * Artikel lahir dari baris di sini — tidak pernah ditulis manual (aturan #1).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kb_documents', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('original_filename')->nullable();
            $table->string('extension', 16)->default('PDF');
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->string('storage_path')->nullable();

            // Isi dokumen dalam bentuk teks. Inilah bahan yang dipotong jadi
            // kb_chunks dan diringkas jadi artikel — tanpa kolom ini jalur
            // dokumen → artikel cuma pura-pura berjalan (cacat attachArticle
            // di mockup yang tidak terlihat karena data contoh sudah jadi).
            $table->longText('extracted_text')->nullable();

            // Katalog milik role Admin — EVA hanya membaca, jadi tautannya
            // selalu boleh kosong dan tidak pernah cascade (aturan #5).
            $table->foreignId('catalog_subject_id')->nullable()
                ->constrained('service_catalog_subjects')->nullOnDelete();

            // String, bukan ENUM — nilai divalidasi di aplikasi.
            $table->string('status', 24)->default('processing');
            $table->unsignedInteger('page_count')->default(0);
            $table->boolean('is_eva_visible')->default(true);
            $table->string('tags')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('indexed_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('catalog_subject_id', 'kb_documents_subject_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kb_documents');
    }
};
