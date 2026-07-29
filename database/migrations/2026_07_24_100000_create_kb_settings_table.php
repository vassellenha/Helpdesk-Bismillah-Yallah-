<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pengaturan EVA yang berubah dari layar admin, bukan dari .env.
 *
 * Sengaja key-value, bukan satu kolom per pengaturan: toggle sumber jawaban
 * kemungkinan bertambah (chunk dokumen, sumber eksternal) dan menambah kolom
 * tiap kali adalah migrasi yang bisa dihindari. Nilainya string — penafsiran
 * boolean-nya di satu tempat, model KbSetting, bukan tersebar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kb_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('value');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kb_settings');
    }
};
