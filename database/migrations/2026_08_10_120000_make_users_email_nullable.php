<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `users.email` menjadi nullable — tetap unique.
 *
 * Bukan pelonggaran validasi, melainkan pengakuan atas bentuk data sumbernya:
 * dari 3.847 pegawai yang dikirim API perusahaan
 * (mobile.adhi.co.id/api/index.php/v2/kms2/karyawan/all), 1.278 orang (33%)
 * memang tidak punya alamat surel korporat. NULL menyatakan "tidak diketahui";
 * string kosong menyatakan "alamatnya adalah kekosongan" — dan yang kedua itu
 * bohong sekaligus mustahil disimpan, karena unique index hanya sanggup memuat
 * SATU string kosong. Baris kedua tanpa email akan menabraknya dan mematikan
 * sync di tengah jalan, setelah sebagian pegawai sudah tertulis.
 *
 * MySQL (dan SQLite) mengizinkan NULL berulang di unique index, jadi keunikan
 * alamat yang benar-benar ada tetap dijaga — persis pola yang sudah dipakai
 * kolom `username` sejak awal.
 *
 * Form Admin tetap MEWAJIBKAN email (UserRoleController) dan itu disengaja:
 * yang boleh kosong hanya baris yang datang dari API, bukan yang diketik orang.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
        });
    }

    /**
     * Turun tidak bisa sekadar membalik nullable: begitu ada baris ber-email
     * NULL, mengembalikan NOT NULL akan ditolak database. Baris seperti itu
     * diberi alamat penampung dari NIP-nya supaya rollback tidak mustahil —
     * jelas-jelas bukan alamat sungguhan, dan kolomnya tetap unique.
     */
    public function down(): void
    {
        DB::table('users')
            ->whereNull('email')
            ->orderBy('id')
            ->each(function ($user) {
                DB::table('users')->where('id', $user->id)->update([
                    'email' => 'tanpa-email-'.$user->id.'@invalid.local',
                ]);
            });

        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable(false)->change();
        });
    }
};
