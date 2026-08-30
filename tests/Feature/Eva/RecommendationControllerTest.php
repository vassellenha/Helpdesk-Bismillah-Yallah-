<?php

namespace Tests\Feature\Eva;

use App\Models\Knowledge\AnswerLog;
use App\Models\Knowledge\Article;
use App\Models\Knowledge\DismissedQuestion;
use App\Services\Knowledge\KnowledgeSearch;
use App\Services\Knowledge\SearchHit;
use App\Services\Knowledge\SubjectMatch;
use App\Services\Knowledge\ServiceMatch;
use App\Services\Knowledge\SubjectSearch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\ActsAsEvaAdmin;
use Tests\TestCase;

/**
 * Ticket Recommendation — layar yang menjawab "kalau EVA menyerah, tiketnya
 * mendarat di mana".
 *
 * Dua hal yang dikunci di sini, keduanya tak menimbulkan error apa pun kalau
 * bocor:
 *
 *  1. **Saran tidak pernah disimpan.** Seluruh isi layar dihitung ulang tiap
 *     kali dibuka. Begitu ada yang menuliskannya ke
 *     `kb_answer_logs.catalog_subject_id`, kolom itu berarti dua hal sekaligus
 *     — "subject artikel yang menjawab" untuk log terjawab, "tebakan" untuk log
 *     gagal — dan setiap layar yang membacanya ikut salah diam-diam.
 *  2. **`has_material` memakai gerbang answerable().** Subject yang materinya
 *     masih draf HARUS tetap terhitung sebagai celah; kalau tidak, daftar tugas
 *     menulis di layar ini menyembunyikan justru pekerjaan yang belum selesai.
 *
 * Pencarian B dipalsukan lewat interface `SubjectSearch` — seam yang sama yang
 * nanti dipakai menukar cocok-kata dengan embedding. Itu yang membuat ambang
 * bisa diuji pada nilai PERSIS (MIN_CONFIDENCE dan MIN_CONFIDENCE - 1), bukan
 * pada apa pun yang kebetulan dikembalikan mesin pencocok hari ini.
 */
final class RecommendationControllerTest extends TestCase
{
    use ActsAsEvaAdmin;
    use RefreshDatabase;

    /** @var array<string,SubjectMatch[]> pertanyaan → calon yang dikembalikan */
    private array $script = [];

    /** @var array<string,SearchHit[]> pertanyaan → jawaban yang ditemukan EVA */
    private array $answers = [];

    /** @var array<string,ServiceMatch> pertanyaan → layanan yang jelas disebut */
    private array $services = [];

    protected function setUp(): void
    {
        parent::setUp();

        // CatalogOptions menyimpan katalog di cache; tanpa dibersihkan, tes yang
        // jalan belakangan memakai katalog milik tes sebelumnya.
        Cache::flush();

        $this->seedCatalog();
        $this->fakeSubjectSearch();
        $this->fakeKnowledgeSearch();

        $this->actingAsEvaAdmin();
    }

    /** Katalog minimal — dua subject di bawah satu layanan, cukup untuk semuanya. */
    private function seedCatalog(): void
    {
        DB::table('issue_categories')->insert(['id' => 1, 'name' => 'Access Request']);
        DB::table('service_catalog_services')->insert(['id' => 1, 'name' => 'SAP']);
        DB::table('service_catalog_subcategories')->insert([
            ['id' => 1, 'service_id' => 1, 'name' => 'LOGIN SAP'],
        ]);

        $subject = fn (int $id, string $name) => [
            'id' => $id, 'issue_category_id' => 1, 'service_id' => 1,
            'subcategory_id' => 1, 'name' => $name,
            'requires_approval' => false, 'support_level' => 1, 'is_active' => true,
        ];

        DB::table('service_catalog_subjects')->insert([
            $subject(1, 'Reset Password'),
            $subject(2, 'User Locked'),
        ]);
    }

    /**
     * Pencarian B palsu: mengembalikan calon yang sudah ditulis per pertanyaan.
     * Pertanyaan yang tidak ada di skrip mengembalikan kosong — itulah bentuk
     * "tak satu pun calon" yang diuji di bawah.
     */
    private function fakeSubjectSearch(): void
    {
        $test = $this;

        $this->app->instance(SubjectSearch::class, new class($test) implements SubjectSearch
        {
            public function __construct(private readonly RecommendationControllerTest $test) {}

            public function cocokkan(string $pertanyaan, int $limit = 5): array
            {
                return array_slice($this->test->scriptFor($pertanyaan), 0, $limit);
            }

            public function terbaik(string $pertanyaan): ?SubjectMatch
            {
                return $this->test->scriptFor($pertanyaan)[0] ?? null;
            }

            public function calonSeri(string $pertanyaan): array
            {
                return [];
            }

            public function layananTerbaik(string $pertanyaan): ?ServiceMatch
            {
                return $this->test->layananFor($pertanyaan);
            }
        });
    }

