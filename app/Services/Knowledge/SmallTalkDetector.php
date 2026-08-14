<?php

declare(strict_types=1);

namespace App\Services\Knowledge;

/**
 * Sapaan dan basa-basi — satu-satunya hal yang boleh dijawab EVA tanpa
 * Knowledge Base.
 *
 * SEBELUMNYA "Halo" menempuh jalur yang sama dengan pertanyaan sungguhan:
 * dicari di KB, tidak ketemu, lalu dibalas "Maaf, saya belum menemukan jawaban
 * yang sesuai" plus tawaran draf tiket. Salah di dua sisi sekaligus. Bagi
 * karyawan, EVA terasa tidak paham bahasa manusia. Bagi admin, sapaan itu
 * masuk Unanswered Questions sebagai celah materi yang harus ditutup — padahal
 * tidak ada artikel yang bisa ditulis untuk menjawab "Halo".
 *
 * Jawabannya sengaja tetap dan ditulis di sini, bukan diminta ke model:
 * balasan sapaan tidak perlu dikarang ulang setiap kali, tidak boleh berbeda
 * tiap orang, dan harus tetap muncul saat kunci OpenAI kosong atau kuota habis.
 *
 * Kalimatnya SENGAJA tidak menyebut "TI". Helpdesk ini juga menerima keluhan
 * di luar teknologi — kursi kantor rusak, permohonan layanan umum — dan
 * sapaan yang menyebut "layanan TI" diam-diam memberi tahu penanya bahwa
 * keluhannya salah alamat, padahal tidak. Contoh yang disebut tetap contoh,
 * bukan batas.
 *
 * Yang TIDAK masuk sini: pertanyaan faktual apa pun. "Apa itu VPN" bukan
 * basa-basi — itu pertanyaan yang jawabannya harus datang dari KB, atau tidak
 * sama sekali.
 */
final class SmallTalkDetector
{
    /**
     * Pola per maksud. Diadu dengan pertanyaan yang sudah dinormalkan
     * (huruf kecil, tanpa tanda baca) dan HANYA cocok bila seluruh kalimatnya
     * memang basa-basi — "halo, bagaimana cara reset password" tidak boleh
     * tertangkap di sini.
     *
     * @var array<string, list<string>>
     */
    private const PATTERNS = [
        'greeting' => [
            'halo', 'hallo', 'helo', 'hello', 'hai', 'hi', 'hey', 'woi', 'p',
            'pagi', 'siang', 'sore', 'malam',
            'selamat pagi', 'selamat siang', 'selamat sore', 'selamat malam',
            'assalamualaikum', 'assalamu alaikum', 'salam',
            'permisi', 'halo eva', 'hai eva', 'eva',
        ],
        'howareyou' => [
            'apa kabar', 'gimana kabarnya', 'bagaimana kabarmu', 'kabar baik',
            'sehat', 'lagi apa', 'sedang apa',
        ],
        'identity' => [
            'kamu siapa', 'siapa kamu', 'anda siapa', 'siapa anda', 'nama kamu siapa',
            'eva itu apa', 'apa itu eva', 'kamu robot', 'kamu ai', 'kamu manusia',
        ],
        'capability' => [
            'kamu bisa apa', 'bisa apa', 'apa yang bisa kamu lakukan', 'bantu apa',
            'bisa bantu apa', 'fungsi kamu apa', 'kegunaan kamu', 'help', 'bantuan',
        ],
        'thanks' => [
            'terima kasih', 'terimakasih', 'makasih', 'makasi', 'thanks', 'thank you',
            'tengkyu', 'thx', 'sip', 'oke sip', 'mantap', 'baik terima kasih',
        ],
        // Tanda terima yang berdiri sendiri. Tanpa ini "ok" menempuh pencarian,
        // gagal, lalu duduk di Unanswered Questions sebagai celah materi —
        // padahal tidak ada artikel yang bisa ditulis untuk menjawab "ok".
        'ack' => [
            'ok', 'oke', 'okay', 'okey', 'oke deh', 'baik', 'siap', 'noted', 'iya', 'ya sudah',
            'yes', 'yup', 'yoi', 'betul', 'benar', 'sudah', 'belum',
        ],
        'bye' => [
            'sampai jumpa', 'dadah', 'bye', 'sudah cukup', 'cukup', 'selesai',
            'oke selesai', 'tidak jadi',
        ],
        'test' => [
            'test', 'tes', 'testing', 'coba', 'ping', 'cek',
        ],
    ];

    /** Masukan sependek ini tidak mungkin memuat pertanyaan. */
    private const MIN_QUESTION_LENGTH = 3;

    /** Panjang minimal sebelum satu kata boleh dicurigai sebagai ketikan asal. */
    private const GIBBERISH_MIN_LENGTH = 4;

    /**
     * Baris papan ketik QWERTY. Masukan yang seluruhnya potongan berurutan dari
     * salah satu baris ini — "asdfgh", "qwerty", "zxcvbn" — adalah jari yang
     * diseret, bukan kata. Aturan vokal tidak menangkapnya (semuanya punya
     * vokal), tapi aturan ini menangkapnya tanpa risiko: tidak ada kata
     * Indonesia yang tersusun dari tombol bersebelahan sepanjang empat huruf.
     */
    private const KEYBOARD_ROWS = ['qwertyuiop', 'asdfghjkl', 'zxcvbnm'];

