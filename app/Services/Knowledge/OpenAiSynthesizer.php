<?php

declare(strict_types=1);

namespace App\Services\Knowledge;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Merangkum beberapa potongan Knowledge Base jadi satu jawaban, lewat OpenAI.
 *
 * Tiga penjaga yang menentukan boleh-tidaknya fitur ini hidup di helpdesk
 * perusahaan:
 *
 * 1. SENTINEL. Model diperintahkan menjawab persis "TIDAK_ADA_DI_KB" bila
 *    potongan yang diberikan tidak cukup. Tanpa jalan keluar yang eksplisit,
 *    model yang disodori dokumen selalu berusaha menjawab — dan jawaban yang
 *    dikarang dari dokumen yang tidak relevan justru terdengar paling
 *    meyakinkan. Sentinel inilah yang membuat EVA masih bisa berkata "belum
 *    ketemu" dan menawarkan tiket.
 * 2. ANGKA TIDAK BOLEH MUNCUL DARI UDARA. Setiap angka di jawaban wajib ada di
 *    salah satu potongan sumber. Ini menangkap bentuk halusinasi yang paling
 *    mahal di konteks SOP: batas waktu, jumlah hari, dan nomor form yang
 *    "kira-kira benar".
 * 3. GAGAL = NULL, BUKAN ERROR. Jaringan putus atau kuota habis membuat EVA
 *    kembali ke perilaku lamanya, bukan berhenti menjawab.
 *
 * Hasilnya sengaja TIDAK di-cache. Tidak seperti parafrase yang memetakan satu
 * teks tetap ke satu hasil, rangkuman bergantung pada pertanyaannya — dan dua
 * orang hampir tidak pernah mengetik pertanyaan yang persis sama.
 */
final class OpenAiSynthesizer implements KnowledgeSynthesizer
{
    private const ENDPOINT = 'https://api.openai.com/v1/chat/completions';

    /** Balasan persis ini berarti "potongan yang ada tidak cukup". */
    private const NOT_FOUND = 'TIDAK_ADA_DI_KB';

    private const SYSTEM_PROMPT = <<<'TXT'
    Anda adalah EVA, asisten Helpdesk internal PT Adhi Karya. Anda menjawab pertanyaan karyawan HANYA berdasarkan potongan dokumen resmi yang diberikan di bawah.

    Aturan mutlak:
    - Gunakan HANYA informasi dari potongan yang diberikan. Pengetahuan umum Anda tentang SAP, jaringan, atau IT pada umumnya TIDAK BOLEH dipakai.
    - Jika beberapa potongan saling melengkapi, gabungkan jadi satu jawaban yang runtut.
    - Jangan menambah syarat, langkah, batas waktu, atau nomor form yang tidak tertulis.
    - Pertahankan persis semua angka, nama aplikasi, nama menu, nama unit, dan kode form.
    - Jangan menyebut "potongan", "dokumen nomor 1", atau "berdasarkan teks di atas". Jawab langsung seperti menjelaskan kepada rekan kerja.
    - Jangan menyapa dan jangan menutup dengan tawaran bantuan.
    - Ringkas: maksimal satu paragraf pendek, atau daftar langkah bila prosedurnya bertahap.
    - JANGAN memakai format Markdown: tanpa **tebal**, tanpa *miring*, tanpa # judul, tanpa `backtick`. Tulis teks polos. Kalau berupa langkah, tulis satu langkah per baris.

    Jika potongan yang diberikan tidak memuat jawaban pertanyaannya — walau topiknya terasa berdekatan — jawab HANYA dengan satu kata berikut, tanpa tambahan apa pun:
    TIDAK_ADA_DI_KB

    Jawab dalam Bahasa Indonesia.
    TXT;

    /** @param array<string,mixed> $config config('services.openai') */
    public function __construct(private readonly array $config) {}

    /** @param list<array{title:string,text:string}> $passages */
    public function rangkum(string $question, array $passages): ?string
    {
        if ($passages === [] || OpenAiCooldown::active()) {
            return null;
        }

        try {
            $response = Http::withToken((string) ($this->config['key'] ?? ''))
                ->timeout((int) ($this->config['timeout'] ?? 8))
                ->asJson()
                ->post(self::ENDPOINT, [
                    'model' => (string) ($this->config['model'] ?? 'gpt-4o-mini'),
                    'temperature' => 0.2,
                    'max_completion_tokens' => 700,
                    'messages' => [
                        ['role' => 'system', 'content' => self::SYSTEM_PROMPT],
                        ['role' => 'user', 'content' => $this->userPrompt($question, $passages)],
                    ],
                ]);

            if ($response->failed()) {
                Log::warning('EVA rangkuman ditolak OpenAI.', [
                    'status' => $response->status(),
                    'error' => $response->json('error.message'),
                ]);

                if (OpenAiCooldown::shouldPauseFor($response->status())) {
                    OpenAiCooldown::start();
                }

                return null;
            }

            return $this->accept(PlainAnswer::bersihkan((string) $response->json('choices.0.message.content', '')), $passages);
        } catch (Throwable $e) {
            Log::warning('EVA rangkuman gagal dipanggil: '.$e->getMessage());
            OpenAiCooldown::start();

            return null;
        }
    }

    /** @param list<array{title:string,text:string}> $passages */
    private function accept(string $answer, array $passages): ?string
    {
        // Sentinel bisa datang sendirian atau terbungkus kalimat; dua-duanya
        // berarti hal yang sama.
        if ($answer === '' || str_contains($answer, self::NOT_FOUND)) {
            return null;
        }

        if (! $this->numbersAreGrounded($answer, $passages)) {
            Log::warning('EVA rangkuman dibuang: ada angka yang tidak ada di sumbernya.');

            return null;
        }

        return $answer;
    }

    /**
     * Setiap angka di jawaban harus bisa ditemukan di salah satu sumbernya.
     *
     * Angka satu digit dilewati: "1." pada penomoran langkah yang dibuat model
     * sendiri bukan klaim fakta, dan menolaknya akan membuang hampir semua
     * jawaban berbentuk daftar.
     *
     * @param  list<array{title:string,text:string}>  $passages
     */
    private function numbersAreGrounded(string $answer, array $passages): bool
    {
        $sources = implode(' ', array_column($passages, 'text'));

        preg_match_all('/\d+/', $answer, $matches);

        foreach (array_unique($matches[0]) as $number) {
            if (mb_strlen($number) > 1 && ! str_contains($sources, $number)) {
                return false;
            }
        }

        return true;
    }

    /** @param list<array{title:string,text:string}> $passages */
    private function userPrompt(string $question, array $passages): string
    {
        $blocks = [];

        foreach ($passages as $i => $passage) {
            $blocks[] = '--- Potongan '.($i + 1).' · '.$passage['title']." ---\n".$passage['text'];
        }

        return "Pertanyaan karyawan:\n".$question."\n\nPotongan dokumen resmi:\n".implode("\n\n", $blocks);
    }
}
