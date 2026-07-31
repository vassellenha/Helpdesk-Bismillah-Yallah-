<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rounds out the "My Profile" field set: login identity (username, seeded from
 * the corporate email), postal address, and the departemen/divisi codes that sit
 * alongside the existing kode_proyek.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->nullable()->unique()->after('email');
            $table->text('address')->nullable()->after('whatsapp');
            $table->string('kode_departemen')->nullable()->after('jabatan');
            $table->string('kode_divisi')->nullable()->after('kode_departemen');
        });

        // Existing accounts have no username yet; email is already unique, so it
        // is a safe seed value and matches how new users are created.
        DB::table('users')->whereNull('username')->update([
            'username' => DB::raw('email'),
        ]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropColumn(['username', 'address', 'kode_departemen', 'kode_divisi']);
        });
    }
};
