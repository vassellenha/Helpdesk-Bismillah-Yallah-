<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\TicketNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Menyapu notifikasi lonceng yang sudah lewat masa simpan.
 *
 * Notifikasi menumpuk tanpa batas: satu requester aktif mengumpulkan sekitar
 * dua per hari, jadi setelah setahun loncengnya berisi ratusan baris dan
 * halaman riwayatnya belasan.
 *
 * Membuang yang lama tidak menghilangkan informasi. Catatan permanennya ada di
 * Audit Trail (siapa melakukan apa, kapan) dan pada tiketnya sendiri beserta
 * Riwayat Status dan Forum Diskusi. Notifikasi hanya pemberitahuan — begitu
 * dibaca dan tiketnya selesai, ia tidak menyimpan apa pun yang tidak ada di
 * tempat lain.
 *
 * Dua ambang, dijelaskan di config/helpdesk.php. Yang belum dibaca sengaja
 * ditahan lebih lama karena ia masih berupa sinyal yang belum sempat dilihat.
 *
 * Aman dijalankan berkali-kali, dan aman dijalankan saat tidak ada yang perlu
 * dibuang. Pakai --dry-run untuk melihat dulu berapa yang akan hilang.
 */
class PurgeOldNotifications extends Command
{
    protected $signature = 'notifications:purge {--dry-run : Tampilkan jumlahnya tanpa menghapus apa pun}';

    protected $description = 'Hapus notifikasi lonceng yang sudah lewat masa simpan.';

    public function handle(): int
    {
        $kering = (bool) $this->option('dry-run');

        $hariDibaca = (int) config('helpdesk.notification_retention.read_days');
        $hariBelum = (int) config('helpdesk.notification_retention.unread_days');

        $terhapus = 0;
        $terhapus += $this->sapu('sudah dibaca', $hariDibaca, true, $kering);
        $terhapus += $this->sapu('belum dibaca', $hariBelum, false, $kering);

        if ($kering) {
            $this->info("Uji coba: {$terhapus} notifikasi AKAN dihapus. Tidak ada yang tersentuh.");

            return self::SUCCESS;
        }

        // Dicatat ke log, bukan ke Audit Trail: ini pemeliharaan sistem yang
        // dijalankan penjadwal, bukan tindakan seorang administrator atas data
        // seseorang. Audit Trail mensyaratkan pelaku, dan di sini tidak ada.
        if ($terhapus > 0) {
            Log::info('notifications:purge menghapus notifikasi kedaluwarsa.', [
                'terhapus' => $terhapus,
                'read_days' => $hariDibaca,
                'unread_days' => $hariBelum,
            ]);
        }

        $this->info("Selesai: {$terhapus} notifikasi dihapus.");

        return self::SUCCESS;
    }

    /**
     * Angka 0 atau negatif mematikan penyapuan untuk kelompok ini. Lihat
     * config/helpdesk.php — salah ketik lebih baik membuat penyapu diam
     * daripada mengosongkan lonceng seluruh perusahaan.
     */
    private function sapu(string $label, int $hari, bool $sudahDibaca, bool $kering): int
    {
        if ($hari <= 0) {
            $this->line("  {$label}: dilewati (masa simpan {$hari} hari).");

            return 0;
        }

        $batas = Carbon::now()->subDays($hari);

        $query = TicketNotification::where('created_at', '<', $batas)
            ->when($sudahDibaca, fn ($q) => $q->whereNotNull('read_at'))
            ->when(! $sudahDibaca, fn ($q) => $q->whereNull('read_at'));

        $jumlah = (clone $query)->count();

        $this->line("  {$label}: {$jumlah} lebih tua dari {$hari} hari (sebelum {$batas->toDateString()}).");

        if ($kering || $jumlah === 0) {
            return $jumlah;
        }

        return $query->delete();
    }
}
