<?php

declare(strict_types=1);

namespace App\Services\Knowledge;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Parafrase lewat OpenAI Chat Completions.
 *
 * Tiga hal yang membuat kelas ini lebih panjang dari sekadar satu panggilan
 * HTTP, dan ketiganya bukan hiasan:
 *
 * 1. GAGAL = TEKS ASLI. Jaringan kantor putus, kuota habis, kunci dicabut —
 *    tidak satu pun boleh membuat EVA berhenti menjawab. Semua jalur gagal
 *    berakhir mengembalikan $answer, dan dicatat ke log supaya diamnya
 *    ketahuan, bukan disembunyikan.
 * 2. HASILNYA DIPERIKSA. Model diminta memparafrase, tapi tidak ada yang
 *    menjamin ia patuh. Jawaban yang kehilangan angka, memanjang dua kali
 *    lipat, atau balas kosong ditolak — lihat isFaithful().
 * 3. HANYA YANG BERHASIL YANG DI-CACHE. Menyimpan hasil fallback berarti satu
 *    gangguan jaringan sesaat membekukan teks mentah selama masa cache.
 *
 * Pertanyaan penanya SENGAJA tidak ikut dikirim. Model yang melihat pertanyaan
 * cenderung ikut menjawabnya — menambah langkah yang tidak ada di SOP — dan
 * hasilnya jadi berbeda-beda untuk jawaban KB yang sama, sehingga tidak bisa
 * di-cache. Yang dikerjakan di sini murni menulis ulang satu potong teks.
 */
final class OpenAiParaphraser implements AnswerParaphraser
{
    private const ENDPOINT = 'https://api.openai.com/v1/chat/completions';

    /**
     * Ikut jadi kunci cache: mengubah instruksi di bawah otomatis membuat
     * hasil lama tidak terpakai, tanpa perlu membersihkan cache manual.
     */
    private const PROMPT_VERSION = 'v3';

    /**
     * Versi pertama memuat kalimat "kalau teksnya sudah jelas, kembalikan apa
     * adanya". Hasilnya benar tapi tidak berguna: artikel KB yang memang sudah
     * ditulis rapi dikembalikan utuh, sehingga di layar fiturnya tampak tidak
     * pernah bekerja. Sekarang menyusun ulang itu wajib — yang dijaga bukan
     * "seberapa banyak berubah", melainkan tidak ada fakta yang bergeser, dan
     * itu ditegakkan isFaithful(), bukan oleh sopan-santun prompt.
     */
    private const SYSTEM_PROMPT = <<<'TXT'
    Anda adalah asisten helpdesk internal. Tulis ulang jawaban dari dokumen berikut dengan kalimat Anda sendiri, seolah menjelaskan langsung kepada rekan kerja — bukan menyalin isi dokumen.

    Aturan mutlak:
    - JANGAN menambah informasi, langkah, saran, atau syarat yang tidak ada di teks asli.
    - JANGAN menghapus atau menggabungkan langkah.
    - Pertahankan persis semua angka, nama aplikasi, nama menu, nama unit, kode form, dan istilah teknis.
    - Pertahankan urutan langkah dan format daftar bila ada.
    - Jangan menyapa, jangan menutup dengan tawaran bantuan, jangan membuka dengan "Tentu" atau "Baik".
    - Panjangnya harus sebanding dengan teks asli, jangan diringkas dan jangan dipanjangkan.
    - JANGAN memakai format Markdown: tanpa **tebal**, tanpa *miring*, tanpa # judul, tanpa `backtick`. Tulis teks polos.

    Balas HANYA dengan teks hasil tulis ulang, dalam Bahasa Indonesia.
    TXT;

    /** Ambang wajar panjang hasil terhadap teks asli. */
    private const MAX_GROWTH = 1.6;

    private const MIN_SHRINK = 0.6;

    /** Jawaban KB jarang berubah; yang berubah menghasilkan kunci cache baru. */
    private const CACHE_DAYS = 30;

    /** Teks sependek ini tidak ada yang bisa diperbaiki — hemat satu panggilan. */
    private const MIN_LENGTH = 40;

    /** @param array<string,mixed> $config config('services.openai') */
    public function __construct(private readonly array $config) {}

