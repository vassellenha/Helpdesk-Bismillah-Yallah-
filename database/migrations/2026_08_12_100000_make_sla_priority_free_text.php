<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Melepas `sla_policies.priority` dari enum empat nilai menjadi teks bebas.
 *
 * Admin tidak bisa menambah tingkat prioritas baru — "Urgent", "Emergency" —
 * karena kolomnya enum('Critical','High','Medium','Low'). Larangannya di lapisan
 * database, jadi mengganti dropdown di layar saja tidak cukup: MySQL menolak
 * nilai di luar keempatnya sebelum kode aplikasi sempat berkata apa-apa.
 *
 * `tickets.priority` sudah varchar sejak awal dan menyalin nilainya dari policy
 * yang dipakai, jadi tidak ada yang perlu diubah di sana.
 *
 * Turunnya kembali ke enum sengaja tidak disediakan: begitu ada satu saja policy
 * dengan prioritas di luar keempat nilai lama, MySQL akan memotongnya jadi string
 * kosong tanpa peringatan. Lebih baik gagal terang-terangan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sla_policies', function (Blueprint $table) {
            $table->string('priority', 50)->change();
        });
    }

    public function down(): void
    {
        throw new RuntimeException(
            'Tidak bisa dikembalikan ke enum: policy dengan prioritas di luar '
            .'Critical/High/Medium/Low akan dipotong jadi string kosong oleh MySQL. '
            .'Hapus atau ubah dulu policy tersebut secara manual bila memang perlu.'
        );
    }
};
