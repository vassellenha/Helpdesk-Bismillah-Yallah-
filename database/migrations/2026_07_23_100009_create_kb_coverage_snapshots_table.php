<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Riwayat coverage — HANYA untuk grafik tren. Angka coverage hari ini selalu
 * dihitung ulang dari data (subject katalog yang punya artikel/FAQ aktif),
 * tidak pernah dibaca dari sini.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kb_coverage_snapshots', function (Blueprint $table) {
            $table->id();
            $table->date('captured_on');
            $table->unsignedInteger('total_subjects');
            $table->unsignedInteger('covered_subjects');
            $table->unsignedTinyInteger('coverage_percent');
            $table->timestamps();

            $table->unique('captured_on', 'kb_coverage_snapshots_day');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kb_coverage_snapshots');
    }
};
