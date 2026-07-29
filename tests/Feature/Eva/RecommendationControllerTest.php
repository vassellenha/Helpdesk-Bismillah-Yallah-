<?php

namespace Tests\Feature\Eva;

use App\Models\Knowledge\AnswerLog;
use App\Models\Knowledge\Article;
use App\Services\Knowledge\SubjectMatch;
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

    protected function setUp(): void
    {
        parent::setUp();

        // CatalogOptions menyimpan katalog di cache; tanpa dibersihkan, tes yang
        // jalan belakangan memakai katalog milik tes sebelumnya.
        Cache::flush();

        $this->seedCatalog();
        $this->fakeSubjectSearch();

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
        });
    }

    /** @return SubjectMatch[] */
    public function scriptFor(string $question): array
    {
        return $this->script[$question] ?? [];
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

    // ---- layar -------------------------------------------------------------

    public function test_halaman_recommendation_tampil(): void
    {
        $this->get('/eva/recommendation')->assertOk();
    }

    public function test_pertanyaan_gagal_muncul_berikut_calon_tujuannya(): void
    {
        $this->unanswered('cara reset password sap');
        $this->saranUntuk('cara reset password sap', $this->calon(1, 80));

        $rows = $this->get('/eva/recommendation')->assertOk()->viewData('rows');

        $this->assertCount(1, $rows);
        $this->assertSame('cara reset password sap', $rows[0]['question']);
        $this->assertSame(1, $rows[0]['candidates'][0]['subject_id']);
        $this->assertSame(80, $rows[0]['candidates'][0]['confidence']);
    }

    /** Layar ini hanya soal pertanyaan yang GAGAL — yang terjawab bukan urusannya. */
    public function test_pertanyaan_yang_terjawab_tidak_ikut(): void
    {
        AnswerLog::create([
            'question' => 'cara reset password sap',
            'outcome' => AnswerLog::OUTCOME_ANSWERED,
            'confidence' => 90,
        ]);

        $this->assertSame([], $this->get('/eva/recommendation')->viewData('rows'));
    }

    /** Draf tiket juga pertanyaan gagal — EVA menyerah, cuma dengan sopan. */
    public function test_draf_tiket_dihitung_sebagai_pertanyaan_gagal(): void
    {
        AnswerLog::create([
            'question' => 'printer tidak bisa cetak',
            'outcome' => AnswerLog::OUTCOME_TICKET_DRAFT,
            'confidence' => 20,
        ]);

        $this->assertCount(1, $this->get('/eva/recommendation')->viewData('rows'));
    }

    // ---- ambang ------------------------------------------------------------

    /**
     * Ambang diuji pada nilai PERSIS: MIN_CONFIDENCE masuk isi-otomatis,
     * satu poin di bawahnya tidak. Tes yang memakai 90 vs 10 akan tetap hijau
     * walau garisnya digeser lima poin.
     */
    public function test_ambang_isi_otomatis_diuji_pada_nilai_persis(): void
    {
        $this->unanswered('tepat di ambang');
        $this->saranUntuk('tepat di ambang', $this->calon(1, SubjectSearch::MIN_CONFIDENCE));

        $this->unanswered('satu poin di bawah');
        $this->saranUntuk('satu poin di bawah', $this->calon(1, SubjectSearch::MIN_CONFIDENCE - 1));

        $this->unanswered('tanpa calon sama sekali');

        $stats = $this->get('/eva/recommendation')->viewData('stats');

        $this->assertSame(3, $stats['questions']);
        $this->assertSame(1, $stats['auto'], 'hanya yang PERSIS di ambang boleh mengisi otomatis');
        $this->assertSame(1, $stats['weak']);
        $this->assertSame(1, $stats['none']);
    }

    public function test_calon_menandai_dirinya_layak_isi_otomatis(): void
    {
        $this->unanswered('cara reset password sap');
        $this->saranUntuk('cara reset password sap',
            $this->calon(1, SubjectSearch::MIN_CONFIDENCE),
            $this->calon(2, SubjectSearch::MIN_CONFIDENCE - 1),
        );

        $candidates = $this->get('/eva/recommendation')->viewData('rows')[0]['candidates'];

        $this->assertTrue($candidates[0]['is_auto_fill']);
        $this->assertFalse($candidates[1]['is_auto_fill']);
    }

    // ---- materi ------------------------------------------------------------

    public function test_subject_yang_punya_artikel_terbit_ditandai_sudah_bermateri(): void
    {
        $this->publishedArticle(subjectId: 1);

        $this->unanswered('cara reset password sap');
        $this->saranUntuk('cara reset password sap',
            $this->calon(1, 80),
            $this->calon(2, 40),
        );

        $candidates = $this->get('/eva/recommendation')->viewData('rows')[0]['candidates'];

        $this->assertTrue($candidates[0]['has_material']);
        $this->assertFalse($candidates[1]['has_material'], 'subject tanpa materi tetap celah');
    }

    /**
     * Materi yang masih DRAF belum menutup apa pun. Kalau gerbang answerable()
     * dilewati di sini, celah materi yang sesungguhnya menghilang dari daftar
     * tugas menulis — persis pekerjaan yang paling perlu terlihat.
     */
    public function test_artikel_draf_tidak_dianggap_menutup_subject(): void
    {
        $this->publishedArticle(subjectId: 1)->update(['status' => Article::STATUS_DRAFT]);

        $this->unanswered('cara reset password sap');
        $this->saranUntuk('cara reset password sap', $this->calon(1, 80));

        $candidates = $this->get('/eva/recommendation')->viewData('rows')[0]['candidates'];

        $this->assertFalse($candidates[0]['has_material']);
    }

    // ---- celah materi ------------------------------------------------------

    /** Subject yang berulang jadi tujuan tanpa materi naik ke puncak daftar tulis. */
    public function test_celah_materi_diurutkan_dari_yang_paling_sering(): void
    {
        $this->unanswered('akun sap terkunci', times: 3);
        $this->saranUntuk('akun sap terkunci', $this->calon(2, 70));

        $this->unanswered('lupa password sap');
        $this->saranUntuk('lupa password sap', $this->calon(1, 70));

        $this->unanswered('password sap tidak bisa dipakai');
        $this->saranUntuk('password sap tidak bisa dipakai', $this->calon(1, 65));

        $gaps = $this->get('/eva/recommendation')->viewData('gaps');

        $this->assertCount(2, $gaps);
        $this->assertSame(1, $gaps[0]['subject_id'], 'dua pertanyaan berbeda menuju subject 1');
        $this->assertSame(2, $gaps[0]['total']);
        $this->assertSame(2, $gaps[1]['subject_id']);
        $this->assertSame(1, $gaps[1]['total'], 'tiga kali pertanyaan SAMA tetap satu celah');
    }

    public function test_subject_yang_sudah_bermateri_tidak_masuk_daftar_celah(): void
    {
        $this->publishedArticle(subjectId: 1);

        $this->unanswered('cara reset password sap');
        $this->saranUntuk('cara reset password sap', $this->calon(1, 80));

        $this->assertSame([], $this->get('/eva/recommendation')->viewData('gaps'));
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
    }

    // ---- bangku uji --------------------------------------------------------

    public function test_bangku_uji_mengembalikan_calon_untuk_pertanyaan_bebas(): void
    {
        $this->saranUntuk('kenapa akun saya terkunci', $this->calon(2, 62));

        $this->postJson('/eva/api/recommendation/test', ['question' => 'kenapa akun saya terkunci'])
            ->assertOk()
            ->assertJsonPath('question', 'kenapa akun saya terkunci')
            ->assertJsonPath('candidates.0.subject_id', 2)
            ->assertJsonPath('candidates.0.confidence', 62)
            ->assertJsonPath('candidates.0.has_material', false);
    }

    /** Bangku uji pun tidak mencatat apa pun — mengetik di sini bukan bertanya ke EVA. */
    public function test_bangku_uji_tidak_mencatat_pertanyaan(): void
    {
        $this->saranUntuk('kenapa akun saya terkunci', $this->calon(2, 62));

        $this->postJson('/eva/api/recommendation/test', ['question' => 'kenapa akun saya terkunci'])->assertOk();

        $this->assertSame(0, AnswerLog::count());
    }

    public function test_bangku_uji_mewajibkan_pertanyaan(): void
    {
        $this->postJson('/eva/api/recommendation/test', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('question');
    }

    public function test_bangku_uji_menolak_pertanyaan_kepanjangan(): void
    {
        $this->postJson('/eva/api/recommendation/test', ['question' => str_repeat('a', 501)])
            ->assertStatus(422)
            ->assertJsonValidationErrors('question');
    }
}
