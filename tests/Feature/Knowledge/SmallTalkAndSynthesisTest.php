<?php

declare(strict_types=1);

namespace Tests\Feature\Knowledge;

use App\Models\Knowledge\AnswerLog;
use App\Models\Knowledge\Article;
use App\Services\Knowledge\EvaReply;
use App\Services\Knowledge\EvaResponder;
use App\Services\Knowledge\KnowledgeSearch;
use App\Services\Knowledge\KnowledgeStats;
use App\Services\Knowledge\KnowledgeSynthesizer;
use App\Services\Knowledge\OpenAiSynthesizer;
use App\Services\Knowledge\SearchHit;
use App\Services\Knowledge\SmallTalkDetector;
use App\Services\Knowledge\SubjectMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Dua kemampuan yang mengubah kapan EVA boleh berkata "belum ketemu".
 *
 * 1. Sapaan tidak lagi menempuh pencarian. Dulu "Halo" dibalas "Maaf, saya
 *    belum menemukan jawaban" plus tawaran tiket, DAN tercatat sebagai celah
 *    materi di Unanswered Questions — celah yang mustahil ditutup, karena tidak
 *    ada artikel yang menjawab "Halo".
 * 2. Jawaban boleh dirangkai dari beberapa sumber. Pertanyaan yang jawabannya
 *    tersebar tidak membuat satu dokumen pun terlihat meyakinkan sendirian,
 *    sehingga dulu selalu berakhir di jalur menyerah walau materinya ada.
 *
 * Yang paling dijaga di berkas ini justru arah sebaliknya: EVA tetap HARUS bisa
 * berkata belum ketemu. Fitur yang membuatnya selalu punya jawaban akan
 * mengubah Unanswered Questions jadi kosong terus, dan admin kehilangan satu-
 * satunya petunjuk materi mana yang perlu ditulis.
 */
final class SmallTalkAndSynthesisTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        SubjectMatcher::forget();
        Http::preventStrayRequests();
    }

    #[DataProvider('sapaan')]
    public function test_sapaan_dijawab_tanpa_menyentuh_knowledge_base(string $sapaan): void
    {
        $this->searchReturns();

        $reply = $this->responder()->jawab($sapaan);

        $this->assertSame(EvaReply::TYPE_SMALL_TALK, $reply->type, "\"{$sapaan}\" seharusnya dianggap sapaan.");
        $this->assertStringNotContainsString('belum menemukan jawaban', $reply->text);
        $this->assertNull($reply->hit);
    }

    public static function sapaan(): array
    {
        return [
            ['Halo'], ['halo!'], ['Haloooo'], ['hai eva'], ['Selamat pagi'],
            ['terima kasih'], ['Makasih ya'], ['kamu siapa?'], ['Kamu bisa apa'],
            ['test'], ['assalamualaikum'],
        ];
    }

    #[DataProvider('omongKosong')]
    public function test_masukan_yang_bukan_pertanyaan_tidak_masuk_unanswered(string $masukan): void
    {
        $this->searchReturns();

        $reply = $this->responder()->jawab($masukan);

        $this->assertSame(EvaReply::TYPE_SMALL_TALK, $reply->type, "\"{$masukan}\" seharusnya tidak dianggap celah materi.");
        $this->assertSame(AnswerLog::OUTCOME_SMALL_TALK, AnswerLog::firstOrFail()->outcome);
    }

    public static function omongKosong(): array
    {
        return [['a'], ['??'], ['12345'], ['asdfgh'], ['qwerty'], ['zxcvbn'], ['sdfgh'], ['ok'], ['siap']];
    }

    /**
     * Penyaringnya harus pelit. Pertanyaan sungguhan yang salah dituduh omong
     * kosong akan lenyap dari Unanswered Questions, dan celah materinya tidak
     * pernah ketahuan — kegagalan yang tidak meninggalkan jejak sama sekali.
     */
    #[DataProvider('pertanyaanPendek')]
    public function test_singkatan_dan_pertanyaan_pendek_tetap_dicari_di_kb(string $pertanyaan): void
    {
        $detector = new SmallTalkDetector;

        $this->assertNull($detector->balasan($pertanyaan), "\"{$pertanyaan}\" adalah pertanyaan, bukan omong kosong.");
    }

    public static function pertanyaanPendek(): array
    {
        return [['VPN'], ['SAP'], ['wifi'], ['vpn wfh'], ['tor document'], ['printer']];
    }

    /**
     * Tabel Unanswered Questions bisa diurutkan menurut waktu, dan itu butuh
     * waktu mentahnya — bukan "2 jam yang lalu". Diurut sebagai teks, "2 jam"
     * jatuh di antara "19 menit" dan "3 hari": urutannya terlihat acak tanpa
     * satu pun error yang muncul.
     */
    public function test_daftar_unanswered_membawa_waktu_yang_bisa_diurutkan(): void
    {
        $lama = AnswerLog::create(['question' => 'pertanyaan lama', 'outcome' => AnswerLog::OUTCOME_NO_ANSWER, 'confidence' => 0]);
        $lama->forceFill(['created_at' => now()->subDays(9)])->save();

        AnswerLog::create(['question' => 'pertanyaan baru', 'outcome' => AnswerLog::OUTCOME_NO_ANSWER, 'confidence' => 0]);

        $rows = app(KnowledgeStats::class)->topUnansweredQuestions(10)->keyBy('question');

        $this->assertNotEmpty($rows['pertanyaan lama']['last_asked_iso']);
        $this->assertLessThan(
            strtotime($rows['pertanyaan baru']['last_asked_iso']),
            strtotime($rows['pertanyaan lama']['last_asked_iso']),
        );
    }

    public function test_perintah_memindahkan_sapaan_lama_keluar_dari_unanswered(): void
    {
        $this->searchReturns();

        // Baris warisan: dicatat sebelum SmallTalkDetector ada.
        foreach (['Halo', 'terima kasih', 'cara pakai vpn untuk wfh'] as $question) {
            AnswerLog::create([
                'question' => $question,
                'outcome' => AnswerLog::OUTCOME_NO_ANSWER,
                'confidence' => 0,
            ]);
        }

        $this->assertSame(3, AnswerLog::unanswered()->count());

        $this->artisan('eva:reclassify-small-talk')->assertSuccessful();
        $this->assertSame(3, AnswerLog::unanswered()->count(), 'Tanpa --apply tidak boleh ada yang berubah.');

        $this->artisan('eva:reclassify-small-talk', ['--apply' => true])->assertSuccessful();

        // Pertanyaan sungguhannya WAJIB tetap tinggal.
        $tersisa = AnswerLog::unanswered()->pluck('question')->all();
        $this->assertSame(['cara pakai vpn untuk wfh'], $tersisa);
    }

    public function test_pertanyaan_sungguhan_tidak_tertangkap_sebagai_sapaan(): void
    {
        $detector = new SmallTalkDetector;

        // Semuanya memuat kata sapaan, tapi semuanya pertanyaan sungguhan.
        $this->assertNull($detector->balasan('halo, bagaimana cara reset password SAP'));
        $this->assertNull($detector->balasan('apa itu VPN'));
        $this->assertNull($detector->balasan('cara tes koneksi jaringan'));
        $this->assertNull($detector->balasan('siapa PIC aplikasi ADELE'));
    }

    public function test_sapaan_tidak_terhitung_sebagai_pertanyaan_tak_terjawab(): void
    {
        $this->searchReturns();
        $this->responder()->jawab('Halo');

        $log = AnswerLog::firstOrFail();
        $this->assertSame(AnswerLog::OUTCOME_SMALL_TALK, $log->outcome);

        // Analytics tidak boleh ikut turun gara-gara orang menyapa.
        $summary = app(KnowledgeStats::class)->answerSummary();
        $this->assertSame(0, $summary['total']);
        $this->assertSame(0, $summary['unanswered']);
    }

    public function test_jawaban_dirangkai_dari_beberapa_sumber_sekaligus(): void
    {
        $this->searchReturns(
            ['Syarat Akses SAP', 'Permohonan akses SAP memakai form FRM-TI-02 dan wajib disetujui atasan langsung.', 70],
            ['SLA Permohonan Akses', 'Permohonan yang lengkap diproses paling lambat 3 hari kerja.', 45],
        );
        $rangkuman = 'Ajukan form FRM-TI-02 dengan persetujuan atasan langsung; permohonan lengkap diproses paling lambat 3 hari kerja.';
        $this->synthesizerReturns($rangkuman);

        $reply = $this->responder()->jawab('bagaimana cara minta akses SAP dan berapa lama prosesnya');

        $this->assertSame(EvaReply::TYPE_ANSWER, $reply->type);
        $this->assertSame($rangkuman, $reply->text);
        $this->assertSame(AnswerLog::OUTCOME_ANSWERED, AnswerLog::firstOrFail()->outcome);
    }

    public function test_sumber_lemah_pun_ikut_dibaca_saat_merangkum(): void
    {
        // Tidak satu pun melewati MIN_CONFIDENCE 55 — dulu ini pasti berakhir
        // "belum menemukan jawaban". Justru di sinilah merangkum berguna.
        $this->searchReturns(
            ['Potongan A', 'Akun terkunci dibuka oleh IT Support.', 40],
            ['Potongan B', 'Permintaan buka akun lewat form FRM-TI-02.', 35],
        );
        $this->synthesizerReturns('Akun terkunci dibuka IT Support melalui form FRM-TI-02.');

        $this->assertSame(EvaReply::TYPE_ANSWER, $this->responder()->jawab('akun saya terkunci')->type);
    }

    public function test_eva_tetap_menyerah_saat_rangkuman_menyatakan_tidak_ada(): void
    {
        $this->searchReturns(['Potongan Tidak Nyambung', 'Prosedur pengajuan cuti tahunan.', 30]);
        $this->synthesizerReturns(null);

        $reply = $this->responder()->jawab('kenapa printer lantai 7 macet');

        $this->assertSame(EvaReply::TYPE_NO_ANSWER, $reply->type);
        $this->assertSame(AnswerLog::OUTCOME_NO_ANSWER, AnswerLog::firstOrFail()->outcome);
    }

    public function test_tanpa_mesin_rangkuman_perilaku_lama_tetap_berlaku(): void
    {
        $this->searchReturns(['Artikel Kuat', 'Jawaban dari satu artikel yang meyakinkan.', 90]);

        $reply = $this->responder()->jawab('pertanyaan yang jelas jawabannya');

        $this->assertSame(EvaReply::TYPE_ANSWER, $reply->type);
        $this->assertSame('Jawaban dari satu artikel yang meyakinkan.', $reply->text);
    }

    public function test_sentinel_dari_model_diterjemahkan_jadi_belum_ketemu(): void
    {
        Http::fake(['api.openai.com/*' => Http::response($this->openAiReply('TIDAK_ADA_DI_KB'))]);

        $this->assertNull($this->synthesizer()->rangkum('apa pun', [['title' => 'A', 'text' => 'isi']]));
    }

    public function test_rangkuman_dengan_angka_karangan_ditolak(): void
    {
        // Sumbernya tidak pernah menyebut 14 hari. Bentuk halusinasi paling
        // mahal di konteks SOP: terdengar wajar, dan salah.
        Http::fake(['api.openai.com/*' => Http::response($this->openAiReply('Permohonan diproses paling lambat 14 hari kerja.'))]);

        $this->assertNull($this->synthesizer()->rangkum(
            'berapa lama prosesnya',
            [['title' => 'SLA', 'text' => 'Permohonan diproses paling lambat 3 hari kerja.']],
        ));
    }

    public function test_openai_gagal_maka_rangkuman_dilewati_bukan_error(): void
    {
        Http::fake(['api.openai.com/*' => Http::response(['error' => ['message' => 'rate limit']], 429)]);

        $this->assertNull($this->synthesizer()->rangkum('apa pun', [['title' => 'A', 'text' => 'isi']]));
    }

    public function test_tanpa_potongan_sama_sekali_tidak_ada_panggilan(): void
    {
        Http::fake();

        $this->assertNull($this->synthesizer()->rangkum('apa pun', []));
        Http::assertNothingSent();
    }

    private function responder(): EvaResponder
    {
        return $this->app->make(EvaResponder::class);
    }

    private function synthesizer(): OpenAiSynthesizer
    {
        return new OpenAiSynthesizer(['key' => 'kunci-uji', 'model' => 'model-uji', 'timeout' => 5]);
    }

    private function synthesizerReturns(?string $rangkuman): void
    {
        $this->app->instance(KnowledgeSynthesizer::class, new class($rangkuman) implements KnowledgeSynthesizer
        {
            public function __construct(private readonly ?string $rangkuman) {}

            public function rangkum(string $question, array $passages): ?string
            {
                return $this->rangkuman;
            }
        });
    }

    /** @param array{0:string,1:string,2:int} ...$rows judul, isi, keyakinan */
    private function searchReturns(array ...$rows): void
    {
        $hits = [];

        foreach ($rows as [$title, $text, $confidence]) {
            $article = Article::create(['title' => $title, 'body' => $text, 'status' => 'published']);
            $hits[] = new SearchHit(Article::class, $article->id, $title, $text, $confidence, null);
        }

        $search = new class implements KnowledgeSearch
        {
            public array $hits = [];

            public function cari(string $pertanyaan, int $limit = 5): array
            {
                return array_slice($this->hits, 0, $limit);
            }
        };

        $search->hits = $hits;
        $this->app->instance(KnowledgeSearch::class, $search);
    }

    /** @return array<string,mixed> */
    private function openAiReply(string $content): array
    {
        return ['choices' => [['message' => ['role' => 'assistant', 'content' => $content]]]];
    }
}