    /** @var array<string, string> */
    private const REPLIES = [
        'greeting' => 'Halo! Saya EVA, asisten Helpdesk ADHI. Ada yang bisa saya bantu — misalnya akses aplikasi, kendala perangkat kerja, atau permohonan layanan?',
        'howareyou' => 'Baik, terima kasih sudah bertanya! Saya siap membantu. Ada kendala layanan yang sedang Anda hadapi?',
        'identity' => 'Saya EVA, asisten virtual Helpdesk ADHI. Saya menjawab dari SOP dan panduan resmi yang sudah terkumpul di Knowledge Base, dan kalau jawabannya belum ada, saya bantu siapkan draf tiket untuk tim yang menangani.',
        'capability' => 'Saya bisa membantu soal layanan Helpdesk ADHI: prosedur akses aplikasi (SAP, ADELE, ARISE), reset kata sandi, kendala perangkat dan jaringan, sampai cara mengajukan permohonan. Di luar contoh itu pun silakan tanyakan — kalau jawabannya belum ada di panduan, saya siapkan draf tiketnya.',
        'thanks' => 'Sama-sama! Kalau ada kendala lain, silakan tanyakan lagi.',
        'bye' => 'Baik, terima kasih. Kalau nanti ada kendala layanan, saya siap membantu lagi.',
        'test' => 'Halo! Saya menerima pesan Anda dengan baik. Silakan tanyakan kendala layanan yang sedang Anda hadapi.',
        'ack' => 'Baik. Kalau ada yang ingin ditanyakan, silakan sampaikan.',
        'noise' => 'Maaf, saya belum menangkap maksudnya. Bisa dituliskan lebih lengkap? Misalnya "cara reset password SAP" atau "printer tidak bisa mencetak".',
    ];

    /** Balasan basa-basi, atau null kalau ini pertanyaan sungguhan. */
    public function balasan(string $question): ?string
    {
        $normalized = $this->normalize($question);

        // Kosong setelah dinormalkan berarti isinya cuma tanda baca ("???").
        if ($normalized === '') {
            return self::REPLIES['noise'];
        }

        foreach (self::PATTERNS as $intent => $phrases) {
            if (in_array($normalized, $phrases, true)) {
                return self::REPLIES[$intent];
            }
        }

        return $this->isNoise($normalized) ? self::REPLIES['noise'] : null;
    }

    /**
     * Masukan yang bukan pertanyaan sama sekali: satu-dua huruf, angka polos,
     * atau tombol keyboard yang diacak.
     *
     * Diperiksa SESUDAH daftar frasa, supaya "ok" dan "hi" tetap dibalas
     * sebagai basa-basi, bukan sebagai omong kosong.
     *
     * Sengaja dibuat pelit. Menyaring terlalu rajin jauh lebih berbahaya
     * daripada meloloskan satu-dua sampah: pertanyaan sungguhan yang salah
     * dituduh omong kosong akan hilang dari Unanswered Questions, dan celah
     * materinya tidak pernah ketahuan. Karena itu singkatan tanpa vokal yang
     * pendek — "VPN", "PC", "SAP" — tetap lolos sebagai pertanyaan.
     */
    private function isNoise(string $normalized): bool
    {
        if (mb_strlen($normalized) < self::MIN_QUESTION_LENGTH) {
            return true;
        }

        // Angka polos tanpa satu pun huruf.
        if (preg_match('/^[\d\s]+$/u', $normalized) === 1) {
            return true;
        }

        $words = explode(' ', $normalized);

        if (count($words) !== 1 || mb_strlen($words[0]) < self::GIBBERISH_MIN_LENGTH) {
            return false;
        }

        return preg_match('/[aeiou]/u', $words[0]) !== 1
            || $this->isKeyboardRun($words[0]);
    }

    /** "asdfgh" dan "qwerty": potongan berurutan dari satu baris papan ketik. */
    private function isKeyboardRun(string $word): bool
    {
        foreach (self::KEYBOARD_ROWS as $row) {
            if (str_contains($row, $word) || str_contains(strrev($row), $word)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Menyamakan bentuk sebelum dibandingkan: huruf kecil, tanda baca dibuang,
     * spasi dirapatkan, dan sapaan berulang ("haloo", "hyy") dipendekkan.
     */
    private function normalize(string $question): string
    {
        $text = mb_strtolower(trim($question));
        $text = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $text) ?? '';
        $text = preg_replace('/\s+/u', ' ', $text) ?? '';
        $text = trim($text);

        // "haloooo" → "halo", "hyyy" → "hy". Huruf yang diulang lebih dari dua
        // kali tidak pernah bermakna di kata Indonesia.
        $text = preg_replace('/(.)\1{2,}/u', '$1', $text) ?? $text;

        return $this->stripParticles($text);
    }

    /**
     * Melepas partikel di ujung kalimat: "makasih ya", "halo kak", "terima
     * kasih pak" adalah sapaan yang sama dengan bentuk polosnya.
     *
     * Hanya dari UJUNG, dan hanya kata yang memang tidak pernah membawa makna
     * sendiri. Membuang kata di tengah akan membuat "cara tes koneksi" ikut
     * menyusut jadi "tes" — pertanyaan sungguhan yang tiba-tiba dibalas sapaan.
     */
    private function stripParticles(string $text): string
    {
        $particles = ['ya', 'yaa', 'yah', 'dong', 'deh', 'sih', 'kak', 'min', 'pak', 'bu', 'bang', 'eva', 'nih', 'aja', 'banget'];

        $words = $text === '' ? [] : explode(' ', $text);

        while (count($words) > 1 && in_array(end($words), $particles, true)) {
            array_pop($words);
        }

        return implode(' ', $words);
    }
}
