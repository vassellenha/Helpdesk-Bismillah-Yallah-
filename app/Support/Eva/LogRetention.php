<?php

declare(strict_types=1);

namespace App\Support\Eva;

use DateTimeInterface;
use Illuminate\Support\Carbon;

/**
 * Aturan masa simpan log EVA.
 *
 * Berdiri sendiri karena dipakai dua pihak yang tidak boleh berselisih:
 * `eva:purge-expired-logs` yang MENGHAPUS, dan hitung mundur di layar
 * Unanswered Questions serta Log Percakapan yang MENJANJIKAN kapan penghapusan
 * itu terjadi. Kalau masing-masing menghitung sendiri dari config, cukup satu
 * pihak salah tanda pertidaksamaan untuk membuat layar menjanjikan "2 hari
 * lagi" pada baris yang malam itu juga disapu — dan tidak ada yang akan
 * menyadarinya sampai ada admin yang kehilangan pekerjaannya.
 */
final class LogRetention
{
    private const DEFAULT_DAYS = 14;

    public static function days(): int
    {
        return (int) config('eva.log_retention_days', self::DEFAULT_DAYS);
    }

    /** Baris yang lebih tua dari ini disapu pada jalannya perintah berikutnya. */
    public static function cutoff(): Carbon
    {
        return Carbon::now()->subDays(self::days());
    }

    /**
     * Sisa hari sebelum sebuah baris disapu.
     *
     * Dijepit di 0, tidak pernah negatif. Baris yang sudah lewat tenggat tapi
     * belum tersapu memang nyata ada di layar — penyapu jalan sekali sehari,
     * jadi ada jeda sampai 24 jam. "-3 hari" tidak berarti apa-apa bagi admin.
     */
    public static function daysLeft(?DateTimeInterface $createdAt): ?int
    {
        if ($createdAt === null) {
            return null;
        }

        $sisa = self::days() - (int) Carbon::instance($createdAt)->diffInDays(Carbon::now());

        return max(0, $sisa);
    }
}
