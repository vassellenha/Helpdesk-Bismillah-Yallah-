<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Komentar Forum Diskusi kini mencatat SIAPA penulisnya, bukan cuma perannya.
 *
 * `author_role` sengaja menyimpan "Support" untuk Support IT maupun Support
 * BPO — itu yang dilihat Requester, dan tidak diubah. Tapi perataan gelembung
 * di layar Support ikut memakainya, sehingga pesan dua orang berbeda menumpuk
 * di sisi kanan yang sama: Support IT membaca pesan Support BPO seolah
 * tulisannya sendiri.
 *
 * Nama tidak bisa menggantikannya. Direktori pegawai perusahaan ini memuat
 * nama yang benar-benar kembar — dua "ARIEF KURNIAWAN", dua "AGUNG WIJAYANTO".
 * Yang membedakan orang hanya id-nya.
 *
 * NULLABLE dan nullOnDelete: komentar lama tidak punya id penulis dan tetap
 * sah apa adanya, sementara komentar orang yang akunnya dihapus tidak ikut
 * lenyap — riwayat diskusi tiket harus utuh walau orangnya sudah tidak ada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_comments', function (Blueprint $table) {
            $table->foreignId('author_id')
                ->nullable()
                ->after('ticket_id')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ticket_comments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('author_id');
        });
    }
};