    /** @return SubjectMatch[] */
    public function scriptFor(string $question): array
    {
        return $this->script[$question] ?? [];
    }

    public function layananFor(string $question): ?ServiceMatch
    {
        return $this->services[$question] ?? null;
    }

    private function layananUntuk(string $question, string $service): void
    {
        $this->services[$question] = new ServiceMatch(serviceId: 9, service: $service);
    }

    /**
     * Pencarian A palsu. WAJIB ada: FulltextKnowledgeSearch tidak jalan di
     * SQLite, dan layar ini sekarang memeriksa ulang tiap pertanyaan lewat sana.
     *
     * Bawaannya kosong = tidak ada jawaban = pertanyaannya masih celah.
     */
    private function fakeKnowledgeSearch(): void
    {
        $test = $this;

        $this->app->instance(KnowledgeSearch::class, new class($test) implements KnowledgeSearch
        {
            public function __construct(private readonly RecommendationControllerTest $test) {}

            public function cari(string $pertanyaan, int $limit = 5): array
            {
                return $this->test->answersFor($pertanyaan);
            }
        });
    }

    /** @return SearchHit[] */
    public function answersFor(string $question): array
    {
        return $this->answers[$question] ?? [];
    }

    /** Menyatakan EVA kini MAMPU menjawab pertanyaan ini. */
    private function kiniTerjawab(string $question, int $confidence = 95): void
    {
        $this->answers[$question] = [
            new SearchHit(Article::class, 1, 'SOP Reset Password SAP', 'isi', $confidence, 1),
        ];
    }

    private function saranUntuk(string $question, SubjectMatch ...$matches): void
    {
        $this->script[$question] = $matches;
    }

    private function calon(int $subjectId, int $confidence): SubjectMatch
    {
        return new SubjectMatch(
            subjectId: $subjectId,
            subject: $subjectId === 1 ? 'Reset Password' : 'User Locked',
            service: 'SAP',
            subcategory: 'LOGIN SAP',
            issueCategory: 'Access Request',
            confidence: $confidence,
            requiresApproval: false,
            supportLevel: 1,
        );
    }

    private function unanswered(string $question, int $times = 1): void
    {
        foreach (range(1, $times) as $ignored) {
            AnswerLog::create([
                'question' => $question,
                'outcome' => AnswerLog::OUTCOME_NO_ANSWER,
                'confidence' => 0,
            ]);
        }
    }

    private function publishedArticle(int $subjectId): Article
    {
        return Article::create([
            'title' => 'SOP Reset Password SAP',
            'summary' => 'Ringkasan.',
            'body' => 'Langkah mengatur ulang kata sandi SAP.',
            'status' => Article::STATUS_PUBLISHED,
            'is_eva_visible' => true,
            'catalog_subject_id' => $subjectId,
        ]);
    }

    /** @return array<int,array<string,mixed>> */
    private function targets(): array
    {
        return $this->get('/eva/recommendation')->assertOk()->viewData('targets');
    }

    // ---- layar -------------------------------------------------------------

    public function test_halaman_recommendation_tampil(): void
    {
        $this->get('/eva/recommendation')->assertOk();
    }

    // ---- pengelompokan per subject -----------------------------------------

    public function test_pertanyaan_dikelompokkan_di_bawah_subject_tujuannya(): void
    {
        $this->unanswered('cara reset password sap', 3);
        $this->saranUntuk('cara reset password sap', $this->calon(1, 80));

        $targets = $this->targets();

        $this->assertCount(1, $targets);
        $this->assertSame(1, $targets[0]['subject_id']);
        $this->assertSame('Reset Password', $targets[0]['subject']);
        $this->assertSame(1, $targets[0]['total'], 'satu pertanyaan berbeda');
        $this->assertSame(3, $targets[0]['volume'], 'ditanyakan tiga kali');
        $this->assertSame('cara reset password sap', $targets[0]['questions'][0]['question']);
        $this->assertSame(80, $targets[0]['questions'][0]['confidence']);
    }

    public function test_dua_pertanyaan_berbeda_ke_subject_sama_menjadi_satu_baris(): void
    {
        $this->unanswered('cara reset password sap', 2);
        $this->unanswered('lupa sandi sap', 1);
        $this->saranUntuk('cara reset password sap', $this->calon(1, 80));
        $this->saranUntuk('lupa sandi sap', $this->calon(1, 62));

        $targets = $this->targets();

        $this->assertCount(1, $targets, 'satu subject, bukan dua baris');
        $this->assertSame(2, $targets[0]['total']);
        $this->assertSame(3, $targets[0]['volume']);
        $this->assertSame(80, $targets[0]['best_confidence']);
    }

