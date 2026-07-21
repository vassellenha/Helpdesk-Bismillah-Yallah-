<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('nip')->nullable()->after('name');
            $table->string('whatsapp')->nullable()->after('email');
            $table->string('unit')->nullable();
            $table->string('jabatan')->nullable();
            $table->string('kode_proyek')->nullable();
            $table->string('nama_proyek')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamp('last_login_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['nip', 'whatsapp', 'unit', 'jabatan', 'kode_proyek', 'nama_proyek', 'status', 'last_login_at']);
        });
    }
};
