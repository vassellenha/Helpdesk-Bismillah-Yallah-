<?php

declare(strict_types=1);

namespace App\Services\Knowledge;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Lapisan percakapan EVA lewat OpenAI.
 *
 * Dua pekerjaan, satu kelas, karena keduanya persoalan yang sama: memahami
 * BAHASA percakapan, bukan memasok isi jawaban. Keduanya juga berbagi seluruh
 * plumbing yang sama — kunci, cooldown, pembersihan format, dan aturan "gagal
 * berarti kembali ke perilaku lama".
 *
 * Penjaga yang menentukan boleh-tidaknya lapisan ini hidup:
 *
 * 1. TIDAK PERNAH JADI SUMBER FAKTA. Prompt di bawah melarang keras memberi
 *    keterangan tentang layanan/prosedur ADHI. Hasil standalone() hanya dipakai
 *    sebagai KATA KUNCI PENCARIAN ke KB, dan hasil chat() hanya dipakai untuk
 *    basa-basi yang memang tidak menyentuh KB sama sekali.
 * 2. GAGAL = KEMBALI KE ASAL, BUKAN ERROR. Setiap jalan keluar memulangkan
 *    nilai yang bisa dipakai, sehingga EVA tidak pernah berhenti melayani
 *    karena OpenAI sedang bermasalah.
 * 3. HEMAT PANGGILAN. Pertanyaan pertama sebuah percakapan tidak punya konteks
 *    untuk diurai, jadi standalone() tidak menelepon siapa pun — dan itu justru
 *    kasus yang paling sering terjadi.
 */
final class OpenAiConversationEngine implements ConversationEngine
{
    private const ENDPOINT = 'https://api.openai.com/v1/chat/completions';

    private const REWRITE_PROMPT = <<<'TXT'
    Anda menulis ulang pertanyaan karyawan agar bisa dicari di basis pengetahuan helpdesk.

    Anda diberi transkrip percakapan sebelumnya dan satu pertanyaan terbaru. Tugas Anda HANYA satu: mengubah pertanyaan terbaru itu menjadi pertanyaan yang berdiri sendiri, dengan mengganti kata rujukan ("itu", "tadi", "-nya", "kalau gagal") memakai kata benda yang jelas dari percakapan sebelumnya.

    Aturan mutlak:
    - JANGAN menjawab pertanyaannya. Keluarkan pertanyaannya saja.
    - JANGAN menambah informasi, syarat, atau istilah teknis yang tidak disebut di percakapan.
    - Pertahankan persis nama aplikasi, menu, unit, dan angka yang sudah disebut.
    - Bila pertanyaan terbaru sudah berdiri sendiri, keluarkan kembali apa adanya tanpa diubah.
    - Keluarkan satu baris saja, tanpa tanda kutip, tanpa awalan apa pun.
    - Bahasa Indonesia.
    TXT;

    private const CHAT_PROMPT = <<<'TXT'
    Anda adalah EVA, asisten Helpdesk internal PT Adhi Karya. Saat ini karyawan sedang menyapa atau berbasa-basi, BUKAN menanyakan kendala layanan.

    Balas dengan hangat dan wajar, seperti rekan kerja yang ramah. Variasikan kalimat Anda — jangan memakai kalimat yang sama berulang kali.

    Aturan mutlak:
    - JANGAN memberi keterangan apa pun tentang aplikasi, prosedur, SOP, batas waktu, atau layanan ADHI. Anda sedang berbasa-basi, bukan menjawab pertanyaan layanan.
    - JANGAN mengarang kemampuan yang tidak Anda miliki.
    - Maksimal dua kalimat pendek.
    - Tutup dengan mengajak karyawan menyampaikan kendalanya, kecuali dia memang sedang berpamitan atau berterima kasih.
    - Teks polos. Tanpa Markdown, tanpa emotikon berlebihan.
    - Bahasa Indonesia.

