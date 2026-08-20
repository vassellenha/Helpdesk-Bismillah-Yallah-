<?php

declare(strict_types=1);

namespace Tests\Feature\Knowledge;

use App\Models\Knowledge\AnswerLog;
use App\Services\Knowledge\ConversationEngine;
use App\Services\Knowledge\EvaReply;
use App\Services\Knowledge\KnowledgeSearch;
use App\Services\Knowledge\NoConversationEngine;
use App\Services\Knowledge\OpenAiConversationEngine;
use App\Services\Knowledge\OpenAiCooldown;
use App\Services\Knowledge\SubjectMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Kalimat percakapan yang BUKAN pertanyaan layanan.
 *
 * "saya memiliki pertanyaan lagi", "boleh tanya sesuatu?", "sebentar ya" —
 * semuanya bukan sapaan menurut daftar pola tetap SmallTalkDetector, jadi
 * selama ini jatuh ke jalur pencarian, gagal menemukan apa pun, lalu dibalas
 * "belum menemukan jawaban" beserta tawaran draf tiket. Dua akibatnya sama
 * buruknya: karyawan yang baru hendak bertanya sudah disodori formulir tiket,
 * dan kalimat yang tidak mungkin dijadikan materi menumpuk di daftar kerja
 * Unanswered Questions.
 *
 * Daftar pola tidak bisa mengejar ini — bentuk kalimat pembuka tak terbatas.
 * Yang dijaga di sini adalah jalan keluarnya: SEBELUM menyerah, EVA bertanya ke
 * model apakah kalimat itu memang pertanyaan layanan.
 *
 * Urutannya menentukan dan ikut diuji: pemeriksaan ini hanya boleh terjadi
 * SESUDAH pencarian KB gagal. Pertanyaan sungguhan harus selalu mendapat
 * jawaban KB lebih dulu.
 */
final class EvaConverseFallbackTest extends TestCase
{
    use RefreshDatabase;

    private const FILLER = 'saya memiliki pertanyaan lagi';

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        OpenAiCooldown::forget();
        SubjectMatcher::forget();
    }

    private function endpoint(): string
    {
        return 'https://api.openai.com/v1/chat/completions';
    }

    private function engine(): OpenAiConversationEngine
    {
        return new OpenAiConversationEngine([
            'key' => 'kunci-uji',
            'model' => 'model-uji',
            'timeout' => 5,
        ]);
    }

    /** @return array<string,mixed> */
    private function openAiReply(string $text): array
    {
        return ['choices' => [['message' => ['content' => $text]]]];
    }

    /** Membuat EVA selalu gagal menemukan jawaban, supaya jalur menyerah yang teruji. */
    private function searchFindsNothing(): void
    {
        $this->app->bind(KnowledgeSearch::class, fn () => new class implements KnowledgeSearch
        {
            public function cari(string $pertanyaan, int $limit = 1): array
            {
                return [];
            }
        });
    }

    // ---- mesin percakapan --------------------------------------------------

    public function test_kalimat_pembuka_dibalas_percakapan(): void
    {
        Http::fake([$this->endpoint() => Http::response($this->openAiReply('Tentu, silakan sampaikan pertanyaan Anda.'))]);

        $this->assertSame(
            'Tentu, silakan sampaikan pertanyaan Anda.',
            $this->engine()->converse(self::FILLER, []),
        );
    }

    /**
     * Sentinel — sama pola dengan TIDAK_ADA_DI_KB milik OpenAiSynthesizer.
     * Model menyatakan ini pertanyaan layanan sungguhan, jadi EVA harus
     * meneruskan ke jalur menyerah yang biasa, bukan mengarang basa-basi.
     */
    public function test_pertanyaan_layanan_sungguhan_tidak_dibalas_percakapan(): void
    {
        Http::fake([$this->endpoint() => Http::response($this->openAiReply('BUKAN_BASA_BASI'))]);

        $this->assertNull($this->engine()->converse('vpn saya tidak bisa connect', []));
    }

    public function test_gagal_memanggil_model_tidak_menghasilkan_balasan(): void
    {
        Http::fake(fn () => throw new ConnectionException('timeout'));

        $this->assertNull($this->engine()->converse(self::FILLER, []));
    }

    public function test_balasan_kepanjangan_ditolak(): void
    {
        Http::fake([$this->endpoint() => Http::response($this->openAiReply(str_repeat('a', 400)))]);

        $this->assertNull($this->engine()->converse(self::FILLER, []));
    }

    public function test_saklar_mati_tidak_pernah_membalas_percakapan(): void
    {
        Http::fake();

        $this->assertNull((new NoConversationEngine)->converse(self::FILLER, []));

        Http::assertNothingSent();
    }

    // ---- perilaku EVA seutuhnya --------------------------------------------

    public function test_kalimat_pembuka_tidak_menawarkan_draf_tiket(): void
    {
        $this->searchFindsNothing();

        $this->app->bind(ConversationEngine::class, fn () => new class implements ConversationEngine
        {
            public function standalone(string $question, array $memory): string
            {
                return $question;
            }

            public function chat(string $question, array $memory, string $fallback): string
            {
                return $fallback;
            }

            public function converse(string $question, array $memory): ?string
            {
                return 'Tentu, silakan sampaikan pertanyaan Anda.';
            }

            public function materialAnswers(string $question, string $title, string $material): bool
            {
                return true;
            }
        });

        $reply = app(\App\Services\Knowledge\EvaResponder::class)->jawab(self::FILLER);

        $this->assertSame(EvaReply::TYPE_SMALL_TALK, $reply->type);
        $this->assertSame('Tentu, silakan sampaikan pertanyaan Anda.', $reply->text);
    }

    /**
     * Kalimat pembuka tidak mungkin dijadikan materi pengetahuan. Membiarkannya
     * masuk daftar kerja admin sebagai celah materi hanya mengotori antrean
     * dengan pekerjaan yang mustahil diselesaikan.
     */
    public function test_kalimat_pembuka_tidak_masuk_unanswered_questions(): void
    {
        $this->searchFindsNothing();

        $this->app->bind(ConversationEngine::class, fn () => new class implements ConversationEngine
        {
            public function standalone(string $question, array $memory): string
            {
                return $question;
            }

            public function chat(string $question, array $memory, string $fallback): string
            {
                return $fallback;
            }

            public function converse(string $question, array $memory): ?string
            {
                return 'Tentu, silakan sampaikan pertanyaan Anda.';
            }

            public function materialAnswers(string $question, string $title, string $material): bool
            {
                return true;
            }
        });

        app(\App\Services\Knowledge\EvaResponder::class)->jawab(self::FILLER);

        $this->assertDatabaseHas('kb_answer_logs', [
            'question' => self::FILLER,
            'outcome' => AnswerLog::OUTCOME_SMALL_TALK,
        ]);
        $this->assertDatabaseMissing('kb_answer_logs', [
            'question' => self::FILLER,
            'outcome' => AnswerLog::OUTCOME_NO_ANSWER,
        ]);
    }

    public function test_pertanyaan_layanan_yang_tak_terjawab_tetap_menawarkan_tiket(): void
    {
        $this->searchFindsNothing();

        // Mesin percakapan menyatakan ini pertanyaan sungguhan (null).
        $this->app->bind(ConversationEngine::class, fn () => new NoConversationEngine);

        $reply = app(\App\Services\Knowledge\EvaResponder::class)
            ->jawab('bagaimana prosedur penggantian kabel LAN di ruang rapat lantai lima');

        $this->assertSame(EvaReply::TYPE_NO_ANSWER, $reply->type);
    }
}