    public function test_subject_belum_bermateri_diurutkan_lebih_dulu(): void
    {
        // Subject 1 sudah ada artikelnya dan ditanyakan JAUH lebih sering;
        // subject 2 belum ada materinya. Yang belum bermateri tetap di atas
        // karena hanya itu yang menghasilkan pekerjaan menulis.
        $this->publishedArticle(1);
        $this->unanswered('cara reset password sap', 9);
        $this->unanswered('akun saya terkunci', 1);
        $this->saranUntuk('cara reset password sap', $this->calon(1, 80));
        $this->saranUntuk('akun saya terkunci', $this->calon(2, 70));

        $targets = $this->targets();

        $this->assertCount(2, $targets);
        $this->assertSame(2, $targets[0]['subject_id']);
        $this->assertFalse($targets[0]['has_material']);
        $this->assertSame(1, $targets[1]['subject_id']);
        $this->assertTrue($targets[1]['has_material']);
    }

    public function test_artikel_draf_tidak_dianggap_menutup_subject(): void
    {
        $this->publishedArticle(1)->update(['status' => Article::STATUS_DRAFT]);
        $this->unanswered('cara reset password sap');
        $this->saranUntuk('cara reset password sap', $this->calon(1, 80));

        $this->assertFalse($this->targets()[0]['has_material']);
    }

    public function test_pertanyaan_tanpa_calon_masuk_daftar_terpisah(): void
    {
        $this->unanswered('klaim tunjangan internet rumah', 4);

        $response = $this->get('/eva/recommendation')->assertOk();

        $this->assertSame([], $response->viewData('targets'));
        $this->assertSame(
            [['question' => 'klaim tunjangan internet rumah', 'count' => 4, 'service' => null]],
            $response->viewData('unrouted'),
        );
    }

    /**
     * "Tanpa saran" bukan lagi satu keranjang.
     *
     * Pertanyaan yang menyebut nama aplikasi tapi tak menemukan subject bukan
     * kegagalan yang sama dengan pertanyaan yang tak dikenali sama sekali. Yang
     * pertama adalah DAFTAR KERJA — layanan itu kekurangan subject atau artikel,
     * dan namanya sudah diketahui. Menyatukan keduanya membuat pekerjaan yang
     * paling jelas ikut tenggelam di daftar buntu.
     */
    public function test_pertanyaan_tanpa_saran_membawa_layanan_bila_aplikasinya_disebut(): void
    {
        $this->unanswered('bagaimana melaporkan kendala di elisa', 3);
        $this->unanswered('klaim tunjangan internet rumah', 1);
        $this->layananUntuk('bagaimana melaporkan kendala di elisa', 'ELISA');

        $response = $this->get('/eva/recommendation')->assertOk();

        $this->assertSame(
            [
                ['question' => 'bagaimana melaporkan kendala di elisa', 'count' => 3, 'service' => 'ELISA'],
                ['question' => 'klaim tunjangan internet rumah', 'count' => 1, 'service' => null],
            ],
            $response->viewData('unrouted'),
        );

        $stats = $response->viewData('stats');
        $this->assertSame(2, $stats['unrouted']);
        $this->assertSame(1, $stats['unrouted_with_service'], 'hanya satu yang aplikasinya diketahui');
    }

    // ---- sejalan dengan Unanswered Questions -------------------------------

    public function test_pertanyaan_yang_disingkirkan_admin_ikut_hilang(): void
    {
        $this->unanswered('cara reset password sap', 2);
        $this->unanswered('akun saya terkunci', 1);
        $this->saranUntuk('cara reset password sap', $this->calon(1, 80));
        $this->saranUntuk('akun saya terkunci', $this->calon(2, 70));

        $this->assertCount(2, $this->targets());

        DismissedQuestion::create([
            'question' => 'cara reset password sap',
            'dismissed_at' => now(),
        ]);

        $targets = $this->targets();

        $this->assertCount(1, $targets, 'yang dihapus di Unanswered tidak boleh hidup di sini');
        $this->assertSame(2, $targets[0]['subject_id']);
    }

    public function test_pertanyaan_yang_kini_bisa_dijawab_eva_ikut_hilang(): void
    {
        $this->unanswered('cara reset password sap', 5);
        $this->saranUntuk('cara reset password sap', $this->calon(1, 80));

        $this->assertCount(1, $this->targets());

        // Materinya baru ditulis: pemeriksaan ulang kini menemukan jawaban.
        $this->kiniTerjawab('cara reset password sap');

        $this->assertSame([], $this->targets());
        $this->assertSame([], $this->get('/eva/recommendation')->viewData('unrouted'));
    }