    Sebuah contoh balasan yang sesuai maksudnya diberikan sebagai acuan nada. Anda boleh menyusun kalimat sendiri, asalkan maksudnya sama.
    TXT;

    private const CONVERSE_PROMPT = <<<'TXT'
    Anda adalah EVA, asisten Helpdesk internal PT Adhi Karya. Basis pengetahuan tidak menemukan jawaban untuk kalimat karyawan di bawah. Sebelum EVA menyerah, tentukan dulu: apakah kalimat itu memang PERTANYAAN LAYANAN?

    Yang BUKAN pertanyaan layanan, misalnya:
    - kalimat pembuka atau pengantar ("saya memiliki pertanyaan lagi", "boleh tanya sesuatu?")
    - konfirmasi atau tanggapan ("oke", "siap", "berarti begitu ya", "sebentar")
    - komentar, keluh kesah umum, atau candaan yang tidak menyebut kendala layanan apa pun
    - pertanyaan tentang KEMAMPUAN ANDA SENDIRI — apa yang bisa Anda lakukan, apakah Anda bisa membantu, apakah Anda bisa mengarahkan atau menyiapkan draf tiket, apakah Anda bisa mencarikan panduan

    Bedakan dengan cermat, karena inilah yang paling sering keliru:
    - "apakah kamu bisa bantu saya membuat tiket?" → menanyakan KEMAMPUAN ANDA. Bukan pertanyaan layanan. Jawablah.
    - "bagaimana prosedur pengajuan akun SAP?" → menanyakan PROSEDUR ADHI. Ini pertanyaan layanan.
    Adanya kata "tiket", "akun", atau nama aplikasi TIDAK dengan sendirinya membuat kalimat menjadi pertanyaan layanan. Yang menentukan adalah apa yang ditanyakan: kemampuan Anda, atau prosedur perusahaan.

    Bila kalimat itu BUKAN pertanyaan layanan: balas dengan hangat dan wajar, lalu ajak karyawan menyampaikan kendalanya. Maksimal dua kalimat pendek.

    Aturan mutlak:
    - JANGAN memberi keterangan apa pun tentang aplikasi, prosedur, SOP, batas waktu, atau layanan ADHI. Anda sedang menanggapi percakapan, bukan menjawab pertanyaan layanan.
    - Anda BOLEH menjelaskan diri Anda sendiri: bahwa Anda mencarikan panduan dari materi resmi helpdesk, dan bila panduannya belum ada Anda bisa menyiapkan draf tiket agar karyawan tinggal memeriksa dan mengirim. Itu tentang Anda, bukan tentang prosedur ADHI.
    - JANGAN menjanjikan tindakan yang tidak bisa Anda lakukan.
    - Teks polos, tanpa Markdown.
    - Bahasa Indonesia.

    Bila kalimat itu TERNYATA pertanyaan layanan yang sungguhan — sekecil apa pun petunjuknya — jawab HANYA dengan satu kata berikut, tanpa tambahan apa pun:
    BUKAN_BASA_BASI
    TXT;

    /** Balasan yang menyatakan "ini pertanyaan layanan sungguhan". */
    private const NOT_CHITCHAT = 'BUKAN_BASA_BASI';

    private const RELEVANCE_PROMPT = <<<'TXT'
    Anda memeriksa apakah sebuah materi helpdesk benar-benar MENJAWAB pertanyaan karyawan.

    Yang dinilai adalah kesamaan MAKSUD, bukan kesamaan kata. Materi yang kebetulan menyebut istilah yang sama tetapi membahas hal lain TIDAK menjawab.

    Contoh yang TIDAK menjawab:
    - Pertanyaan tentang kemampuan asisten, dijawab materi prosedur layanan.
    - Pertanyaan tentang aplikasi A, dijawab materi aplikasi B.
    - Pertanyaan "bagaimana cara X", dijawab materi yang hanya menyinggung X sambil lalu.

