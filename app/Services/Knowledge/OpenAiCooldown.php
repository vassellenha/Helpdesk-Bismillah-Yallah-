<?php

declare(strict_types=1);

namespace App\Services\Knowledge;

use Illuminate\Support\Facades\Cache;

/**
 * Ingatan pendek bahwa OpenAI sedang menolak.
 *
 * Saat kuota habis, satu pertanyaan menempuh DUA panggilan yang sudah pasti
 * ditolak — rangkuman lalu parafrase — dan karyawan menunggu dua kali batas
 * waktu jaringan untuk jawaban yang pada akhirnya diambil dari artikel juga.
 * Di log itu terlihat berpasangan, satu detik terpaut:
 *
 *   11:13:57  EVA rangkuman ditolak OpenAI. {"status":429}
 *   11:13:58  EVA parafrase ditolak OpenAI. {"status":429}
 *
 * Penolakan pertama sudah cukup jadi kabar: selama satu menit berikutnya kedua
 * mesin berhenti menelepon dan langsung memakai teks Knowledge Base. Lewat itu
 * mereka mencoba lagi sendiri — tidak ada sakelar yang harus dinyalakan orang,
 * dan pemulihannya tidak menunggu siapa pun.
 *
 * Ditumpangkan pada cache karena sifatnya memang begitu: boleh hilang, boleh
 * kedaluwarsa sendiri, dan tidak apa-apa kalau tiap server punya catatannya
 * masing-masing. Kehilangan catatan ini paling buruk berarti satu panggilan
 * tambahan yang gagal — persis keadaan sebelum kelas ini ada.
 */
final class OpenAiCooldown
{
    private const KEY = 'eva:openai:cooldown';

    /** Cukup lama untuk melewati satu jendela limit per menit, cukup pendek supaya pulih tanpa terasa. */
    private const SECONDS = 60;

    public static function active(): bool
    {
        return Cache::get(self::KEY) !== null;
    }

    /**
     * Ditandai HANYA untuk kegagalan yang menimpa semua panggilan berikutnya:
     * jaringan putus, kuota habis (429), kunci ditolak (401/403), dan server
     * OpenAI bermasalah (5xx).
     *
     * Kesalahan 400 sengaja TIDAK ikut. Itu keluhan atas isi permintaan kita
     * sendiri — seperti nama parameter yang salah — dan mendiamkannya selama
     * semenit hanya akan menyembunyikan bug kita di balik jeda yang terlihat
     * seperti gangguan jaringan.
     */
    public static function start(): void
    {
        Cache::put(self::KEY, true, self::SECONDS);
    }

    public static function shouldPauseFor(int $status): bool
    {
        return $status === 429 || $status === 401 || $status === 403 || $status >= 500;
    }

    /** Dipakai tes untuk memerankan "satu menit sudah lewat". */
    public static function forget(): void
    {
        Cache::forget(self::KEY);
    }
}
