<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Ticket;
use Illuminate\Support\Carbon;

/**
 * Nomor tiket: {INC|SR|AR}-{KODE LAYANAN}-{tahun}-{urut per layanan}.
 *
 * Contoh: INC-ADELE-2026-0001, AR-AKUNAPLIKASI-2026-0003, SR-OTHER-2026-0002.
 *
 * Berdiri sendiri, bukan method di TicketController, karena tiket lahir dari
 * tiga tempat: form Requester, seeder data contoh, dan perintah penomoran ulang.
 * Tiga salinan aturan yang sama pasti melenceng — dan melencengnya baru
 * ketahuan setelah ada dua tiket bernomor kembar.
 */
final class TicketNumber
{
    /** Dipakai saat tiket tidak menunjuk layanan mana pun (kategori "Other"). */
    public const NO_SERVICE = 'OTHER';

    /**
     * Nama layanan → kode yang aman dipakai di dalam nomor tiket.
     *
     * Dua jalur, berurutan:
     *
     *   1. Singkatan tulisan tangan di config `helpdesk.service_codes`. Ini
     *      jalur utama untuk nama panjang — "PERUBAHAN AKSES APLIKASI" jadi
     *      "AKSES", bukan "PERUBAHANAKSESAPLIKASI" sepanjang 22 huruf.
     *      Singkatan yang dikenali orang tidak bisa disimpulkan dari teksnya;
     *      itu pengetahuan kantor, jadi harus ditulis, bukan ditebak.
     *
     *   2. Kalau tidak terdaftar: seluruh karakter non-alfanumerik dibuang,
     *      lalu dibesarkan. Nama yang memang sudah pendek — ADELE, SAP, VPN —
     *      tidak perlu didaftarkan sama sekali.
     *
     * Hasil kedua jalur dibersihkan dengan aturan yang sama. Alasannya sempit
     * tapi menentukan: nomor urut dibaca dari segmen TERAKHIR setelah nomor
     * dipisah tanda hubung. Satu tanda hubung yang lolos — dari nama layanan
     * MAUPUN dari singkatan yang salah ketik di config — membuat pembacaan itu
     * meleset, dan tiket berikutnya mengulang angka yang sudah terpakai.
     */
    public static function serviceCode(?string $serviceName): string
    {
        $name = trim((string) $serviceName);

        if ($name === '') {
            return self::NO_SERVICE;
        }

        $code = self::sanitize(self::configuredCode($name) ?? $name);

        return $code === '' ? self::NO_SERVICE : $code;
    }

    /**
     * Singkatan dari config, dicocokkan tanpa peduli besar-kecil huruf.
     *
     * Pencocokan longgar disengaja: nama di katalog ditulis manusia dan bisa
     * saja berubah kapitalisasinya ("Sahabat APP" jadi "SAHABAT APP") tanpa ada
     * yang menganggapnya perubahan berarti. Kalau pencocokannya ketat, singkatan
     * itu diam-diam berhenti berlaku dan nomor tiket berubah bentuk tanpa
     * seorang pun mengubah aturannya.
     */
    private static function configuredCode(string $name): ?string
    {
        foreach ((array) config('helpdesk.service_codes', []) as $service => $code) {
            if (strcasecmp(trim((string) $service), $name) === 0) {
                return (string) $code;
            }
        }

        return null;
    }

    private static function sanitize(string $value): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $value) ?? '');
    }

    /**
     * Nomor berikutnya untuk satu layanan pada tahun berjalan.
     *
     * Deretnya per LAYANAN, bukan per jenis tiket: satu layanan yang punya
     * Incident dan Access Request memakai deret yang sama, sehingga nomor
     * urutnya benar-benar berarti "tiket ke-berapa untuk layanan ini".
     */
    public static function next(string $prefix, ?string $serviceName, ?Carbon $now = null): string
    {
        $now ??= Carbon::now();
        $code = self::serviceCode($serviceName);
        $year = $now->format('Y');

        return sprintf('%s-%s-%s-%04d', $prefix, $code, $year, self::lastSequence($code, $year) + 1);
    }

    /**
     * Nomor urut tertinggi yang sudah terpakai layanan ini pada tahun tersebut.
     *
     * Angkanya diurai di PHP, bukan lewat SUBSTRING_INDEX di SQL. Fungsi itu
     * hanya ada di MySQL, sedangkan tes berjalan di SQLite — versi SQL-nya
     * membuat seluruh jalur ini tidak bisa diuji sama sekali. Jumlah tiket per
     * layanan per tahun terlalu kecil untuk membuat penguraian di PHP mahal.
     */
    private static function lastSequence(string $code, string $year): int
    {
        // Pola diawali tanda hubung supaya "ELISA" tidak ikut mencocoki
        // "XELISA" — keduanya nyata bisa hidup berdampingan di katalog.
        return (int) Ticket::query()
            ->where('ticket_no', 'like', '%-'.$code.'-'.$year.'-%')
            ->pluck('ticket_no')
            ->map(fn (string $no) => (int) substr($no, (int) strrpos($no, '-') + 1))
            ->max();
    }

    /**
     * Awalan dari jenis tiket. Nilai selain ketiganya jatuh ke INC, sama
     * seperti perilaku sebelumnya.
     */
    public static function prefixFor(?string $issueCategory): string
    {
        return match ($issueCategory) {
            'Access Request' => 'AR',
            'Service Request' => 'SR',
            default => 'INC',
        };
    }
}
