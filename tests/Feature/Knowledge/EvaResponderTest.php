<?php

namespace Tests\Feature\Knowledge;

use App\Models\Knowledge\AnswerLog;
use App\Models\Knowledge\Article;
use App\Models\Knowledge\Conversation;
use App\Services\Knowledge\EvaReply;
use App\Services\Knowledge\EvaResponder;
use App\Services\Knowledge\KnowledgeSearch;
use App\Services\Knowledge\SearchHit;
use App\Services\Knowledge\SubjectMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Mengunci otak percakapan EVA: kapan menjawab, kapan bertanya balik, kapan
 * menyerah — dan bahwa SETIAP jalur meninggalkan tepat satu baris
 * kb_answer_logs.
 *
 * Invarian terakhir itu yang paling mahal kalau bocor: Unanswered Questions,
 * Analytics, dan Ticket Recommendation semuanya dibangun dari tabel itu. Satu
 * jalur yang lupa mencatat tidak memunculkan error apa pun — ia hanya membuat
 * seluruh layar Insights melaporkan angka yang terlalu kecil, tanpa petunjuk.
 *
 * Pencarian A dipalsukan lewat interface KnowledgeSearch — seam portabilitas
 * yang sama yang nanti dipakai menukar FULLTEXT dengan embedding. Itu juga yang
 * membuat tes ini jalan di SQLite :memory: (FulltextKnowledgeSearch butuh
 * FULLTEXT MySQL) DAN membuat ambang keyakinan bisa diuji pada nilai persis,
 * bukan pada apa pun yang kebetulan dikembalikan mesin pencari hari ini.
 */
final class EvaResponderTest extends TestCase
{
    use RefreshDatabase;

    private object $search;

    protected function setUp(): void
    {
        parent::setUp();

        // VagueQuestionDetector, CatalogOptions, dan SubjectMatcher semuanya
        // menyimpan katalog di cache/statik. Tanpa dibersihkan, tes yang jalan
        // belakangan akan memakai katalog milik tes sebelumnya.
        Cache::flush();
        SubjectMatcher::forget();

        $this->seedCatalog();
        $this->searchReturns();
    }

    /**
     * Katalog minimal: satu subject password di layanan SAP, plus dua "Reset
     * Password" identik di dua sub category berbeda di bawah SATU layanan
     * (AKUN APLIKASI) — bentuk yang memicu seri, dan yang membuat pembeda
     * seharusnya sub category, bukan nama layanan.
     */
    private function seedCatalog(): void
    {
        DB::table('issue_categories')->insert(['id' => 1, 'name' => 'Access Request']);

        DB::table('service_catalog_services')->insert([
            ['id' => 1, 'name' => 'SAP'],
            ['id' => 2, 'name' => 'AKUN APLIKASI'],
        ]);

        DB::table('service_catalog_subcategories')->insert([
            ['id' => 1, 'service_id' => 1, 'name' => 'LOGIN SAP'],
            ['id' => 2, 'service_id' => 2, 'name' => 'SAP'],
            ['id' => 3, 'service_id' => 2, 'name' => 'SILO (OTHER APPS)'],
        ]);

        $subject = fn (int $id, int $service, int $subcat, string $name) => [
            'id' => $id, 'issue_category_id' => 1, 'service_id' => $service,
            'subcategory_id' => $subcat, 'name' => $name,
            'requires_approval' => false, 'support_level' => 1, 'is_active' => true,
        ];

        DB::table('service_catalog_subjects')->insert([
            $subject(1, 1, 1, 'Password Expired'),
            $subject(2, 2, 2, 'Reset Password'),
            $subject(3, 2, 3, 'Reset Password'),
        ]);
    }

    /** Pencarian A palsu — juga merekam apakah ia dipanggil sama sekali. */
    private function searchReturns(SearchHit ...$hits): void
    {
        $this->search = new class implements KnowledgeSearch
        {
            /** @var SearchHit[] */
            public array $hits = [];

            /** @var string[] */
            public array $queries = [];

            public function cari(string $pertanyaan, int $limit = 5): array
            {
                $this->queries[] = $pertanyaan;

                return $this->hits;
            }
        };

        $this->search->hits = $hits;
        $this->app->instance(KnowledgeSearch::class, $this->search);
    }

    private function responder(): EvaResponder
    {
        return $this->app->make(EvaResponder::class);
    }

    private function hit(int $confidence, ?int $subjectId = 1): SearchHit
    {
        return new SearchHit(
            sourceType: Article::class,
            sourceId: 7,
            title: 'SOP Reset Password SAP',
            answer: 'Buka portal SAP lalu ganti password Anda.',
            confidence: $confidence,
            catalogSubjectId: $subjectId,
        );
    }

    // ---- Ambang keyakinan -------------------------------------------------

