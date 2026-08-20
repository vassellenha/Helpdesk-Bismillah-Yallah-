<?php

declare(strict_types=1);

namespace Tests\Feature\Knowledge;

use App\Models\Knowledge\Conversation;
use App\Models\Knowledge\ConversationTurn;
use App\Models\User;
use App\Services\Knowledge\ConversationEngine;
use App\Services\Knowledge\ConversationMemory;
use App\Services\Knowledge\NoConversationEngine;
use App\Services\Knowledge\OpenAiConversationEngine;
use App\Services\Knowledge\OpenAiCooldown;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Lapisan percakapan EVA — ingatan, penguraian pertanyaan lanjutan, dan
 * basa-basi yang tidak kaleng.
 *
 * Yang dijaga di sini BUKAN kualitas kalimat yang dihasilkan model (itu tidak
 * bisa ditegakkan tes), melainkan tiga janji yang membuat lapisan ini aman
 * dinyalakan di helpdesk perusahaan:
 *
 *   1. Ingatannya terbatas dan urut — tidak menyeret percakapan lama.
 *   2. Setiap kegagalan model berakhir di perilaku EVA yang sekarang, bukan di
 *      pesan error. Karyawan tidak boleh kehilangan akses SOP karena OpenAI
 *      sedang bermasalah.
 *   3. Model tidak pernah jadi sumber fakta. Hasil penguraian hanya dipakai
 *      untuk MENCARI, dan hasil yang terlihat seperti jawaban dibuang.
 */
final class EvaConversationalTest extends TestCase
{
    use RefreshDatabase;

    private const QUESTION = 'kalau masih gagal gimana?';

    private const REWRITTEN = 'apa yang harus dilakukan bila reset password SAP masih gagal?';

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        OpenAiCooldown::forget();
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

    private function conversationWith(string ...$messages): Conversation
    {
        $user = User::factory()->create(['status' => 'active', 'helpdesk_access' => 'enabled']);

        $conversation = Conversation::create(['user_id' => $user->id]);

        foreach ($messages as $i => $message) {
            $conversation->turns()->create([
                'ordinal' => $i,
                'role' => $i % 2 === 0 ? ConversationTurn::ROLE_USER : ConversationTurn::ROLE_EVA,
                'message' => $message,
            ]);
        }

        return $conversation;
    }

    /** @return list<array{role: string, message: string}> */
    private function memory(): array
    {
        return [
            ['role' => ConversationTurn::ROLE_USER, 'message' => 'cara reset password SAP'],
            ['role' => ConversationTurn::ROLE_EVA, 'message' => 'Buka Portal SSO lalu pilih Ubah Password SAP.'],
        ];
    }

    // ---- ingatan percakapan ------------------------------------------------

    public function test_percakapan_kosong_tidak_menghasilkan_ingatan(): void
    {
        $this->assertSame([], ConversationMemory::recall(null));
        $this->assertSame([], ConversationMemory::recall($this->conversationWith()));
    }

    public function test_ingatan_urut_dari_yang_paling_lama(): void
    {
        $memory = ConversationMemory::recall($this->conversationWith('satu', 'dua', 'tiga'));

        $this->assertSame(['satu', 'dua', 'tiga'], array_column($memory, 'message'));
    }

    /** Percakapan panjang tidak boleh menyeret topik lama yang sudah selesai. */
    public function test_ingatan_dibatasi_pada_giliran_terakhir(): void
    {
        $conversation = $this->conversationWith('a', 'b', 'c', 'd', 'e', 'f', 'g', 'h');

        $memory = ConversationMemory::recall($conversation, 4);

        $this->assertSame(['e', 'f', 'g', 'h'], array_column($memory, 'message'));
    }

    public function test_ingatan_membawa_peran_tiap_giliran(): void
    {
        $memory = ConversationMemory::recall($this->conversationWith('tanya', 'jawab'));

        $this->assertSame(ConversationTurn::ROLE_USER, $memory[0]['role']);
        $this->assertSame(ConversationTurn::ROLE_EVA, $memory[1]['role']);
    }

