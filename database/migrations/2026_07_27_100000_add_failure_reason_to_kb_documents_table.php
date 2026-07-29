<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Alasan kegagalan indexing.
 *
 * Dibutuhkan begitu indexing pindah ke antrean: selama masih sinkron, berkas
 * yang tak terbaca dijawab 422 berikut kalimat yang menjelaskan langkah
 * berikutnya. Setelah asinkron, request sudah selesai jauh sebelum mesinnya
 * menjawab — tanpa kolom ini yang tersisa di layar cuma lencana merah
 * "failed" tanpa satu pun petunjuk apakah berkasnya rusak, mesin OCR-nya belum
 * terpasang, atau isinya memang kosong.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kb_documents', function (Blueprint $table) {
            $table->string('failure_reason', 500)->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('kb_documents', function (Blueprint $table) {
            $table->dropColumn('failure_reason');
        });
    }
};
