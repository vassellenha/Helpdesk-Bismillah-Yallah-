<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tautan TAMBAHAN artikel → subject katalog.
 *
 * Satu SOP sering menjawab beberapa subject sekaligus: "SOP Unlock Akun SAP"
 * melayani "Aktivasi/Unlock Akun" di Access Request maupun "User Locked" di
 * Incident. Sebelum tabel ini, artikel hanya bisa menempel di satu subject dan
 * subject sisanya terhitung kosong di Coverage, Apps & Systems, serta Ticket
 * Recommendation.
 *
 * kb_articles.catalog_subject_id SENGAJA tetap ada sebagai subject UTAMA —
 * subject yang ditulis ke kb_answer_logs saat artikel ini menjawab, dan yang
 * ditampilkan sebagai identitas artikel di daftar. Tabel ini hanya menambah
 * jangkauan; ia tidak menggantikan kolom itu. Yang membaca "artikel ini
 * melayani subject apa saja" wajib lewat Article::allSubjectIds() supaya
 * gabungan keduanya cuma punya satu definisi.
 *
 * Aturan #1 tidak tersentuh: artikel tetap lahir dari SATU dokumen. Yang jamak
 * di sini adalah subject yang DILAYANI, bukan asal-usulnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kb_article_subject', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained('kb_articles')->cascadeOnDelete();
            $table->foreignId('subject_id')
                ->constrained('service_catalog_subjects')->cascadeOnDelete();
            $table->timestamps();

            // Menautkan subject yang sama dua kali tidak punya arti, dan kalau
            // dibiarkan akan menggandakan hitungan materi di pohon taksonomi.
            $table->unique(['article_id', 'subject_id'], 'kb_article_subject_unique');
            $table->index('subject_id', 'kb_article_subject_subject_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kb_article_subject');
    }
};
