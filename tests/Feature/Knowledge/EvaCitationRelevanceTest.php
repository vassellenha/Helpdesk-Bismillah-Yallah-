<?php

declare(strict_types=1);

namespace Tests\Feature\Knowledge;

use App\Services\Knowledge\ConversationEngine;
use App\Services\Knowledge\EvaReply;
use App\Services\Knowledge\EvaResponder;
use App\Services\Knowledge\KnowledgeSearch;
use App\Services\Knowledge\NoConversationEngine;
use App\Services\Knowledge\OpenAiConversationEngine;
use App\Services\Knowledge\OpenAiCooldown;
use App\Services\Knowledge\SearchHit;
use App\Services\Knowledge\SubjectMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Kutipan materi harus dibuktikan, bukan diandaikan.
 *
 * Pencarian teks memulangkan materi yang bertumpang KATA, belum tentu
 * bertumpang MAKSUD. Pertanyaan "apakah EVA bisa mengarahkan saya untuk
 * pembuatan tiket" mengandung kata "tiket", dan SOP akun SAP juga menyebut
 * "formulir tiket" — cukup untuk lolos ambang keyakinan, sama sekali tidak
 * cukup untuk menjawabnya. Yang lahir dari situ adalah jawaban salah topik yang
 * JUSTRU tampak resmi karena membawa kutipan, dan karyawan tidak punya cara
 * membedakannya dari jawaban yang benar.
 *
 * Dua janji yang dijaga di sini:
 *   1. Materi yang tidak menjawab tidak pernah dikutip.
 *   2. Ragu-ragu berarti LOLOS — layanan yang mati tidak boleh membungkam
 *      materi yang sebenarnya benar.
 */
final class EvaCitationRelevanceTest extends TestCase
{
    use RefreshDatabase;

    private const QUESTION = 'apakah eva bisa mengarahkan saya untuk pembuatan tiket';

    private const TITLE = 'SOP-TI-01 Login dan Otorisasi SAP';

    private const MATERIAL = 'Ajukan permintaan lewat formulir tiket dengan kategori Access Request, subject Aktivasi/Unlock akun.';

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

    /** Pencarian selalu memulangkan satu materi dengan keyakinan yang ditentukan. */
    private function searchReturns(int $confidence): void
    {
        $hit = new SearchHit(
            sourceType: \App\Models\Knowledge\Article::class,
            sourceId: 1,
            title: self::TITLE,
            answer: self::MATERIAL,
            confidence: $confidence,
            catalogSubjectId: null,
        );

        $this->app->bind(KnowledgeSearch::class, fn () => new class($hit) implements KnowledgeSearch
        {
            public function __construct(private readonly SearchHit $hit) {}

            public function cari(string $pertanyaan, int $limit = 1): array
            {
                return [$this->hit];
            }
        });
    }

    /** @param bool|null $verdict null = jangan pernah dipanggil */
    private function engineSaying(?bool $verdict, ?string $converse = null): void
    {
        $this->app->bind(ConversationEngine::class, fn () => new class($verdict, $converse) implements ConversationEngine
        {
            public function __construct(
                private readonly ?bool $verdict,
                private readonly ?string $converse,
            ) {}

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
                return $this->converse;
            }

            public function materialAnswers(string $question, string $title, string $material): bool
            {
                if ($this->verdict === null) {
                    throw new \LogicException('Relevansi tidak boleh diperiksa pada keyakinan tinggi.');
                }

                return $this->verdict;
            }
        });
    }

    // ---- penilai relevansi -------------------------------------------------

    public function test_materi_yang_tidak_menjawab_ditolak(): void
    {
        Http::fake([$this->endpoint() => Http::response($this->openAiReply('TIDAK_NYAMBUNG'))]);

        $this->assertFalse($this->engine()->materialAnswers(self::QUESTION, self::TITLE, self::MATERIAL));
    }

    public function test_materi_yang_menjawab_diterima(): void
    {
        Http::fake([$this->endpoint() => Http::response($this->openAiReply('NYAMBUNG'))]);

        $this->assertTrue($this->engine()->materialAnswers('cara reset password SAP', self::TITLE, self::MATERIAL));
    }

    /** Ragu-ragu berarti lolos: layanan mati tidak boleh membungkam materi yang benar. */
    public function test_layanan_mati_membuat_materi_tetap_lolos(): void
    {
        Http::fake(fn () => throw new ConnectionException('timeout'));

        $this->assertTrue($this->engine()->materialAnswers(self::QUESTION, self::TITLE, self::MATERIAL));
    }

    public function test_saklar_mati_membuat_materi_tetap_lolos(): void
    {
        Http::fake();

        $this->assertTrue((new NoConversationEngine)->materialAnswers(self::QUESTION, self::TITLE, self::MATERIAL));

        Http::assertNothingSent();
    }

    // ---- perilaku EVA seutuhnya --------------------------------------------

    public function test_materi_tak_nyambung_tidak_dikutip_dan_dijawab_percakapan(): void
    {
        $this->searchReturns(70);
        $this->engineSaying(false, 'Bisa. Saya carikan panduannya dulu, dan kalau belum ada saya siapkan draf tiketnya.');

        $reply = app(EvaResponder::class)->jawab(self::QUESTION);

        $this->assertSame(EvaReply::TYPE_SMALL_TALK, $reply->type);
        $this->assertNull($reply->hit, 'Materi yang tidak menjawab tidak boleh ikut dikutip.');
    }

    public function test_materi_nyambung_tetap_dikutip(): void
    {
        $this->searchReturns(70);
        $this->engineSaying(true);

        $reply = app(EvaResponder::class)->jawab('cara reset password SAP');

        $this->assertSame(EvaReply::TYPE_ANSWER, $reply->type);
        $this->assertNotNull($reply->hit);
        $this->assertSame(self::TITLE, $reply->hit->title);
    }

    /**
     * Di atas ambang ragu, pencarian sudah cukup yakin. Memeriksanya lagi hanya
     * menambah satu panggilan ke jalur yang paling sering dilewati — test double
     * di bawah melempar bila itu terjadi.
     */
    public function test_keyakinan_tinggi_tidak_diperiksa_relevansinya(): void
    {
        $this->searchReturns(97);
        $this->engineSaying(null);

        $reply = app(EvaResponder::class)->jawab('cara reset password SAP');

        $this->assertSame(EvaReply::TYPE_ANSWER, $reply->type);
    }
}
