<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menandai user mana yang berasal dari direktori pegawai perusahaan.
 *
 * Setelah sync pertama, tabel `users` akan bercampur: 3.847 pegawai sungguhan
 * berdampingan dengan sisa akun seed/uji coba, dan tidak ada cara membedakannya.
 * Itu bukan sekadar merepotkan mata — ia menentukan keputusan nyata, misalnya
 * siapa yang boleh dihapus saat bersih-bersih, dan siapa yang akan dinonaktifkan
 * kalau `deactivate_missing` dinyalakan.
 *
 * Sempat dipertimbangkan menebaknya dari bentuk NPP: yang asli berhuruf
 * ("B/22/07/2410/79"), yang seed murni angka ("10027761"). Ditolak setelah
 * diperiksa terhadap payload sungguhan — dari 3.847 NPP ada 168 pola berbeda,
 * dan SATU di antaranya murni angka. Tebakan itu akan salah menandai seorang
 * pegawai sungguhan sebagai data sampah, tepat pada keputusan yang paling tidak
 * boleh salah. Kolom eksplisit tidak bisa meleset.
 *
 * Nullable, dan itu maknanya: NULL = tidak pernah tersentuh sinkronisasi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('synced_at')->nullable()->after('last_login_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('synced_at');
        });
    }
};
