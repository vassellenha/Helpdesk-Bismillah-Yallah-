<?php

declare(strict_types=1);

namespace Tests\Feature\Knowledge;

use App\Models\Knowledge\Article;
use App\Services\Knowledge\AnswerParaphraser;
use App\Services\Knowledge\EvaResponder;
use App\Services\Knowledge\KnowledgeSearch;
use App\Services\Knowledge\OpenAiCooldown;
use App\Services\Knowledge\OpenAiParaphraser;
use App\Services\Knowledge\OpenAiSynthesizer;
use App\Services\Knowledge\PassthroughParaphraser;
use App\Services\Knowledge\SearchHit;
use App\Services\Knowledge\SubjectMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Parafrase jawaban EVA: satu-satunya tempat teks Knowledge Base boleh ditulis
 * ulang sebelum sampai ke karyawan.
 *
 * Yang dijaga di sini bukan "apakah kalimatnya jadi lebih bagus" — itu tidak
 * bisa diuji otomatis. Yang dijaga adalah dua hal yang bisa: EVA tidak pernah
 * berhenti menjawab gara-gara model, dan tidak ada angka SOP yang berubah dalam
 * perjalanan.
 */
final class AnswerParaphraseTest extends TestCase
{
    use RefreshDatabase;

    private const ANSWER = 'Buka menu Profil di SAP, pilih Ubah Kata Sandi, lalu masukkan kata sandi lama. Kata sandi baru minimal 12 karakter dan berlaku 90 hari.';

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        SubjectMatcher::forget();
        Http::preventStrayRequests();
    }

    /**
     * Penjaga dompet, bukan penjaga perilaku.
     *
     * Tanpa dua nilai ini dipaku di phpunit.xml, suite membaca .env pengembang:
     * setiap tes yang membuat EVA menjawab menembak OpenAI sungguhan, satu
     * panggilan berbayar per jawaban, dan sekali `php artisan test` sanggup
     * menghabiskan kuota per-menit sebuah akun. Persis itu yang terjadi sekali
     * sebelum tes ini ada.
     */
    public function test_lingkungan_tes_tidak_pernah_memanggil_openai(): void
    {
        $this->assertSame('', (string) config('services.openai.key'));
        $this->assertFalse((bool) config('services.openai.paraphrase_enabled'));
        $this->assertInstanceOf(PassthroughParaphraser::class, $this->app->make(AnswerParaphraser::class));
    }

    /**
     * Kunci OpenAI ~164 karakter dan hampir selalu ditempel lewat editor
     * terminal. Dua kecelakaan sungguhan terjadi saat memasangnya di server:
     * baris terpotong di tepi layar nano sehingga tanda ">" ikut tersimpan,
     * dan satu tanda kutip tertinggal di ujung. Keduanya menghasilkan 401 yang
     * membingungkan — kuncinya "terbaca", panjangnya wajar, tapi ditolak.
     */
    public function test_kunci_dengan_kutip_atau_spasi_nyasar_dibersihkan(): void
    {
        $asli = getenv('OPENAI_API_KEY');

        try {
            foreach (['"sk-uji-123"', " sk-uji-123\n", "'sk-uji-123'"] as $kotor) {
                putenv('OPENAI_API_KEY='.$kotor);
                $_ENV['OPENAI_API_KEY'] = $kotor;

                $config = require config_path('services.php');

                $this->assertSame('sk-uji-123', $config['openai']['key'], "Gagal membersihkan: {$kotor}");
            }
        } finally {
            putenv('OPENAI_API_KEY='.($asli === false ? '' : $asli));
            $_ENV['OPENAI_API_KEY'] = $asli === false ? '' : $asli;
        }
    }

    public function test_jawaban_kb_dikirim_ke_parafrase_sebelum_sampai_ke_penanya(): void
    {
        $this->searchReturns(self::ANSWER);
        $this->app->instance(AnswerParaphraser::class, new class implements AnswerParaphraser
        {
            public function parafrase(string $answer): string
            {
                return 'DITULIS ULANG: '.$answer;
            }
        });

        $reply = $this->app->make(EvaResponder::class)->jawab('cara ubah kata sandi sap');

        $this->assertSame('DITULIS ULANG: '.self::ANSWER, $reply->text);
        // Materi aslinya tetap terbawa untuk panel sumber dan penilaian rating.
        $this->assertSame(self::ANSWER, $reply->hit->answer);
    }

    public function test_bawaannya_tidak_mengubah_jawaban_sama_sekali(): void
    {
        config()->set('services.openai.paraphrase_enabled', false);
        $this->searchReturns(self::ANSWER);

        $this->assertInstanceOf(PassthroughParaphraser::class, $this->app->make(AnswerParaphraser::class));
        $this->assertSame(self::ANSWER, $this->app->make(EvaResponder::class)->jawab('cara ubah kata sandi sap')->text);
    }

    public function test_flag_menyala_tanpa_kunci_tetap_tidak_memanggil_openai(): void
    {
        config()->set('services.openai.paraphrase_enabled', true);
        config()->set('services.openai.key', '');

        $this->assertInstanceOf(PassthroughParaphraser::class, $this->app->make(AnswerParaphraser::class));
    }

    public function test_flag_menyala_dengan_kunci_memakai_openai(): void
    {
        config()->set('services.openai.paraphrase_enabled', true);
        config()->set('services.openai.key', 'kunci-uji');

        $this->assertInstanceOf(OpenAiParaphraser::class, $this->app->make(AnswerParaphraser::class));
    }

    public function test_hasil_parafrase_dipakai_saat_openai_menjawab_wajar(): void
    {
        $rewritten = 'Masuk ke menu Profil SAP, pilih Ubah Kata Sandi, isi kata sandi lama, lalu buat kata sandi baru minimal 12 karakter yang berlaku 90 hari.';
        Http::fake([$this->endpoint() => Http::response($this->openAiReply($rewritten))]);

        $this->assertSame($rewritten, $this->paraphraser()->parafrase(self::ANSWER));
    }

    /**
     * Model generasi baru menolak `max_tokens` dengan 400, dan gejalanya
     * menipu: pengaman menangkapnya, EVA tetap menjawab dengan teks asli, dan
     * fiturnya tampak "menyala tapi tidak pernah mengubah apa pun". Nama
     * parameternya dikunci di sini supaya kesalahan itu muncul sebagai tes
     * merah, bukan sebagai fitur yang diam.
     */
    public function test_permintaan_memakai_nama_parameter_batas_token_yang_didukung(): void
    {
        Http::fake([$this->endpoint() => Http::response($this->openAiReply('x'))]);

        $this->paraphraser()->parafrase(self::ANSWER);

        Http::assertSent(function ($request) {
            $body = $request->data();

            return array_key_exists('max_completion_tokens', $body)
                && ! array_key_exists('max_tokens', $body)
                && $body['model'] === 'model-uji';
        });
    }

    public function test_openai_gagal_maka_jawaban_asli_yang_dipakai(): void
    {
        Http::fake([$this->endpoint() => Http::response(['error' => ['message' => 'quota habis']], 429)]);

        $this->assertSame(self::ANSWER, $this->paraphraser()->parafrase(self::ANSWER));
    }

    public function test_jaringan_putus_tidak_membuat_eva_berhenti_menjawab(): void
    {
        Http::fake(fn () => throw new ConnectionException('timeout'));

        $this->assertSame(self::ANSWER, $this->paraphraser()->parafrase(self::ANSWER));
    }

    public function test_balasan_kosong_ditolak(): void
    {
        Http::fake([$this->endpoint() => Http::response($this->openAiReply('   '))]);

        $this->assertSame(self::ANSWER, $this->paraphraser()->parafrase(self::ANSWER));
    }

    public function test_parafrase_yang_menghilangkan_angka_sop_ditolak(): void
    {
        // "12 karakter" hilang — persis jenis penyimpangan yang tidak boleh
        // sampai ke karyawan, karena hasilnya tetap terdengar meyakinkan.
        $menyimpang = 'Masuk ke menu Profil SAP, pilih Ubah Kata Sandi, isi kata sandi lama, lalu buat kata sandi baru yang kuat dan berlaku 90 hari.';
        Http::fake([$this->endpoint() => Http::response($this->openAiReply($menyimpang))]);

        $this->assertSame(self::ANSWER, $this->paraphraser()->parafrase(self::ANSWER));
    }

    public function test_parafrase_yang_mengarang_panjang_ditolak(): void
    {
        $kepanjangan = self::ANSWER.' '.str_repeat('Sebagai tambahan, hubungi tim IT bila mengalami kendala lain. ', 5);
        Http::fake([$this->endpoint() => Http::response($this->openAiReply($kepanjangan))]);

        $this->assertSame(self::ANSWER, $this->paraphraser()->parafrase(self::ANSWER));
    }

    public function test_jawaban_pendek_tidak_dikirim_sama_sekali(): void
    {
        Http::fake();

        $this->assertSame('Hubungi IT.', $this->paraphraser()->parafrase('Hubungi IT.'));
        Http::assertNothingSent();
    }

    public function test_jawaban_yang_sama_hanya_dipanggilkan_sekali(): void
    {
        $rewritten = 'Buka menu Profil SAP, pilih Ubah Kata Sandi, isikan kata sandi lama, lalu buat yang baru minimal 12 karakter dan berlaku 90 hari.';
        Http::fake([$this->endpoint() => Http::response($this->openAiReply($rewritten))]);

        $paraphraser = $this->paraphraser();
        $paraphraser->parafrase(self::ANSWER);

        $this->assertSame($rewritten, $paraphraser->parafrase(self::ANSWER));
        Http::assertSentCount(1);
    }

    public function test_kegagalan_tidak_ikut_tersimpan_di_cache(): void
    {
        $rewritten = 'Buka menu Profil SAP, pilih Ubah Kata Sandi, isikan kata sandi lama, lalu buat yang baru minimal 12 karakter dan berlaku 90 hari.';
        Http::fakeSequence()
            ->push(['error' => ['message' => 'sedang sibuk']], 503)
            ->push($this->openAiReply($rewritten));

        $paraphraser = $this->paraphraser();

        // Gangguan sesaat tidak boleh membekukan teks mentah selama masa cache.
        $this->assertSame(self::ANSWER, $paraphraser->parafrase(self::ANSWER));

        // Penolakan tadi menahan panggilan berikutnya selama semenit. Perankan
        // menit itu lewat, supaya yang diuji di sini tetap soal cache.
        OpenAiCooldown::forget();

        $this->assertSame($rewritten, $paraphraser->parafrase(self::ANSWER));
    }

    public function test_kunci_tidak_pernah_ikut_ke_dalam_teks_jawaban(): void
    {
        Http::fake([$this->endpoint() => Http::response(['error' => ['message' => 'unauthorized']], 401)]);

        $reply = $this->paraphraser()->parafrase(self::ANSWER);

        $this->assertStringNotContainsString('kunci-uji', $reply);
    }

    /**
     * Saat kuota habis, satu pertanyaan dulu menempuh DUA panggilan yang
     * sama-sama pasti ditolak — rangkuman lalu parafrase — dan karyawan
     * menunggu dua kali batas waktu jaringan untuk jawaban yang ujungnya
     * diambil dari artikel juga.
     */
    public function test_penolakan_pertama_menahan_panggilan_berikutnya(): void
    {
        Http::fake([$this->endpoint() => Http::response(['error' => ['message' => 'rate limit']], 429)]);

        $paraphraser = $this->paraphraser();
        $paraphraser->parafrase(self::ANSWER);
        $paraphraser->parafrase('Teks lain yang cukup panjang untuk melewati ambang minimal parafrase EVA.');

        Http::assertSentCount(1);
    }

    public function test_jeda_itu_dipakai_bersama_oleh_rangkuman_dan_parafrase(): void
    {
        Http::fake([$this->endpoint() => Http::response(['error' => ['message' => 'rate limit']], 429)]);

        // Rangkuman gagal duluan, persis urutan di EvaResponder.
        (new OpenAiSynthesizer(['key' => 'kunci-uji', 'model' => 'model-uji', 'timeout' => 5]))
            ->rangkum('pertanyaan apa pun', [['title' => 'A', 'text' => 'isi']]);

        $this->assertSame(self::ANSWER, $this->paraphraser()->parafrase(self::ANSWER));

        // Parafrase tidak ikut menelepon: penolakan rangkuman sudah jadi kabar.
        Http::assertSentCount(1);
    }

    /**
     * 400 adalah keluhan atas isi permintaan kita sendiri, bukan gangguan di
     * seberang. Menahan panggilan selama semenit hanya akan menyembunyikan bug
     * kita di balik jeda yang terlihat seperti masalah jaringan.
     */
    public function test_kesalahan_permintaan_sendiri_tidak_menahan_panggilan(): void
    {
        Http::fake([$this->endpoint() => Http::response(['error' => ['message' => 'Unsupported parameter']], 400)]);

        $paraphraser = $this->paraphraser();
        $paraphraser->parafrase(self::ANSWER);
        $paraphraser->parafrase('Teks lain yang cukup panjang untuk melewati ambang minimal parafrase EVA.');

        Http::assertSentCount(2);
    }

    public function test_setelah_semenit_eva_mencoba_lagi_sendiri(): void
    {
        $rewritten = 'Buka menu Profil SAP, pilih Ubah Kata Sandi, isikan kata sandi lama, lalu buat yang baru minimal 12 karakter dan berlaku 90 hari.';
        Http::fakeSequence()
            ->push(['error' => ['message' => 'rate limit']], 429)
            ->push($this->openAiReply($rewritten));

        $paraphraser = $this->paraphraser();
        $this->assertSame(self::ANSWER, $paraphraser->parafrase(self::ANSWER));

        // Pemulihannya tidak menunggu siapa pun — tidak ada sakelar yang harus
        // dinyalakan orang.
        $this->travel(61)->seconds();

        $this->assertSame($rewritten, $paraphraser->parafrase(self::ANSWER));
    }

    private function paraphraser(): OpenAiParaphraser
    {
        return new OpenAiParaphraser([
            'key' => 'kunci-uji',
            'model' => 'model-uji',
            'timeout' => 5,
        ]);
    }

    private function endpoint(): string
    {
        return 'api.openai.com/*';
    }

    /** @return array<string,mixed> bentuk respons Chat Completions seperlunya */
    private function openAiReply(string $content): array
    {
        return ['choices' => [['message' => ['role' => 'assistant', 'content' => $content]]]];
    }

    private function searchReturns(string $answer): void
    {
        $article = Article::create([
            'title' => 'Ubah Kata Sandi SAP',
            'body' => $answer,
            'status' => 'published',
        ]);

        $search = new class implements KnowledgeSearch
        {
            public array $hits = [];

            public function cari(string $pertanyaan, int $limit = 5): array
            {
                return $this->hits;
            }
        };

        $search->hits = [new SearchHit(
            sourceType: Article::class,
            sourceId: $article->id,
            title: 'Ubah Kata Sandi SAP',
            answer: $answer,
            confidence: 90,
            catalogSubjectId: null,
        )];

        $this->app->instance(KnowledgeSearch::class, $search);
    }
}
