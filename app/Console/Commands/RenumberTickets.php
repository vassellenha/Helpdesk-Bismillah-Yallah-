<?php

namespace App\Console\Commands;

use App\Models\Ticket;
use App\Support\TicketNumber;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Menomori ulang SELURUH tiket ke format {JENIS}-{LAYANAN}-{tahun}-{urut}.
 *
 * Dipakai sekali saat format nomor berubah. Setelah itu tiket baru sudah lahir
 * dengan format yang benar lewat TicketNumber::next(), jadi perintah ini tidak
 * perlu dijadwalkan.
 *
 * INI MENIMPA NOMOR YANG SUDAH BEREDAR. Nomor tiket sudah terkirim lewat
 * notifikasi, mungkin tercetak di ekspor PDF, dan mungkin sudah disebut orang
 * di percakapan. Semuanya tidak akan cocok lagi setelah perintah ini jalan.
 * Jalankan `--dry-run` dulu.
 *
 * Urutannya mengikuti UMUR tiket, bukan urutan id: tiket tertua sebuah layanan
 * mendapat 0001. Kalau tidak, nomor kecil akan menunjuk tiket yang justru baru,
 * dan angka urut itu berbohong tentang urutan kejadian.
 */
class RenumberTickets extends Command
{
    protected $signature = 'tickets:renumber {--dry-run : Tampilkan rencananya saja, tanpa mengubah apa pun}';

    protected $description = 'Menomori ulang seluruh tiket ke format {JENIS}-{LAYANAN}-{tahun}-{urut}.';

    public function handle(): int
    {
        $rencana = $this->susunRencana();

        if ($rencana === []) {
            $this->info('Tidak ada tiket untuk dinomori ulang.');

            return self::SUCCESS;
        }

        $berubah = array_filter($rencana, fn (array $b) => $b['lama'] !== $b['baru']);

        $this->table(
            ['ID', 'Nomor lama', 'Nomor baru'],
            array_map(fn (array $b) => [$b['id'], $b['lama'], $b['baru']], array_slice($berubah, 0, 20)),
        );

        if (count($berubah) > 20) {
            $this->line(sprintf('… dan %d tiket lainnya.', count($berubah) - 20));
        }

        if ($this->option('dry-run')) {
            $this->warn(sprintf('Uji coba saja — %d tiket AKAN berubah kalau dijalankan tanpa --dry-run.', count($berubah)));

            return self::SUCCESS;
        }

        if ($berubah === []) {
            $this->info('Semua tiket sudah memakai format terbaru.');

            return self::SUCCESS;
        }

        $this->tulisUlang($rencana);

        Log::warning('Nomor tiket ditulis ulang', [
            'jumlah' => count($berubah),
            'contoh' => array_slice(array_map(fn (array $b) => $b['lama'].' → '.$b['baru'], $berubah), 0, 5),
        ]);

        $this->info(sprintf('%d tiket dinomori ulang.', count($berubah)));

        return self::SUCCESS;
    }

    /**
     * @return array<int,array{id:int,lama:string,baru:string}>
     */
    private function susunRencana(): array
    {
        $penghitung = [];
        $rencana = [];

        // Diurutkan menurut created_at, bukan id — data hasil seeder dibuat
        // dengan umur acak, sehingga id besar bisa saja lebih tua.
        foreach (Ticket::query()->orderBy('created_at')->orderBy('id')->get() as $tiket) {
            $kode = TicketNumber::serviceCode($tiket->service_name);
            $tahun = ($tiket->created_at ?? now())->format('Y');
            $kunci = $kode.'|'.$tahun;

            $penghitung[$kunci] = ($penghitung[$kunci] ?? 0) + 1;

            $rencana[] = [
                'id' => $tiket->id,
                'lama' => $tiket->ticket_no,
                'baru' => sprintf(
                    '%s-%s-%s-%04d',
                    TicketNumber::prefixFor($tiket->issue_category),
                    $kode,
                    $tahun,
                    $penghitung[$kunci],
                ),
            ];
        }

        return $rencana;
    }

    /**
     * Ditulis DUA TAHAP di dalam satu transaksi.
     *
     * Kolom ticket_no unik. Menulis langsung ke nomor akhir bisa bertabrakan di
     * tengah jalan — nomor baru sebuah tiket mungkin persis nomor lama tiket
     * lain yang belum sempat diganti. Tahap pertama memindahkan semuanya ke
     * nilai sementara yang pasti tidak dipakai siapa pun, sehingga tahap kedua
     * selalu menemukan tempat kosong.
     *
     * @param  array<int,array{id:int,lama:string,baru:string}>  $rencana
     */
    private function tulisUlang(array $rencana): void
    {
        DB::transaction(function () use ($rencana) {
            foreach ($rencana as $baris) {
                DB::table('tickets')->where('id', $baris['id'])
                    ->update(['ticket_no' => 'TMP-'.$baris['id']]);
            }

            foreach ($rencana as $baris) {
                DB::table('tickets')->where('id', $baris['id'])
                    ->update(['ticket_no' => $baris['baru']]);
            }
        });
    }
}
