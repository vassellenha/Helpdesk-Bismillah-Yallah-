<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Rentang "Minggu / Bulan / Tahun" untuk ringkasan dashboard.
 *
 * Dipakai bersama oleh dashboard Requester dan Approver. Ditaruh di satu kelas
 * karena keduanya harus menjawab "minggu ini" dengan tanggal yang SAMA — kalau
 * masing-masing menghitung sendiri, dua layar bisa menampilkan rentang berbeda
 * pada hari yang sama dan tidak ada yang tahu mana yang benar.
 *
 * Kunci periodenya sengaja sama persis dengan yang sudah dipakai dashboard
 * Support dan Support BPO ('week', 'month', 'year'), supaya seluruh helpdesk
 * memakai satu kosakata.
 */
final class DashboardPeriod
{
    public const KEYS = ['week', 'month', 'year'];

    public const DEFAULT = 'month';

    /** Awal rentang: Senin minggu ini, tanggal 1 bulan ini, atau 1 Januari. */
    public static function cutoff(string $period): Carbon
    {
        return match ($period) {
            'week' => Carbon::now()->startOfWeek(),
            'year' => Carbon::now()->startOfYear(),
            default => Carbon::now()->startOfMonth(),
        };
    }

    /**
     * Titik-titik grafik untuk satu periode. Kepadatannya mengikuti rentang —
     * minggu dipecah per hari, bulan per minggu, tahun per bulan — supaya tiap
     * pilihan menghasilkan 4–12 titik. Memakai satu satuan untuk ketiganya
     * selalu salah di salah satu ujung: 365 batang harian tak terbaca, dan satu
     * batang bulanan untuk "minggu ini" tidak menjelaskan apa pun.
     *
     * @return Collection<int,array{start:Carbon,end:Carbon,label:string}>
     */
    public static function buckets(string $period): Collection
    {
        return match ($period) {
            'week' => self::dailyBuckets(),
            'year' => self::monthlyBuckets(),
            default => self::weeklyBuckets(),
        };
    }

    /** Tujuh hari minggu berjalan, Senin sampai Minggu. */
    private static function dailyBuckets(): Collection
    {
        $start = Carbon::now()->startOfWeek();

        return collect(range(0, 6))->map(function (int $i) use ($start) {
            $day = $start->clone()->addDays($i);

            return [
                'start' => $day->clone()->startOfDay(),
                'end' => $day->clone()->endOfDay(),
                'label' => $day->translatedFormat('D'),
            ];
        });
    }

    /**
     * Minggu-minggu di dalam bulan berjalan. Potongan pertama dan terakhir
     * sengaja dipotong di batas bulan, bukan diperpanjang ke bulan tetangga:
     * grafik berjudul "Bulan" tidak boleh menghitung tiket bulan lain.
     */
    private static function weeklyBuckets(): Collection
    {
        $monthStart = Carbon::now()->startOfMonth();
        $monthEnd = Carbon::now()->endOfMonth();

        $buckets = collect();
        $cursor = $monthStart->clone();
        $index = 1;

        while ($cursor->lessThanOrEqualTo($monthEnd)) {
            $end = $cursor->clone()->endOfWeek();

            $buckets->push([
                'start' => $cursor->clone(),
                'end' => $end->greaterThan($monthEnd) ? $monthEnd->clone() : $end,
                'label' => 'M'.$index,
            ]);

            $cursor = $end->clone()->addSecond();
            $index++;
        }

        return $buckets;
    }

    /** Dua belas bulan tahun berjalan. */
    private static function monthlyBuckets(): Collection
    {
        $start = Carbon::now()->startOfYear();

        return collect(range(0, 11))->map(function (int $i) use ($start) {
            // addMonthsNoOverflow dari tanggal 1 tidak bisa meluap, tapi
            // ditulis eksplisit supaya tetap benar bila awalnya diubah nanti.
            $month = $start->clone()->addMonthsNoOverflow($i);

            return [
                'start' => $month->clone()->startOfMonth(),
                'end' => $month->clone()->endOfMonth(),
                'label' => $month->translatedFormat('M'),
            ];
        });
    }
}
