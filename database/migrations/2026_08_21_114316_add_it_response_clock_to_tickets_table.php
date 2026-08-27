<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jam respons terpisah untuk tahap IT.
 *
 * Sebelum ini tiket hanya punya SATU jam respons (`response_due_at` /
 * `first_response_at`), dan `markFirstResponse()` bersifat idempoten — sekali
 * distempel, tidak bisa disetel ulang. Menekan "Eskalasi IT" sendiri dihitung
 * sebagai respons (memutuskan tiket perlu IT memang sebuah tanggapan), jadi
 * begitu tiket berpindah tangan jam responsnya sudah beku: PIC IT mewarisi
 * "sudah direspons" milik BPO dan tidak pernah punya batas responsnya sendiri.
 * Tiket bisa menganggur berhari-hari di tangan IT tanpa satu pun indikator
 * berubah warna.
 *
 * Kolom baru ini SENGAJA tidak menyentuh dua kolom lama. `first_response_at`
 * tetap berarti "respons pertama atas tiket ini" — itu yang dibaca
 * TeamLeadController untuk rata-rata waktu respons tim, dan mengubah artinya
 * akan diam-diam menggeser angka laporan yang sudah berjalan. Yang baru
 * menjawab pertanyaan berbeda: "setelah sampai ke IT, berapa lama sampai IT
 * menanggapi?"
 *
 * Keduanya null untuk tiket yang belum pernah dieskalasi, dan itu memang
 * pembedanya — lihat Ticket::getActiveResponseDueAtAttribute().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->timestamp('it_response_due_at')->nullable()->after('response_due_at');
            $table->timestamp('it_first_response_at')->nullable()->after('first_response_at');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn(['it_response_due_at', 'it_first_response_at']);
        });
    }
};