    public function parafrase(string $answer): string
    {
        $original = trim($answer);

        if (mb_strlen($original) < self::MIN_LENGTH) {
            return $answer;
        }

        $key = $this->cacheKey($original);
        $cached = Cache::get($key);

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $rewritten = $this->ask($original);

        if ($rewritten !== $original) {
            Cache::put($key, $rewritten, now()->addDays(self::CACHE_DAYS));
        }

        return $rewritten;
    }

    /** Selalu mengembalikan teks yang bisa dipakai — aslinya kalau apa pun meleset. */
    private function ask(string $original): string
    {
        // OpenAI baru saja menolak panggilan lain. Menelepon lagi sekarang
        // hanya menambah satu batas waktu tunggu ke jawaban yang ujungnya
        // diambil dari teks asli juga.
        if (OpenAiCooldown::active()) {
            return $original;
        }

        try {
            $response = Http::withToken((string) ($this->config['key'] ?? ''))
                ->timeout((int) ($this->config['timeout'] ?? 8))
                ->asJson()
                ->post(self::ENDPOINT, [
                    'model' => (string) ($this->config['model'] ?? 'gpt-4o-mini'),
                    'temperature' => 0.2,

                    // BUKAN 'max_tokens'. Model generasi baru menolak nama lama
                    // itu dengan 400, dan gejalanya menipu: EVA tetap menjawab
                    // (jatuh ke teks asli) sehingga fiturnya tampak "menyala
                    // tapi tidak pernah mengubah apa pun". Nama baru ini juga
                    // diterima model lama, jadi tidak perlu bercabang.
                    'max_completion_tokens' => $this->tokenBudget($original),
                    'messages' => [
                        ['role' => 'system', 'content' => self::SYSTEM_PROMPT],
                        ['role' => 'user', 'content' => $original],
                    ],
                ]);

            if ($response->failed()) {
                Log::warning('EVA parafrase ditolak OpenAI.', [
                    'status' => $response->status(),
                    // Badan respons OpenAI memuat pesan error, bukan isi SOP.
                    'error' => $response->json('error.message'),
                ]);

                if (OpenAiCooldown::shouldPauseFor($response->status())) {
                    OpenAiCooldown::start();
                }

                return $original;
            }

            // Dibersihkan SEBELUM diperiksa, supaya sisa tanda format tidak
            // ikut terhitung sebagai selisih panjang di isFaithful().
            $rewritten = PlainAnswer::bersihkan((string) $response->json('choices.0.message.content', ''));

            if (! $this->isFaithful($rewritten, $original)) {
                Log::warning('EVA parafrase dibuang karena menyimpang dari teks asli.', [
                    'original_length' => mb_strlen($original),
                    'rewritten_length' => mb_strlen($rewritten),
                ]);

                return $original;
            }

            return $rewritten;
        } catch (Throwable $e) {
            Log::warning('EVA parafrase gagal dipanggil: '.$e->getMessage());
            OpenAiCooldown::start();

            return $original;
        }
    }

    /**
     * Penjaga terakhir sebelum teks model sampai ke karyawan.
     *
     * Pemeriksaan angka yang paling menentukan: SOP penuh batas waktu, nomor
     * langkah, dan kode aplikasi. Sengaja ketat sebelah — "5 hari" yang ditulis
     * ulang jadi "lima hari" ikut ditolak. Kehilangan satu parafrase yang
     * sebenarnya benar jauh lebih murah daripada meloloskan satu angka yang
     * bergeser.
     */
    private function isFaithful(string $rewritten, string $original): bool
    {
        if ($rewritten === '') {
            return false;
        }

        $length = mb_strlen($rewritten);
        $originalLength = mb_strlen($original);

        if ($length > $originalLength * self::MAX_GROWTH || $length < $originalLength * self::MIN_SHRINK) {
            return false;
        }

        preg_match_all('/\d+/', $original, $matches);

        foreach (array_unique($matches[0]) as $number) {
            if (! str_contains($rewritten, $number)) {
                return false;
            }
        }

        return true;
    }

    /** Cukup untuk menulis ulang, tidak cukup untuk mengarang bab baru. */
    private function tokenBudget(string $original): int
    {
        return (int) min(1500, max(200, mb_strlen($original)));
    }

    private function cacheKey(string $original): string
    {
        return 'eva:paraphrase:'.self::PROMPT_VERSION.':'
            .($this->config['model'] ?? 'default').':'
            .hash('sha256', $original);
    }
}
