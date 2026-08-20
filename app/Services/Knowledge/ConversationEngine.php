<?php

declare(strict_types=1);

namespace App\Services\Knowledge;

/**
 * Lapisan PERCAKAPAN EVA — bukan lapisan menjawab.
 *
 * Pembagian wewenangnya sengaja tegas, dan inilah yang menjaga EVA tetap aman
 * dipakai di helpdesk perusahaan:
 *
 *   - Lapisan ini boleh mengatur BAHASA: menyapa, menangkap maksud, menyusun
 *     ulang pertanyaan lanjutan, membalas basa-basi.
 *   - Lapisan ini TIDAK BOLEH menjadi sumber FAKTA. Semua keterangan tentang
 *     layanan, aplikasi, prosedur, dan batas waktu ADHI tetap wajib datang dari
 *     Knowledge Base lewat KnowledgeSearch dan KnowledgeSynthesizer.
 *
 * Alasannya bukan kehati-hatian abstrak. Model bahasa tahu banyak tentang SAP,
 * VPN, dan reset password "pada umumnya" — dan itu justru bahayanya: jawaban
 * yang benar di perusahaan lain terdengar sama meyakinkannya dengan jawaban
 * yang benar di ADHI, sementara karyawan tidak punya cara membedakannya. SOP
 * karangan yang fasih lebih merugikan daripada EVA yang berkata belum tahu.
 */
interface ConversationEngine
{
    /**
     * Ubah pertanyaan lanjutan menjadi pertanyaan yang berdiri sendiri.
     *
     * "kalau masih gagal gimana?" setelah membahas reset password SAP menjadi
     * "apa yang harus dilakukan bila reset password SAP masih gagal?". Hasilnya
     * dipakai untuk MENCARI di KB — jadi ini murni penerjemah maksud, bukan
     * penjawab.
     *
     * WAJIB memulangkan pertanyaan yang bisa dipakai. Bila konteks tidak ada,
     * layanan mati, atau hasilnya meragukan, kembalikan `$question` apa adanya:
     * pencarian dengan pertanyaan asli masih jauh lebih baik daripada tidak ada
     * pencarian sama sekali.
     *
     * @param  list<array{role: string, message: string}>  $memory
     */
    public function standalone(string $question, array $memory): string;

    /**
     * Balas basa-basi dengan kalimat yang hidup, bukan kalimat kaleng.
     *
     * `$fallback` adalah balasan tetap yang sudah dimiliki SmallTalkDetector.
     * Ia bukan sekadar cadangan saat gagal, melainkan juga PENGUNCI MAKNA:
     * apa pun yang dipulangkan model harus tetap berupa balasan untuk maksud
     * yang sama (sapaan dibalas sapaan, terima kasih dibalas terima kasih).
     * Bila hasilnya kosong atau tidak wajar, yang dipakai `$fallback`.
     *
     * @param  list<array{role: string, message: string}>  $memory
     */
    public function chat(string $question, array $memory, string $fallback): string;

    /**
     * Jaring terakhir sebelum EVA menyerah: apakah kalimat ini sebenarnya BUKAN
     * pertanyaan layanan?
     *
     * "saya memiliki pertanyaan lagi", "boleh tanya sesuatu?", "sebentar ya" —
     * bentuk kalimat pembuka tidak terbatas, jadi daftar pola tetap tidak akan
     * pernah mengejarnya. Tanpa jaring ini semuanya berakhir sama: dibalas
     * "belum menemukan jawaban" berikut tawaran draf tiket kepada orang yang
     * bahkan belum sempat bertanya, sekaligus menumpuk di daftar kerja admin
     * sebagai celah materi yang mustahil ditutup.
     *
     * HANYA dipanggil setelah pencarian KB gagal. Pertanyaan sungguhan wajib
     * selalu mendapat kesempatan dijawab Knowledge Base lebih dulu.
     *
     * @param  list<array{role: string, message: string}>  $memory
     * @return string|null null berarti "ini memang pertanyaan layanan" — EVA
     *                     meneruskan ke jalur menyerah yang biasa
     */
    public function converse(string $question, array $memory): ?string;

    /**
     * Apakah materi ini benar-benar menjawab pertanyaannya?
     *
     * Pencarian teks bisa memulangkan materi yang bertumpang kata tapi tidak
     * bertumpang MAKSUD. "apakah EVA bisa mengarahkan saya untuk pembuatan
     * tiket" mengandung kata "tiket", dan SOP akun SAP juga menyebut "formulir
     * tiket" — cukup untuk membuatnya lolos ambang, sama sekali tidak cukup
     * untuk membuatnya menjawab. Yang lahir dari situ adalah jawaban yang
     * salah topik LENGKAP DENGAN kutipan yang membuatnya tampak resmi.
     *
     * Kutipan harus dibuktikan, bukan diandaikan. Bila materinya tidak
     * menjawab, EVA tidak boleh mengutipnya sama sekali.
     *
     * WAJIB memulangkan true bila tidak bisa memutuskan — layanan mati,
     * jaringan putus, saklar mati. Ragu-ragu tidak boleh membuat EVA berhenti
     * menjawab dari materi yang mungkin memang benar.
     */
    public function materialAnswers(string $question, string $title, string $material): bool;
}
