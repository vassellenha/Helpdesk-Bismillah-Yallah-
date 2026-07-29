<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kelompok kata yang dianggap sama saat mencari.
 *
 * Satu baris = satu kelompok setara, mis. "password, sandi, kata sandi, pw".
 * Tidak ada kata "utama" di dalam kelompok: karyawan menulis "sandi" dan
 * dokumen menulis "password" — keduanya sah, tidak ada yang perlu dianggap
 * bentuk yang benar.
 *
 * Disimpan sebagai satu kolom teks, bukan tabel term terpisah. Itu keputusan
 * sadar: daftarnya dikelola admin, jumlahnya puluhan, dan seluruhnya dimuat
 * ke memori lalu di-cache. Menormalisasi jadi dua tabel hanya menambah join
 * untuk data yang tidak pernah di-query per baris.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kb_synonyms', function (Blueprint $table) {
            $table->id();
            $table->string('terms', 500);
            $table->boolean('is_active')->default(true);
            $table->string('note')->nullable();
            $table->timestamps();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kb_synonyms');
    }
};