    Jawab HANYA dengan satu kata, tanpa tambahan apa pun:
    - NYAMBUNG bila materi itu memang memuat jawaban pertanyaannya.
    - TIDAK_NYAMBUNG bila tidak.
    TXT;

    /** Balasan yang menyatakan materi tidak menjawab pertanyaannya. */
    private const NOT_RELEVANT = 'TIDAK_NYAMBUNG';

    /** Materi dipangkas sebelum dikirim — penilaian relevansi tidak butuh isi utuh. */
    private const RELEVANCE_MATERIAL_CHARS = 1200;

    /** Balasan basa-basi yang lebih panjang dari ini hampir pasti sudah menjawab sesuatu. */
    private const MAX_CHAT_LENGTH = 320;

    /** @param array<string,mixed> $config config('services.openai') */
    public function __construct(private readonly array $config) {}

    public function standalone(string $question, array $memory): string
    {
        // Pertanyaan pertama tidak punya rujukan untuk diurai. Ini jalur yang
        // paling sering dilewati, dan menelepon OpenAI di sini hanya menambah
        // satu detik ke setiap percakapan demi hasil yang sudah pasti sama.
        if ($memory === []) {
            return $question;
        }

        $transcript = ConversationMemory::transcript($memory);

        if ($transcript === '') {
            return $question;
        }

        $rewritten = $this->ask(
            self::REWRITE_PROMPT,
            "Percakapan sebelumnya:\n{$transcript}\n\nPertanyaan terbaru:\n{$question}",
            $this->rewriteBudget($question),
        );

        if ($rewritten === null) {
            return $question;
        }

        if (! $this->isPlausibleRewrite($rewritten, $question)) {
            // Dicatat, bukan dibuang diam-diam. Pembuangan tanpa jejak membuat
            // fitur ini tampak "menyala tapi tidak pernah bekerja" — persis
            // pelajaran yang sudah dibayar sekali di OpenAiParaphraser.
            Log::warning('EVA penguraian pertanyaan dibuang karena tidak wajar.', [
                'question_length' => mb_strlen($question),
                'rewritten_length' => mb_strlen($rewritten),
                'multiline' => str_contains($rewritten, "\n"),
            ]);

            return $question;
        }

        return $rewritten;
    }

    public function chat(string $question, array $memory, string $fallback): string
    {
        $transcript = ConversationMemory::transcript($memory);

        $context = $transcript === ''
            ? "Sapaan karyawan:\n{$question}"
            : "Percakapan sebelumnya:\n{$transcript}\n\nSapaan karyawan:\n{$question}";

        $reply = $this->ask(
            self::CHAT_PROMPT,
            $context."\n\nAcuan nada:\n".$fallback,
            240,
        );

        if ($reply === null || $reply === '' || mb_strlen($reply) > self::MAX_CHAT_LENGTH) {
            return $fallback;
        }

        return $reply;
    }

    public function converse(string $question, array $memory): ?string
    {
        $transcript = ConversationMemory::transcript($memory);

        $context = $transcript === ''
            ? "Kalimat karyawan:\n{$question}"
            : "Percakapan sebelumnya:\n{$transcript}\n\nKalimat karyawan:\n{$question}";

        $reply = $this->ask(self::CONVERSE_PROMPT, $context, 240);

        if ($reply === null) {
            return null;
        }

        // Sentinel bisa datang bersih atau terbungkus kalimat pengantar. Yang
        // diperiksa keberadaannya, bukan kesamaan persis — model yang menambah
        // satu titik di ujung tidak boleh membuat pertanyaan layanan sungguhan
        // dibalas basa-basi.
        if (str_contains($reply, self::NOT_CHITCHAT)) {
            return null;
        }

        if (mb_strlen($reply) > self::MAX_CHAT_LENGTH) {
            Log::warning('EVA balasan percakapan dibuang karena kepanjangan.', [
                'length' => mb_strlen($reply),
            ]);

            return null;
        }

        return $reply;
    }

