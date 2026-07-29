<?php

declare(strict_types=1);

namespace Tests\Feature\Eva;

use App\Models\Knowledge\AnswerLog;
use App\Models\Knowledge\AnswerRating;
use App\Models\Knowledge\Article;
use App\Models\Knowledge\Conversation;
use App\Models\Ticket;
use App\Models\User;
use App\Support\Eva\AssistantWidget;
use App\Services\Knowledge\KnowledgeSearch;
use App\Services\Knowledge\SearchHit;
use App\Services\Knowledge\SubjectMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\ActsAsEvaAdmin;
use Tests\TestCase;

/**
 * Widget EVA di portal — permukaan karyawan.
 *
 * Yang dijaga di sini adalah hal-hal yang MEMBEDAKANNYA dari EVA Preview,
 * bukan perilaku menjawabnya (itu milik EvaResponderTest) maupun mekanika
 * percakapan (PreviewControllerTest):
 *
 *   1. Endpoint-nya TERBUKA — tamu tanpa identitas tetap dilayani. Kalau suatu
 *      saat ada yang memindahkannya ke dalam grup `eva.access`, tes ini gagal
 *      sebelum ada karyawan yang menemukannya lewat layar 401.
 *   2. Catatan penilaian benar-benar mendarat di kb_answer_ratings. Kolom
 *      `reason`/`comment` sudah lama ada dan sudah lama divalidasi, tapi belum
 *      pernah ada satu pun pengirim — panel tanggapan di Rating & Feedback
 *      selama ini membaca kolom yang selalu kosong.
 *   3. Aturan #4 tetap dipegang dari pintu masuk yang baru ini.
 */
final class AssistantWidgetTest extends TestCase
{
    use ActsAsEvaAdmin;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        SubjectMatcher::forget();

        // CurrentActor mencari persona ini lewat email — tanpa barisnya, setiap
        // endpoint widget gagal keras sebelum sampai ke logikanya.
        User::factory()->create(['name' => 'Andi Pratama', 'email' => 'andi.pratama@adhi.co.id']);