    public function test_keyakinan_di_ambang_dijawab(): void
    {
        $this->searchReturns($this->hit(KnowledgeSearch::MIN_CONFIDENCE));

        $reply = $this->responder()->jawab('cara reset password sap');

        $this->assertSame(EvaReply::TYPE_ANSWER, $reply->type);
        $this->assertSame('Buka portal SAP lalu ganti password Anda.', $reply->text);
    }

    /**
     * Satu poin di bawah ambang sudah tidak dijawab. Ambang yang "kira-kira"
     * berarti EVA menebak, dan menebak panduan IT yang salah lebih merugikan
     * daripada mengaku tidak tahu.
     */
    public function test_satu_poin_di_bawah_ambang_tidak_dijawab(): void
    {
        $this->searchReturns($this->hit(KnowledgeSearch::MIN_CONFIDENCE - 1));

        $reply = $this->responder()->jawab('bagaimana cara mencetak dokumen besar');

        $this->assertSame(EvaReply::TYPE_NO_ANSWER, $reply->type);
    }

    public function test_keyakinan_pas_hedge_tidak_menahan_diri(): void
    {
        $this->searchReturns($this->hit(KnowledgeSearch::HEDGE_CONFIDENCE));

        $this->assertFalse($this->responder()->jawab('cara reset password sap')->isHedged);
    }

    public function test_keyakinan_sedang_dijawab_dengan_menahan_diri(): void
    {
        $this->searchReturns($this->hit(KnowledgeSearch::HEDGE_CONFIDENCE - 1));

        $reply = $this->responder()->jawab('cara reset password sap');

        $this->assertSame(EvaReply::TYPE_ANSWER, $reply->type);
        $this->assertTrue($reply->isHedged, 'di bawah HEDGE_CONFIDENCE jawaban harus ditandai menahan diri');
    }

    /** Calon terbaik diambil dari urutan pertama, bukan dicari ulang. */
    public function test_calon_pertama_yang_dipakai(): void
    {
        $this->searchReturns($this->hit(90), $this->hit(95));

        $this->assertSame(90, $this->responder()->jawab('cara reset password sap')->hit->confidence);
    }

    // ---- Bertanya balik ---------------------------------------------------

    /**
     * Keluhan generik tanpa nama layanan tidak boleh dicarikan jawabannya sama
     * sekali. Mencari dulu lalu membuang hasilnya tetap membuka peluang EVA
     * menjawab artikel layanan yang salah kalau ambangnya kebetulan terlampaui.
     */
    public function test_pertanyaan_kabur_bertanya_balik_tanpa_mencari(): void
    {
        $this->searchReturns($this->hit(99));

        $reply = $this->responder()->jawab('tidak bisa login');

        $this->assertSame(EvaReply::TYPE_CLARIFY, $reply->type);
        $this->assertSame([], $this->search->queries, 'pertanyaan kabur tidak boleh menyentuh Pencarian A');
    }

    /** Menyebut layanan membuat pertanyaan tidak lagi kabur. */
    public function test_menyebut_layanan_membuat_pertanyaan_tidak_kabur(): void
    {
        $this->searchReturns($this->hit(90));

        $reply = $this->responder()->jawab('tidak bisa login SAP');

        $this->assertSame(EvaReply::TYPE_ANSWER, $reply->type);
        $this->assertSame(['tidak bisa login SAP'], $this->search->queries);
    }

    /**
     * Seri: pertanyaannya jelas soal apa ("reset password") tapi ambigu
     * cabangnya. EVA bertanya balik dengan PEMBEDA NYATA — kedua calon berbagi
     * layanan AKUN APLIKASI, jadi pilihannya wajib sub category. Menawarkan
     * "AKUN APLIKASI" dua kali tidak memecah apa pun.
     */
    public function test_seri_bertanya_balik_dengan_pembeda_sub_category(): void
    {
        $this->searchReturns($this->hit(KnowledgeSearch::MIN_CONFIDENCE - 1));

        $reply = $this->responder()->jawab('reset password');

        $this->assertSame(EvaReply::TYPE_CLARIFY, $reply->type);
        $this->assertStringContainsString('Reset Password', $reply->text);
        $this->assertEqualsCanonicalizing(['SAP', 'SILO (OTHER APPS)'], $reply->clarifyOptions);
    }

    /** Jawaban yakin menang atas seri — seri hanya diperiksa saat EVA gagal. */
    public function test_jawaban_yakin_tidak_dikalahkan_seri(): void
    {
        $this->searchReturns($this->hit(90));

        $this->assertSame(EvaReply::TYPE_ANSWER, $this->responder()->jawab('reset password')->type);
    }

    /** Tanpa calon seri, kegagalan berakhir di tawaran draf tiket. */
    public function test_gagal_tanpa_seri_berakhir_no_answer(): void
    {
        $reply = $this->responder()->jawab('kucing saya lapar sekali');

        $this->assertSame(EvaReply::TYPE_NO_ANSWER, $reply->type);
        $this->assertSame([], $reply->clarifyOptions);
    }

