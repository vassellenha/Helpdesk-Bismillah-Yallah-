<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tempat menyimpan NAMA divisi dari direktori pegawai.
 *
 * API mengirim pasangan kode+nama untuk ketiga tingkat organisasi
 * (dept_id/dept_name, division_id/division_name, proy_unit_id/proy_unit_name),
 * tapi sampai sekarang hanya kodenya yang disimpan. Layar Profil Saya jadi
 * menampilkan "07", "210", "2107000001" — angka yang tidak berarti apa-apa bagi
 * pemilik akunnya sendiri.
 *
 * Hanya SATU kolom yang ditambah, bukan tiga:
 *   - nama departemen sudah tersimpan di `unit` (dipetakan dari dept_name)
 *   - nama proyek sudah punya kolom `nama_proyek`, hanya belum pernah diisi
 * Menambahkan `nama_departemen` hanya akan menduplikat isi `unit`.
 *
 * Kolom kode TIDAK dihapus dan tetap diisi sync — keduanya dipakai bersamaan:
 * kode untuk pencocokan antarsistem, nama untuk dibaca manusia.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('nama_divisi')->nullable()->after('kode_divisi');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('nama_divisi');
        });
    }
};