    public function test_jawaban_di_bawah_ambang_tetap_dianggap_celah(): void
    {
        $this->unanswered('cara reset password sap');
        $this->saranUntuk('cara reset password sap', $this->calon(1, 80));
        $this->kiniTerjawab('cara reset password sap', KnowledgeSearch::MIN_CONFIDENCE - 1);

        $this->assertCount(1, $this->targets(), 'kandidat di bawah ambang bukan jawaban');
    }

    // ---- angka ringkasan ---------------------------------------------------

    public function test_angka_ringkasan_menghitung_subject_bukan_pertanyaan(): void
    {
        $this->publishedArticle(1);
        $this->unanswered('cara reset password sap', 2);
        $this->unanswered('lupa sandi sap', 1);
        $this->unanswered('akun saya terkunci', 1);
        $this->unanswered('klaim tunjangan internet rumah', 1);
        $this->saranUntuk('cara reset password sap', $this->calon(1, 80));
        $this->saranUntuk('lupa sandi sap', $this->calon(1, 62));
        $this->saranUntuk('akun saya terkunci', $this->calon(2, 70));

        $stats = $this->get('/eva/recommendation')->assertOk()->viewData('stats');

        $this->assertSame(4, $stats['questions']);
        $this->assertSame(2, $stats['targets'], 'dua subject tujuan');
        $this->assertSame(1, $stats['without_material'], 'hanya subject 2 yang belum bermateri');
        $this->assertSame(1, $stats['unrouted']);
    }

    // ---- invarian ----------------------------------------------------------

    /**
     * Membuka layar ini tidak boleh menyentuh satu baris pun. Saran yang
     * tersimpan akan membuat `catalog_subject_id` berarti dua hal sekaligus.
     */
    public function test_membuka_layar_tidak_menyimpan_saran_apa_pun(): void
    {
        $this->unanswered('cara reset password sap');
        $this->saranUntuk('cara reset password sap', $this->calon(1, 80));

        $this->get('/eva/recommendation')->assertOk();

        $this->assertNull(AnswerLog::sole()->catalog_subject_id);
        $this->assertSame(0, DB::table('tickets')->count(), 'aturan #4: EVA berhenti di draf');
        $this->assertSame(0, DismissedQuestion::count());
    }

    // ---- bangku uji --------------------------------------------------------

    public function test_bangku_uji_mengembalikan_calon_beserta_keadaan_materinya(): void
    {
        $this->publishedArticle(1);
        $this->saranUntuk('cara reset password sap', $this->calon(1, 80), $this->calon(2, 40));

        $candidates = $this->postJson('/eva/api/recommendation/test', [
            'question' => 'cara reset password sap',
        ])->assertOk()->json('candidates');

        $this->assertCount(2, $candidates);
        $this->assertTrue($candidates[0]['has_material']);
        $this->assertTrue($candidates[0]['is_auto_fill']);
        $this->assertFalse($candidates[1]['has_material']);
        $this->assertFalse($candidates[1]['is_auto_fill']);
    }

    /**
     * Bangku uji harus memakai ATURAN YANG SAMA dengan draf tiket sungguhan.
     *
     * Tanpa ini, admin mengetik "kendala di elisa" dan melihat layar KOSONG —
     * lalu menyimpulkan EVA tidak bisa apa-apa, padahal di widget pertanyaan itu
     * justru berakhir rapi di ELISA › Lainnya. Layar uji yang menampilkan lebih
     * sedikit daripada yang sebenarnya terjadi lebih menyesatkan daripada tidak
     * ada layar uji sama sekali.
     */
    public function test_bangku_uji_menampilkan_layanan_saat_tak_ada_calon(): void
    {
        $this->layananUntuk('kendala di elisa', 'ELISA');

        $hasil = $this->postJson('/eva/api/recommendation/test', [
            'question' => 'kendala di elisa',
        ])->assertOk();

        $this->assertSame([], $hasil->json('candidates'));
        $this->assertSame('ELISA', $hasil->json('service.service'));
    }

    /** Ada calon → "Lainnya" TIDAK menyala, persis seperti di draf sungguhan. */
    public function test_bangku_uji_menyembunyikan_layanan_saat_masih_ada_calon(): void
    {
        $this->saranUntuk('cara reset password sap', $this->calon(1, 80));
        $this->layananUntuk('cara reset password sap', 'SAP');

        $hasil = $this->postJson('/eva/api/recommendation/test', [
            'question' => 'cara reset password sap',
        ])->assertOk();

        $this->assertNotSame([], $hasil->json('candidates'));
        $this->assertNull($hasil->json('service'));
    }

    public function test_bangku_uji_menolak_pertanyaan_kosong(): void
    {
        $this->postJson('/eva/api/recommendation/test', ['question' => ''])->assertStatus(422);
    }
}
