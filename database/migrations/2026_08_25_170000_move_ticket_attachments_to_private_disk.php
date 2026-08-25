<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Memindahkan berkas lampiran yang sudah terlanjur ada dari disk publik ke
 * disk privat.
 *
 * Mengganti kode saja tidak cukup. Rute baru membaca dari disk privat, jadi
 * tanpa langkah ini setiap lampiran lama berubah jadi "berkas hilang" di layar
 * — dan yang lebih buruk, salinannya tetap tergeletak di public/storage,
 * tetap bisa diunduh siapa saja lewat tautan lama. Lubangnya tidak tertutup
 * sampai berkasnya benar-benar pindah.
 *
 * Ditulis sebagai migrasi, bukan perintah artisan sekali jalan, supaya ia ikut
 * berjalan sendiri di setiap lingkungan yang menarik perubahan ini. Runbook
 * deploy sudah memanggil `php artisan migrate --force`; satu langkah manual
 * yang harus diingat adalah langkah yang cepat atau lambat akan terlewat.
 *
 * Aman diulang: berkas yang sudah ada di tujuan dilewati, yang sumbernya sudah
 * tidak ada juga dilewati.
 */
return new class extends Migration
{
    /** @var array<int,string> */
    private array $folders = ['ticket-attachments', 'ticket-comments'];

    public function up(): void
    {
        $this->move(from: 'public', to: 'local');
    }

    /**
     * Turun berarti kembali ke keadaan yang bocor, tapi migrasi yang tidak bisa
     * dibalik akan mengunci rollback rilis — dan rollback biasanya dijalankan
     * justru saat keadaan sudah genting.
     */
    public function down(): void
    {
        $this->move(from: 'local', to: 'public');
    }

    private function move(string $from, string $to): void
    {
        $sumber = Storage::disk($from);
        $tujuan = Storage::disk($to);
        $pindah = 0;

        foreach ($this->folders as $folder) {
            foreach ($sumber->files($folder) as $path) {
                if ($tujuan->exists($path)) {
                    continue;
                }

                $isi = $sumber->get($path);

                if ($isi === null) {
                    Log::warning('[Lampiran] Berkas tidak terbaca saat pemindahan disk.', ['path' => $path]);

                    continue;
                }

                $tujuan->put($path, $isi);
                $sumber->delete($path);
                $pindah++;
            }
        }

        if ($pindah > 0) {
            Log::info("[Lampiran] {$pindah} berkas dipindahkan dari disk '{$from}' ke '{$to}'.");
        }
    }
};
