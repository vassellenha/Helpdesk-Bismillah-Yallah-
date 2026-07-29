<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jejak penyunting artikel.
 *
 * `author_id` menjawab "artikel ini lahir dari materi siapa" dan tidak pernah
 * berubah. Yang tidak terjawab sebelum ini: SIAPA yang terakhir menimpa judul,
 * ringkasan, dan body-nya. Artikel lahir dari dokumen (aturan #1), tapi
 * isinya boleh disunting bebas lewat PUT — tanpa kolom ini, perubahan isi yang
 * memelintir jawaban EVA tidak meninggalkan bekas siapa pun.
 *
 * Nullable karena artikel lama (dan artikel yang lahir dari indexer, bukan
 * dari tangan manusia) memang tidak punya penyunting.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kb_articles', function (Blueprint $table) {
            $table->foreignId('updated_by')->nullable()->after('author_id')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('kb_articles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('updated_by');
        });
    }
};