    public function test_transkrip_menamai_kedua_pihak(): void
    {
        $transcript = ConversationMemory::transcript($this->memory());

        $this->assertStringContainsString('Karyawan: cara reset password SAP', $transcript);
        $this->assertStringContainsString('EVA: Buka Portal SSO', $transcript);
    }

    public function test_transkrip_kosong_untuk_ingatan_kosong(): void
    {
        $this->assertSame('', ConversationMemory::transcript([]));
    }

    // ---- penguraian pertanyaan lanjutan ------------------------------------

    public function test_pertanyaan_lanjutan_diurai_memakai_konteks(): void
    {
        Http::fake([$this->endpoint() => Http::response($this->openAiReply(self::REWRITTEN))]);

        $this->assertSame(
            self::REWRITTEN,
            $this->engine()->standalone(self::QUESTION, $this->memory()),
        );
    }

    /**
     * Pertanyaan pertama tidak punya rujukan untuk diurai. Menelepon OpenAI di
     * sini hanya menambah satu detik ke jalur yang paling sering dilewati.
     */
    public function test_pertanyaan_pertama_tidak_memanggil_openai(): void
    {
        Http::fake();

        $this->assertSame(self::QUESTION, $this->engine()->standalone(self::QUESTION, []));

        Http::assertNothingSent();
    }

    public function test_transkrip_percakapan_ikut_terkirim(): void
    {
        Http::fake([$this->endpoint() => Http::response($this->openAiReply(self::REWRITTEN))]);

        $this->engine()->standalone(self::QUESTION, $this->memory());

        Http::assertSent(function ($request) {
            $prompt = $request->data()['messages'][1]['content'];

            return str_contains($prompt, 'cara reset password SAP')
                && str_contains($prompt, self::QUESTION);
        });
    }

    public function test_permintaan_memakai_nama_parameter_batas_token_yang_didukung(): void
    {
        Http::fake([$this->endpoint() => Http::response($this->openAiReply(self::REWRITTEN))]);

        $this->engine()->standalone(self::QUESTION, $this->memory());

        Http::assertSent(function ($request) {
            $body = $request->data();

            return array_key_exists('max_completion_tokens', $body)
                && ! array_key_exists('max_tokens', $body)
                && $body['model'] === 'model-uji';
        });
    }

    // ---- gagal = kembali ke pertanyaan asal --------------------------------

    public function test_openai_menolak_maka_pertanyaan_asal_yang_dipakai(): void
    {
        Http::fake([$this->endpoint() => Http::response(['error' => ['message' => 'kuota habis']], 429)]);

        $this->assertSame(self::QUESTION, $this->engine()->standalone(self::QUESTION, $this->memory()));
    }

    public function test_jaringan_putus_maka_pertanyaan_asal_yang_dipakai(): void
    {
        Http::fake(fn () => throw new ConnectionException('timeout'));

        $this->assertSame(self::QUESTION, $this->engine()->standalone(self::QUESTION, $this->memory()));
    }

    public function test_balasan_kosong_maka_pertanyaan_asal_yang_dipakai(): void
    {
        Http::fake([$this->endpoint() => Http::response($this->openAiReply('   '))]);

        $this->assertSame(self::QUESTION, $this->engine()->standalone(self::QUESTION, $this->memory()));
    }

    public function test_tanpa_kunci_tidak_memanggil_openai(): void
    {
        Http::fake();

        $engine = new OpenAiConversationEngine(['key' => '', 'model' => 'model-uji']);

        $this->assertSame(self::QUESTION, $engine->standalone(self::QUESTION, $this->memory()));

        Http::assertNothingSent();
    }

    public function test_penolakan_pertama_menahan_panggilan_berikutnya(): void
    {
        Http::fake([$this->endpoint() => Http::response(['error' => ['message' => 'kuota habis']], 429)]);

        $this->engine()->standalone(self::QUESTION, $this->memory());

        $this->assertTrue(OpenAiCooldown::active());
    }

