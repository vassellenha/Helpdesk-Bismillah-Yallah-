<?php

namespace App\Services\Knowledge;

/**
 * Sejauh mana sekumpulan kandidat sanggup membuat EVA menjawab.
 *
 * Ambang MIN_CONFIDENCE bukan satu-satunya jalan. EvaResponder memanggil
 * perangkum SEBELUM ambang diperiksa: begitu rangkuman berhasil disusun dari
 * potongan yang lolos FLOOR, jawabannya dikirim berapa pun keyakinan kandidat
 * terbaiknya. Itu disengaja — jawaban yang tersebar di beberapa dokumen tidak
 * pernah membuat satu pun di antaranya terlihat meyakinkan sendirian.
 *
 * Yang tidak disengaja adalah alat ukurnya ikut tidak tahu. Uji langsung pada
 * Search Settings dulu hanya membandingkan kandidat terbaik dengan ambang,
 * lalu menyatakan "EVA belum akan menjawab" untuk pertanyaan yang nyatanya
 * dijawab EVA. Pengelola EVA memakai layar itu untuk menilai cakupan, jadi
 * yang salah bukan jawabannya, melainkan angka yang dipakai mengambil
 * keputusan tentangnya.
 *
 * Aturannya tinggal di sini supaya responder dan alat ukurnya tidak bisa
 * berselisih lagi.
 */
final class AnswerReach
{
    /** Kandidat terbaik melewati ambang — EVA pasti menjawab. */
    public const ANSWER = 'answer';

    /**
     * Di bawah ambang, tapi masih cukup untuk dicoba dirangkum. Hasil akhirnya
     * bergantung pada perangkum, jadi yang jujur dikatakan adalah "mungkin",
     * bukan "tidak".
     */
    public const MAYBE = 'maybe_synthesis';

    /** Tidak ada yang bisa dipakai — EVA menawarkan draf tiket. */
    public const NONE = 'none';

    /**
     * Batas bawah potongan yang masih layak disodorkan ke perangkum.
     *
     * Sumbernya di sini, dan EvaResponder membacanya dari sini juga: selama
     * angkanya disalin di dua tempat, keduanya akan berpisah pada perubahan
     * pertama dan layar akan kembali berbohong dengan cara yang sama.
     */
    public const SYNTHESIS_FLOOR = 20;

    /** @param SearchHit[] $hits */
    public static function for(array $hits): string
    {
        $best = $hits[0] ?? null;

        if ($best === null) {
            return self::NONE;
        }

        if ($best->confidence >= KnowledgeSearch::MIN_CONFIDENCE) {
            return self::ANSWER;
        }

        return $best->confidence >= self::SYNTHESIS_FLOOR ? self::MAYBE : self::NONE;
    }
}