        $this->seedCatalog();
        $this->searchReturns();
    }

    public function test_portal_memuat_widget_beserta_meta_csrf(): void
    {
        $response = $this->get(route('portal.index'));

        $response->assertOk();
        $response->assertSee('data-react="EvaAssistantWidget"', false);
        // apiFetch membaca token dari meta ini. Tanpa baris itu setiap
        // pertanyaan dibalas 419 dengan pesan yang tidak menyebut CSRF.
        $response->assertSee('name="csrf-token"', false);
    }

    /**
     * Angka keyakinan dan ambangnya tidak boleh sampai ke halaman karyawan.
     *
     * Keduanya alat kerja admin — gunanya membandingkan satu jawaban dengan
     * jawaban lain untuk memutuskan materi mana yang perlu diperbaiki. Di
     * widget ia cuma angka yang tak bisa ditindaklanjuti, dan mudah salah
     * dibaca sebagai "jawabannya baru benar 97%".
     *
     * Diuji pada PROP-nya, bukan pada teks yang dirender: markup widget lahir
     * di React, jadi kalimatnya memang tidak ada di HTML awal. Yang bisa bocor
     * lewat HTML justru angkanya sendiri, dan itulah yang dijaga di sini.
     */
    public function test_ambang_keyakinan_tidak_dikirim_ke_widget(): void
    {
        $props = AssistantWidget::props();

        $this->assertArrayNotHasKey('thresholds', $props);
        $this->assertSame(['endpoints', 'offsetBottom'], array_keys($props));

        $this->get(route('portal.index'))
            ->assertOk()
            ->assertDontSee('min_confidence')
            ->assertDontSee('hedge_confidence');
    }

    /** EVA Preview — layar ADMIN — justru WAJIB tetap menampilkannya. */
    public function test_eva_preview_tetap_menerima_ambang_keyakinan(): void
    {
        $this->actingAsEvaAdmin();

        $this->get(route('eva.preview'))
            ->assertOk()
            ->assertSee('min_confidence');
    }

    public function test_tamu_tanpa_identitas_tetap_dilayani(): void
    {
        // Justru INI yang membedakannya dari konsol. `eva/api/preview/ask`
        // ditolak 401 di lingkungan testing; widget tidak boleh ikut ditolak,
        // karena karyawan di portal memang belum punya identitas EVA.
        $this->postJson(route('eva.assistant.ask'), ['question' => 'cara reset password SAP'])
            ->assertOk()
            ->assertJsonPath('type', 'answer');

        $this->postJson(route('eva.preview.ask'), ['question' => 'cara reset password SAP'])
            ->assertStatus(401);
    }

    public function test_pertanyaan_tercatat_di_log_jawaban(): void
    {
        $this->postJson(route('eva.assistant.ask'), ['question' => 'cara reset password SAP'])
            ->assertOk();

        // Tanpa baris ini, Unanswered Questions dan Coverage Dashboard
        // melaporkan angka terlalu kecil tanpa petunjuk apa pun.
        $this->assertSame(1, AnswerLog::count());
        $this->assertSame('cara reset password SAP', AnswerLog::sole()->question);
    }

    public function test_percakapan_disambung_bukan_dibuat_ulang(): void
    {
        $first = $this->postJson(route('eva.assistant.ask'), ['question' => 'cara reset password SAP']);
        $conversationId = $first->json('conversation_id');

        $this->postJson(route('eva.assistant.ask'), [
            'question' => 'lalu bagaimana kalau gagal',
            'conversation_id' => $conversationId,
        ])->assertOk()->assertJsonPath('conversation_id', $conversationId);

        $this->assertSame(1, Conversation::count());
        $this->assertSame(
            [0, 1, 2, 3],
            Conversation::find($conversationId)->turns()->orderBy('ordinal')->pluck('ordinal')->all(),
        );
    }

    public function test_percakapan_milik_orang_lain_tidak_bisa_disisipi(): void
    {
        $orang_lain = User::factory()->create(['email' => 'bukan.andi@adhi.co.id']);
        $milikOrangLain = Conversation::create([
            'user_id' => $orang_lain->id,
            'requester_name' => $orang_lain->name,
            'outcome' => Conversation::OUTCOME_OPEN,
            'started_at' => now(),
        ]);

        $this->postJson(route('eva.assistant.ask'), [
            'question' => 'cara reset password SAP',
            'conversation_id' => $milikOrangLain->id,
        ])->assertStatus(404);

        $this->assertSame(0, $milikOrangLain->turns()->count());
    }

    public function test_bintang_tersimpan_dan_tidak_bisa_dinilai_dua_kali(): void
    {
        $logId = $this->postJson(route('eva.assistant.ask'), ['question' => 'cara reset password SAP'])
            ->json('answer_log_id');

        $this->postJson(route('eva.assistant.rate'), ['answer_log_id' => $logId, 'stars' => 2])
            ->assertOk()
            ->assertJsonPath('rated', true);

        $this->postJson(route('eva.assistant.rate'), ['answer_log_id' => $logId, 'stars' => 5])
            ->assertStatus(409);

        $this->assertSame(1, AnswerRating::count());
        $this->assertSame(2, AnswerRating::sole()->stars);
    }

    public function test_catatan_menempel_pada_penilaian_yang_sudah_ada(): void
    {
        $logId = $this->postJson(route('eva.assistant.ask'), ['question' => 'cara reset password SAP'])
            ->json('answer_log_id');

        $this->postJson(route('eva.assistant.rate'), ['answer_log_id' => $logId, 'stars' => 2])->assertOk();

        $this->postJson(route('eva.assistant.note'), [
            'answer_log_id' => $logId,
            'reason' => 'Langkahnya kurang lengkap',
            'comment' => 'Bagian verifikasi OTP-nya tidak dijelaskan.',
        ])->assertOk()->assertJsonPath('noted', true);

        // Baris penilaiannya TETAP satu — catatan menyempurnakan baris yang
        // sudah ada, bukan melahirkan penilaian kedua yang akan ditolak unique.
        $this->assertSame(1, AnswerRating::count());

        $rating = AnswerRating::sole();
        $this->assertSame(2, $rating->stars, 'Catatan tidak boleh mengubah bintang.');
        $this->assertSame('Langkahnya kurang lengkap', $rating->reason);
        $this->assertSame('Bagian verifikasi OTP-nya tidak dijelaskan.', $rating->comment);
    }

    public function test_catatan_tanpa_penilaian_ditolak(): void
    {
        $logId = $this->postJson(route('eva.assistant.ask'), ['question' => 'cara reset password SAP'])
            ->json('answer_log_id');

        $this->postJson(route('eva.assistant.note'), [
            'answer_log_id' => $logId,
            'comment' => 'catatan tanpa bintang',
        ])->assertStatus(409);

        $this->assertSame(0, AnswerRating::count());
    }

    public function test_draf_tiket_tidak_menulis_satu_baris_pun_ke_tabel_tiket(): void
    {
        $this->searchReturns(null);

        $ask = $this->postJson(route('eva.assistant.ask'), ['question' => 'printer lantai 7 macet total']);
        $ask->assertOk()->assertJsonPath('type', 'no_answer');

        $sebelum = Ticket::count();

        $this->postJson(route('eva.assistant.ticket-draft'), [
            'answer_log_id' => $ask->json('answer_log_id'),
            'question' => 'printer lantai 7 macet total',
        ])->assertOk()->assertJsonPath('draft.description', 'printer lantai 7 macet total');

        // Aturan #4: EVA hanya merekomendasikan. Nomor tiket terbit setelah
        // karyawan mengirim sendiri di form Buat Tiket.
        $this->assertSame($sebelum, Ticket::count());
        $this->assertSame(AnswerLog::OUTCOME_TICKET_DRAFT, AnswerLog::sole()->outcome);
    }

    public function test_pertanyaan_kosong_dibalas_json_bukan_html(): void
    {
        // Pola `*/api/*` di bootstrap/app.php yang mengurus ini. Path widget
        // memang mengandung segmen /api/ justru karena itu — tanpa segmennya,
        // error validasi dibalas HTML dan apiFetch gagal memparsenya.
        $this->postJson(route('eva.assistant.ask'), ['question' => ''])
            ->assertStatus(422)
            ->assertJsonValidationErrors('question');
    }

    /** Pencarian A dipalsukan lewat interface-nya — sama seperti PreviewControllerTest. */
    private function searchReturns(?SearchHit $hit = null): void
    {
        if (func_num_args() === 0) {
            $hit = new SearchHit(
                sourceType: 'article',
                sourceId: 1,
                title: 'SOP Reset Password SAP',
                answer: 'Buka portal SAP, pilih Forgot Password, lalu ikuti verifikasinya.',
                confidence: 90,
                catalogSubjectId: null,
            );
        }

        $this->instance(KnowledgeSearch::class, new class($hit) implements KnowledgeSearch
        {
            public function __construct(private readonly ?SearchHit $hit) {}

            public function cari(string $pertanyaan, int $limit = 5): array
            {
                return $this->hit === null ? [] : [$this->hit];
            }
        });
    }

    /**
     * Katalog minimal supaya SubjectMatcher punya sesuatu untuk dicocokkan.
     * Ditulis lewat DB langsung — tabelnya milik tim, dan tes ini tidak berhak
     * mengasumsikan ada factory untuknya.
     */
    private function seedCatalog(): void
    {
        $issueCategoryId = DB::table('issue_categories')->insertGetId([
            'name' => 'Incident', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $serviceId = DB::table('service_catalog_services')->insertGetId([
            'name' => 'SAP', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $subcategoryId = DB::table('service_catalog_subcategories')->insertGetId([
            'service_id' => $serviceId, 'name' => 'Akun', 'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('service_catalog_subjects')->insert([
            'issue_category_id' => $issueCategoryId,
            'service_id' => $serviceId,
            'subcategory_id' => $subcategoryId,
            'name' => 'Reset Password',
            'requires_approval' => false,
            'support_level' => 1,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
