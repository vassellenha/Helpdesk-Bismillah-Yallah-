<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pertanyaan yang sengaja disingkirkan admin dari daftar Unanswered Questions.
 *
 * KENAPA TABEL SENDIRI, BUKAN KOLOM DI kb_answer_logs. Log jawaban adalah
 * catatan KEJADIAN — "pada tanggal sekian seseorang bertanya ini dan EVA gagal".
 * Menandai atau menghapus baris di sana akan mengubah angka Analytics dan
 * deflection rate bulan lalu, yaitu memalsukan masa lalu untuk merapikan daftar
 * kerja hari ini. Yang disingkirkan karena itu TEKS pertanyaannya, di tabel
 * terpisah, dan log aslinya tidak tersentuh sama sekali.
 *
 * `dismissed_at` bukan hiasan: pertanyaan yang ditanyakan LAGI setelah
 * disingkirkan akan muncul kembali. Itu bukti baru bahwa ia memang perlu
 * dijawab, dan keputusan lama tidak boleh membungkamnya selamanya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kb_dismissed_questions', function (Blueprint $table) {
            $table->id();

            // Panjang & unique sejajar dengan kb_answer_logs.question: satu
            // keputusan per teks pertanyaan, bukan per kejadian.
            $table->string('question', 500)->unique();

            // Sengaja TIDAK ada kolom `reason`. Dialog konfirmasinya tidak
            // menanyakan alasan, jadi apa pun yang tersimpan di sini akan jadi
            // karangan sistem — dan kolom yang diisi sendiri oleh kode selalu
            // berakhir dibaca orang sebagai keputusan manusia.

            $table->dateTime('dismissed_at');
            $table->foreignId('dismissed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kb_dismissed_questions');
    }
};