    public function materialAnswers(string $question, string $title, string $material): bool
    {
        $verdict = $this->ask(
            self::RELEVANCE_PROMPT,
            "Pertanyaan karyawan:\n{$question}\n\nJudul materi:\n{$title}\n\nIsi materi:\n"
                .mb_substr($material, 0, self::RELEVANCE_MATERIAL_CHARS),
            16,
        );

        // Ragu-ragu berarti LOLOS. Layanan yang mati tidak boleh diam-diam
        // membungkam materi yang sebenarnya benar — itu menukar satu kesalahan
        // dengan kesalahan yang lebih buruk.
        if ($verdict === null) {
            return true;
        }

        if (str_contains($verdict, self::NOT_RELEVANT)) {
            Log::info('EVA menolak mengutip materi yang tidak menjawab.', [
                'title' => $title,
            ]);

            return false;
        }

        return true;
    }

    /**
     * Panggilan bersama untuk kedua pekerjaan.
     *
     * @return string|null null berarti "pakai nilai asal" — pemanggil yang
     *                     memutuskan nilai asal itu apa
     */
    private function ask(string $system, string $user, int $budget): ?string
    {
        if (! $this->isConfigured() || OpenAiCooldown::active()) {
            return null;
        }

        try {
            $response = Http::withToken((string) ($this->config['key'] ?? ''))
                ->timeout((int) ($this->config['timeout'] ?? 8))
                ->asJson()
                ->post(self::ENDPOINT, [
                    'model' => (string) ($this->config['model'] ?? 'gpt-4o-mini'),

                    // Lebih tinggi dari parafrase (0.2) karena di sinilah
                    // variasi memang diinginkan: sapaan yang identik kata demi
                    // kata persis itulah yang membuat EVA terasa seperti mesin.
                    'temperature' => 0.7,

                    // BUKAN 'max_tokens' — model generasi baru menolak nama
                    // lama itu dengan 400. Lihat catatan sama di OpenAiParaphraser.
                    'max_completion_tokens' => $budget,
                    'messages' => [
                        ['role' => 'system', 'content' => $system],
                        ['role' => 'user', 'content' => $user],
                    ],
                ]);

            if ($response->failed()) {
                Log::warning('EVA lapisan percakapan ditolak OpenAI.', [
                    'status' => $response->status(),
                    'error' => $response->json('error.message'),
                ]);

                if (OpenAiCooldown::shouldPauseFor($response->status())) {
                    OpenAiCooldown::start();
                }

                return null;
            }

            $text = PlainAnswer::bersihkan((string) $response->json('choices.0.message.content', ''));

            return $text === '' ? null : $text;
        } catch (Throwable $e) {
            Log::warning('EVA lapisan percakapan gagal dipanggil: '.$e->getMessage());
            OpenAiCooldown::start();

            return null;
        }
    }

    /**
     * Penjaga hasil penulisan ulang.
     *
     * Yang ditangkap bukan salah ketik, melainkan model yang MENJAWAB alih-alih
     * menulis ulang — gejalanya selalu sama: hasilnya jauh lebih panjang dari
     * pertanyaannya, atau berisi beberapa baris langkah. Pertanyaan hasil urai
     * wajar tumbuh, tapi tidak berlipat ganda.
     */
    private function isPlausibleRewrite(string $rewritten, string $question): bool
    {
        if (str_contains($rewritten, "\n")) {
            return false;
        }

        $limit = max(200, mb_strlen($question) * 3);

        return mb_strlen($rewritten) <= $limit;
    }

    private function rewriteBudget(string $question): int
    {
        return (int) min(400, max(120, mb_strlen($question) * 3));
    }

    private function isConfigured(): bool
    {
        return ($this->config['key'] ?? '') !== '';
    }
}
