<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * catalog_subject_id sudah ada, tapi null untuk tiket "Lainnya" (requester
 * memilih Layanan tapi tidak ada Subcategory/Subject yang cocok). Tanpa
 * kolom ini, tiket "Lainnya" tidak tahu Layanan mana yang harus dilempar ke
 * semua PIC BPO-nya — service_name cuma string tampilan, bukan kunci yang
 * bisa dipakai untuk mencari PIC di service_catalog_service_bpo_pics.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->foreignId('service_catalog_service_id')->nullable()->after('catalog_subject_id')
                ->constrained('service_catalog_services')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('service_catalog_service_id');
        });
    }
};