    /**
     * Model yang MENJAWAB alih-alih menulis ulang — gejalanya selalu sama:
     * hasilnya berlipat panjang, atau berisi beberapa baris langkah. Kalau
     * lolos, EVA akan mencari di KB memakai sebuah jawaban karangan sebagai
     * kata kunci, dan hasilnya tidak akan pernah cocok dengan apa pun.
     */
    public function test_hasil_yang_terlihat_seperti_jawaban_dibuang(): void
    {
        $jawaban = str_repeat('Buka Portal SSO lalu pilih menu Akun Saya. ', 20);

        Http::fake([$this->endpoint() => Http::response($this->openAiReply($jawaban))]);

        $this->assertSame(self::QUESTION, $this->engine()->standalone(self::QUESTION, $this->memory()));
    }

    public function test_hasil_berupa_daftar_langkah_dibuang(): void
    {
        Http::fake([$this->endpoint() => Http::response($this->openAiReply("Langkah 1: buka SSO\nLangkah 2: pilih menu"))]);

        $this->assertSame(self::QUESTION, $this->engine()->standalone(self::QUESTION, $this->memory()));
    }

    // ---- basa-basi ---------------------------------------------------------

    public function test_sapaan_dibalas_kalimat_dari_model(): void
    {
        Http::fake([$this->endpoint() => Http::response($this->openAiReply('Halo! Ada yang bisa saya bantu hari ini?'))]);

        $this->assertSame(
            'Halo! Ada yang bisa saya bantu hari ini?',
            $this->engine()->chat('halo', [], 'balasan kaleng'),
        );
    }

    public function test_acuan_nada_ikut_terkirim(): void
    {
        Http::fake([$this->endpoint() => Http::response($this->openAiReply('Halo!'))]);

        $this->engine()->chat('halo', [], 'balasan kaleng');

        Http::assertSent(fn ($request) => str_contains($request->data()['messages'][1]['content'], 'balasan kaleng'));
    }

    public function test_sapaan_gagal_dibalas_kalimat_kaleng(): void
    {
        Http::fake(fn () => throw new ConnectionException('timeout'));

        $this->assertSame('balasan kaleng', $this->engine()->chat('halo', [], 'balasan kaleng'));
    }

    /** Balasan basa-basi yang panjang hampir pasti sudah menjawab sesuatu. */
    public function test_balasan_basa_basi_kepanjangan_ditolak(): void
    {
        Http::fake([$this->endpoint() => Http::response($this->openAiReply(str_repeat('a', 400)))]);

        $this->assertSame('balasan kaleng', $this->engine()->chat('halo', [], 'balasan kaleng'));
    }

    // ---- saklar mati -------------------------------------------------------

    public function test_saklar_mati_mengembalikan_perilaku_lama(): void
    {
        Http::fake();

        $engine = new NoConversationEngine;

        $this->assertSame(self::QUESTION, $engine->standalone(self::QUESTION, $this->memory()));
        $this->assertSame('balasan kaleng', $engine->chat('halo', [], 'balasan kaleng'));

        Http::assertNothingSent();
    }

    public function test_bawaannya_lapisan_percakapan_mati(): void
    {
        config(['services.openai.conversation_enabled' => false]);

        $this->assertInstanceOf(NoConversationEngine::class, app(ConversationEngine::class));
    }

    public function test_saklar_menyala_tanpa_kunci_tetap_mati(): void
    {
        config([
            'services.openai.conversation_enabled' => true,
            'services.openai.key' => '',
        ]);

        $this->assertInstanceOf(NoConversationEngine::class, app(ConversationEngine::class));
    }

    public function test_saklar_menyala_dengan_kunci_memakai_openai(): void
    {
        config([
            'services.openai.conversation_enabled' => true,
            'services.openai.key' => 'kunci-uji',
        ]);

        $this->assertInstanceOf(OpenAiConversationEngine::class, app(ConversationEngine::class));
    }
}
