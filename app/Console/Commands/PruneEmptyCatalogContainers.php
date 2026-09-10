<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ServiceCatalogService;
use App\Models\ServiceCatalogSubcategory;
use Illuminate\Console\Command;

/**
 * Menyapu Sub Kategori dan Layanan yang tidak berisi Subjek apa pun.
 *
 * Sisa dari perilaku lama: menambah Subjek IKUT membuat Layanan dan Sub
 * Kategori-nya (firstOrCreate di ServiceCatalogController::store), tapi
 * menghapus Subjek dulu hanya menghapus Subjeknya. Wadahnya tertinggal
 * kosong, dan tidak ketahuan selama tidak ada layar yang menampilkan Sub
 * Kategori — sampai tab "Cakupan Team Lead" ada.
 *
 * Kebocorannya sudah ditutup di destroy(), jadi perintah ini untuk yang
 * TERLANJUR tertinggal. Sekali jalan, bukan rutin.
 *
 * Melapor dulu, menghapus belakangan: tanpa --apply tidak ada satu baris pun
 * yang tersentuh. Pola yang sama dengan catalog:audit-support.
 */
class PruneEmptyCatalogContainers extends Command
{
    protected $signature = 'catalog:prune-empty
        {--apply : Benar-benar hapus Sub Kategori kosong, bukan cuma melapor}
        {--drop-service=* : id Layanan kosong yang ikut dihapus — sebutkan satu per satu}';

    protected $description = 'Sapu Sub Kategori dan Layanan yang tidak berisi Subjek apa pun';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');

        $subcategories = ServiceCatalogSubcategory::with('service')->doesntHave('subjects')->orderBy('id')->get();

        if ($subcategories->isEmpty()) {
            $this->info('Tidak ada Sub Kategori kosong.');
        } else {
            $this->warn($subcategories->count().' Sub Kategori tidak berisi Subjek apa pun:');
            $this->table(
                ['ID', 'Layanan', 'Sub Kategori'],
                $subcategories->map(fn (ServiceCatalogSubcategory $s) => [$s->id, $s->service?->name ?? '—', $s->name])->all(),
            );

            if ($apply) {
                $subcategories->each->delete();
                $this->info($subcategories->count().' Sub Kategori dihapus.');
            }
        }

        $this->newLine();

        /*
         | Dihitung SETELAH Sub Kategori kosong dibuang: sebuah Layanan baru
         | benar-benar kosong begitu Sub Kategori terakhirnya hilang.
         |
         | TAPI TIDAK IKUT DISAPU OLEH --apply. Layanan tanpa Sub Kategori
         | tetap muncul di pemilih Aplikasi pada form Tiket Baru, dengan Sub
         | Category jatuh ke "Other" — lihat MASTER_APPLICATIONS di
         | ServiceCatalogSeeder. Sebagian besar yang muncul di daftar ini
         | memang disengaja: aplikasi perusahaan yang belum punya definisi
         | Subjek. Menyapunya berarti menghapus pilihan dari layar requester
         | tanpa ada yang meminta.
         |
         | Jadi daftarnya ditampilkan untuk DIBACA, dan yang benar-benar
         | sampah dibuang dengan menyebut id-nya: --drop-service=12
         */
        $services = ServiceCatalogService::doesntHave('subjects')->doesntHave('subcategories')->orderBy('id')->get();
        $drop = array_map('intval', (array) $this->option('drop-service'));

        if ($services->isEmpty()) {
            $this->info('Tidak ada Layanan kosong.');
        } else {
            $this->warn($services->count().' Layanan tidak berisi Sub Kategori maupun Subjek:');
            $this->table(
                ['ID', 'Layanan', ''],
                $services->map(fn (ServiceCatalogService $s) => [
                    $s->id,
                    $s->name,
                    in_array($s->id, $drop, true) ? '← akan dihapus' : '',
                ])->all(),
            );
            $this->line('Sebagian besar Layanan di atas DISENGAJA: tanpa Sub Kategori pun ia tetap');
            $this->line('muncul di pemilih Aplikasi pada form Tiket Baru. Hapus hanya yang Anda');
            $this->line('yakin sampah, dengan menyebut id-nya: --drop-service=12 --drop-service=34');
        }

        $dibuang = $services->whereIn('id', $drop);

        if ($dibuang->isNotEmpty()) {
            $dibuang->each->delete();
            $this->newLine();
            $this->info($dibuang->count().' Layanan dihapus: '.$dibuang->pluck('name')->implode(', '));
        }

        if ($tidakDitemukan = array_diff($drop, $services->pluck('id')->all())) {
            $this->error('id Layanan berikut tidak ada di daftar kosong, jadi dilewati: '.implode(', ', $tidakDitemukan));
        }

        if (! $apply && $subcategories->isNotEmpty()) {
            $this->newLine();
            $this->line('Sub Kategori belum dihapus. Jalankan ulang dengan --apply kalau daftarnya sudah benar.');
        }

        return self::SUCCESS;
    }
}
