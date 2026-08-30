<?php

namespace App\Services\Knowledge;

/**
 * Pencarian B — mencocokkan pertanyaan bebas ke NAMA MASALAH di katalog
 * (service_catalog_subjects), bukan ke jawaban.
 *
 * Ini colokan yang bisa ditukar, kembar dengan KnowledgeSearch (Pencarian A).
 * Implementasi sekarang, SubjectMatcher, memakai cocok-kata + jarak edit.
 * Saat lisensi model AI tersedia, ganti ke implementasi berbasis embedding
 * cukup dengan menukar SATU baris bind() di AppServiceProvider — controller,
 * layar, dan seluruh pemanggil tidak berubah, karena mereka hanya tahu
 * kontrak ini, bukan cara kerjanya.
 *
 * Ambang di sini SENGAJA milik kontrak, bukan implementasi: apa pun mesin
 * penilainya nanti, keputusan "cukup yakin untuk isi otomatis" vs "hanya layak
 * jadi calon" harus tetap dibaca dengan garis yang sama, supaya perilaku EVA
 * di layar tidak diam-diam berubah saat mesinnya diganti.
 */
interface SubjectSearch
{
    /**
     * Cukup yakin untuk MENGISI OTOMATIS subject pada draf tiket.
     *
     * Jauh di atas SUGGEST_FLOOR: mengisi dropdown dengan tebakan salah lebih
     * berbahaya daripada membiarkannya kosong, karena kolom yang sudah terisi
     * cenderung tidak diperiksa lagi.
     */
    public const MIN_CONFIDENCE = 50;

    /**
     * Cukup relevan untuk MASUK DAFTAR CALON yang dibaca manusia. Lebih rendah
     * karena taruhannya ringan — orang bisa memilih sendiri; yang mahal justru
     * menyembunyikan calon yang benar.
     */
    public const SUGGEST_FLOOR = 30;

    /**
     * Selisih keyakinan sekecil ini dianggap SERI, dan terbaik() menolak
     * memilih di antaranya.
     *
     * Ada di interface, bukan lagi private di SubjectMatcher, karena layar
     * Ticket Recommendation harus memakai aturan yang sama. Selama angkanya
     * tersembunyi, layar itu memberi lencana hijau "akan terisi otomatis" pada
     * dua calon yang seri — padahal justru tidak ada yang terisi. Layar yang
     * menjanjikan hal yang tidak terjadi lebih buruk daripada layar yang diam.
     */
    public const TIE_MARGIN = 5;

    /**
     * Subject yang paling mungkin dimaksud, terurut dari yang paling yakin.
     * Boleh kosong.
     *
     * @return SubjectMatch[]
     */
    public function cocokkan(string $pertanyaan, int $limit = 5): array;

    /**
     * Subject tunggal yang cukup meyakinkan untuk diisikan otomatis, atau null
     * bila tak satu pun melewati MIN_CONFIDENCE. Null adalah hasil yang sah.
     */
    public function terbaik(string $pertanyaan): ?SubjectMatch;

    /**
     * LAYANAN yang jelas disebut penanya, tanpa memutuskan subject-nya.
     *
     * Dipakai saat terbaik() menyerah. Pertanyaan "ini aplikasi apa?" jauh
     * lebih mudah dijawab daripada "ini masalah apa?" — nama aplikasi diketik
     * apa adanya oleh penanya, sementara nama subject harus ditebak dari
     * kalimat bebas. Menjawab yang mudah saja, lalu berhenti, lebih berguna
     * daripada diam: form Buat Tiket menerima Layanan tanpa Subject lewat sub
     * category "Lainnya", dan tiket itu tetap sampai ke tim yang benar.
     *
     * Null bila pertanyaan tidak menyebut satu pun nama layanan, atau menyebut
     * lebih dari satu — dua nama aplikasi dalam satu kalimat bukan keyakinan,
     * itu pertanyaan yang belum selesai.
     */
    public function layananTerbaik(string $pertanyaan): ?ServiceMatch;

    /**
     * Calon-calon SERI yang sama-sama kuat — bahan EVA untuk bertanya balik.
     *
     * Kembalikan isi hanya bila pertanyaan jelas menunjuk satu masalah (calon
     * teratas ≥ MIN_CONFIDENCE) TAPI ambigu antar cabang katalog: "reset
     * password" → SAP atau SILO. Kalau calon teratas lemah, itu bukan
     * keambiguan melainkan sekadar tak paham — kembalikan array kosong, biar
     * EVA menyerah dan menawarkan draf, bukan bertanya soal yang ia sendiri tak
     * yakin.
     *
     * Array kosong = tidak ada seri yang layak ditanyakan.
     *
     * @return SubjectMatch[]
     */
    public function calonSeri(string $pertanyaan): array;
}