    // ---- Pencatatan: invarian paling penting ------------------------------

    /**
     * SETIAP jalur menulis tepat satu baris. Yang paling mudah bocor adalah
     * no_answer — justru baris yang paling dibutuhkan Unanswered Questions.
     */
    public function test_setiap_jalur_mencatat_tepat_satu_baris(): void
    {
        $jalur = [
            AnswerLog::OUTCOME_ANSWERED => fn () => $this->answerable('cara reset password sap'),
            AnswerLog::OUTCOME_CLARIFY => fn () => $this->responder()->jawab('tidak bisa login'),
            AnswerLog::OUTCOME_NO_ANSWER => fn () => $this->responder()->jawab('kucing saya lapar sekali'),
        ];

        foreach ($jalur as $outcome => $jalankan) {
            AnswerLog::query()->delete();
            $this->searchReturns();

            $reply = $jalankan();

            $logs = AnswerLog::all();
            $this->assertCount(1, $logs, "jalur {$outcome} harus mencatat tepat satu baris");
            $this->assertSame($outcome, $logs[0]->outcome);
            $this->assertSame($logs[0]->id, $reply->answerLogId, 'id log yang dikembalikan harus baris yang benar-benar ditulis');
        }
    }

    public function test_log_jawaban_menyimpan_sumber_dan_keyakinan(): void
    {
        $this->searchReturns($this->hit(88, subjectId: 2));

        $this->responder()->jawab('cara reset password sap');

        $log = AnswerLog::sole();
        $this->assertSame(Article::class, $log->source_type);
        $this->assertSame(7, $log->source_id);
        $this->assertSame(88, $log->confidence);
        $this->assertSame(2, $log->catalog_subject_id);
    }

    /**
     * Jalur gagal TIDAK boleh menebak subject. Kolom catalog_subject_id sudah
     * berarti "subject artikel yang menjawab"; mengisinya dengan tebakan
     * membuat satu kolom berarti dua hal dan diam-diam merusak Coverage.
     */
    public function test_jalur_gagal_tidak_menebak_subject(): void
    {
        $this->responder()->jawab('reset password');

        $log = AnswerLog::sole();
        $this->assertNull($log->catalog_subject_id);
        $this->assertSame(0, $log->confidence);
    }

    /** Kolom question hanya 500 karakter — pemotongan terjadi sebelum insert. */
    public function test_pertanyaan_panjang_dipotong_sebelum_dicatat(): void
    {
        $this->responder()->jawab('  '.str_repeat('a', 600).'  ');

        $this->assertSame(500, mb_strlen(AnswerLog::sole()->question));
    }

    // ---- Hasil percakapan -------------------------------------------------

    public function test_percakapan_ditandai_terjawab(): void
    {
        $conversation = $this->conversation();
        $this->answerable('cara reset password sap', $conversation);

        $this->assertSame(Conversation::OUTCOME_ANSWERED, $conversation->fresh()->outcome);
    }

    public function test_percakapan_gagal_ditandai_menuju_tiket(): void
    {
        $conversation = $this->conversation();
        $this->responder()->jawab('kucing saya lapar sekali', $conversation);

        $this->assertSame(Conversation::OUTCOME_TICKET, $conversation->fresh()->outcome);
    }

    /**
     * Bertanya balik BUKAN akhir percakapan. Menandainya "menuju tiket" akan
     * membuat Log Percakapan menghitung deflection yang tidak pernah terjadi.
     */
    public function test_bertanya_balik_tidak_menutup_percakapan(): void
    {
        $conversation = $this->conversation();
        $this->responder()->jawab('tidak bisa login', $conversation);

        $this->assertSame(Conversation::OUTCOME_OPEN, $conversation->fresh()->outcome);
    }

    /** Giliran berikutnya yang berhasil tetap bisa menutup percakapan. */
    public function test_percakapan_tertutup_di_giliran_berikutnya(): void
    {
        $conversation = $this->conversation();

        $this->responder()->jawab('tidak bisa login', $conversation);
        $this->answerable('tidak bisa login SAP', $conversation);

        $this->assertSame(Conversation::OUTCOME_ANSWERED, $conversation->fresh()->outcome);
        $this->assertSame(2, AnswerLog::where('conversation_id', $conversation->id)->count());
    }

    private function conversation(): Conversation
    {
        return Conversation::create([
            'requester_name' => 'Andi Pratama',
            'outcome' => Conversation::OUTCOME_OPEN,
            'started_at' => now(),
        ]);
    }

    private function answerable(string $question, ?Conversation $conversation = null): EvaReply
    {
        $this->searchReturns($this->hit(90));

        return $this->responder()->jawab($question, $conversation);
    }
}
