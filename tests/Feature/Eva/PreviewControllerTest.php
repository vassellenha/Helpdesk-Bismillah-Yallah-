<?php

namespace Tests\Feature\Eva;

use App\Models\Knowledge\AnswerLog;
use App\Models\Knowledge\AnswerRating;
use App\Models\Knowledge\Article;
use App\Models\Knowledge\Conversation;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Knowledge\KnowledgeSearch;
use App\Services\Knowledge\SearchHit;
use App\Services\Knowledge\SubjectMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\ActsAsEvaAdmin;
use Tests\TestCase;

/**
 * EVA Preview lewat HTTP — jalur yang benar-benar dipakai komponen.
 *
 * Yang dijaga di sini bukan perilaku menjawabnya (itu milik EvaResponderTest),
 * melainkan hal-hal yang hanya bisa salah di lapisan controller: percakapan
 * dipakai ulang atau justru beranak, urutan giliran, sekali-nilai-per-jawaban,
 * dan — yang paling penting — bahwa aturan #4 dipegang: EVA berhenti di draf
 * dan tidak pernah menulis satu baris pun ke tabel tiket.
 */
final class PreviewControllerTest extends TestCase
{
    use ActsAsEvaAdmin;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        SubjectMatcher::forget();

        // CurrentActor mencari persona ini lewat email — tanpa barisnya, setiap
        // endpoint Preview gagal keras sebelum sampai ke logikanya.
        User::factory()->create(['name' => 'Andi Pratama', 'email' => 'andi.pratama@adhi.co.id', 'nip' => '19950418102']);

        $this->seedCatalog();
        $this->searchReturns();

        $this->actingAsEvaAdmin();
    }

    private function seedCatalog(): void
    {
        DB::table('issue_categories')->insert(['id' => 1, 'name' => 'Access Request']);
        DB::table('service_catalog_services')->insert(['id' => 1, 'name' => 'SAP']);
        DB::table('service_catalog_subcategories')->insert(['id' => 1, 'service_id' => 1, 'name' => 'LOGIN SAP']);
        DB::table('service_catalog_subjects')->insert([[
            'id' => 1, 'issue_category_id' => 1, 'service_id' => 1, 'subcategory_id' => 1,
            'name' => 'Password Expired', 'requires_approval' => false,
            'support_level' => 1, 'is_active' => true,
        ]]);
    }

    private function searchReturns(SearchHit ...$hits): void
    {
        $search = new class implements KnowledgeSearch
        {
            /** @var SearchHit[] */
            public array $hits = [];

            public function cari(string $pertanyaan, int $limit = 5): array
            {
                return $this->hits;
            }
        };

        $search->hits = $hits;
        $this->app->instance(KnowledgeSearch::class, $search);
    }

    private function confidentHit(): SearchHit
    {
        return new SearchHit(
            sourceType: Article::class,
            sourceId: 7,
            title: 'SOP Reset Password SAP',
            answer: 'Buka portal SAP lalu ganti password Anda.',
            confidence: 90,
            catalogSubjectId: 1,
        );
    }

    public function test_halaman_preview_tampil(): void
    {
        $this->get('/eva/preview')->assertOk();
    }

    // ---- ask --------------------------------------------------------------

    public function test_bertanya_membuka_percakapan_dan_mencatat_dua_giliran(): void
    {
        $this->searchReturns($this->confidentHit());

        $response = $this->postJson('/eva/api/preview/ask', ['question' => 'cara reset password sap'])
            ->assertOk()
            ->assertJsonPath('type', 'answer')
            ->assertJsonPath('hit.confidence', 90);

        $conversation = Conversation::sole();
        $this->assertSame($conversation->id, $response->json('conversation_id'));

        $turns = $conversation->turns()->orderBy('ordinal')->get();
        $this->assertSame(['user', 'eva'], $turns->pluck('role')->all());
        $this->assertSame([0, 1], $turns->pluck('ordinal')->all());
        $this->assertSame('cara reset password sap', $turns[0]->message);
        $this->assertSame('Buka portal SAP lalu ganti password Anda.', $turns[1]->message);
    }

    /**
     * Melanjutkan percakapan yang sama tidak boleh membuat percakapan baru, dan
     * ordinal-nya harus menyambung. Kalau ordinal mengulang dari 0, Log
     * Percakapan menampilkan giliran dengan urutan acak.
     */
    public function test_melanjutkan_percakapan_menyambung_urutan_giliran(): void
    {
        $this->searchReturns($this->confidentHit());

        $first = $this->postJson('/eva/api/preview/ask', ['question' => 'pertanyaan pertama']);
        $conversationId = $first->json('conversation_id');

        $this->postJson('/eva/api/preview/ask', [
            'question' => 'pertanyaan kedua',
            'conversation_id' => $conversationId,
        ])->assertOk()->assertJsonPath('conversation_id', $conversationId);

        $this->assertSame(1, Conversation::count(), 'percakapan tidak boleh beranak');
        $this->assertSame(
            [0, 1, 2, 3],
            Conversation::find($conversationId)->turns()->orderBy('ordinal')->pluck('ordinal')->all(),
        );
    }

    /** Jawaban yang menahan diri ditandai, supaya komponen bisa melunakkan bahasanya. */
    public function test_jawaban_menahan_diri_ditandai_ke_komponen(): void
    {
        $this->searchReturns(new SearchHit(
            sourceType: Article::class,
            sourceId: 7,
            title: 'SOP',
            answer: 'Coba langkah ini.',
            confidence: KnowledgeSearch::HEDGE_CONFIDENCE - 1,
            catalogSubjectId: 1,
        ));

        $this->postJson('/eva/api/preview/ask', ['question' => 'cara reset password sap'])
            ->assertOk()
            ->assertJsonPath('is_hedged', true);
    }

    public function test_bertanya_mewajibkan_pertanyaan(): void
    {
        $this->postJson('/eva/api/preview/ask', [])->assertStatus(422);
    }

    public function test_bertanya_menolak_pertanyaan_terlalu_panjang(): void
    {
        $this->postJson('/eva/api/preview/ask', ['question' => str_repeat('a', 501)])->assertStatus(422);
    }

    public function test_bertanya_menolak_percakapan_tak_dikenal(): void
    {
        $this->postJson('/eva/api/preview/ask', [
            'question' => 'halo',
            'conversation_id' => 9999,
        ])->assertStatus(422);
    }

    // ---- rate -------------------------------------------------------------

    public function test_memberi_bintang_tersimpan(): void
    {
        $log = $this->askAndGetLogId();

        $this->postJson('/eva/api/preview/rate', ['answer_log_id' => $log, 'stars' => 5])
            ->assertOk()
            ->assertJsonPath('rated', true);

        $this->assertSame(5, AnswerRating::sole()->stars);
    }

    /**
     * Sekali nilai per jawaban. Percobaan kedua harus ditolak dengan pesan yang
     * jelas — BUKAN diam-diam menimpa nilai pertama, dan bukan pula 500.
     */
    public function test_menilai_dua_kali_ditolak_dengan_pesan_jelas(): void
    {
        $log = $this->askAndGetLogId();

        $this->postJson('/eva/api/preview/rate', ['answer_log_id' => $log, 'stars' => 5])->assertOk();

        $this->postJson('/eva/api/preview/rate', ['answer_log_id' => $log, 'stars' => 1])
            ->assertStatus(409)
            ->assertJsonPath('message', 'Jawaban ini sudah Anda nilai sebelumnya.');

        $this->assertSame(1, AnswerRating::count());
        $this->assertSame(5, AnswerRating::sole()->stars, 'nilai pertama tidak boleh tertimpa');
    }

    public function test_bintang_di_luar_rentang_ditolak(): void
    {
        $log = $this->askAndGetLogId();

        $this->postJson('/eva/api/preview/rate', ['answer_log_id' => $log, 'stars' => 0])->assertStatus(422);
        $this->postJson('/eva/api/preview/rate', ['answer_log_id' => $log, 'stars' => 6])->assertStatus(422);
    }

    // ---- ticket draft: aturan #4 -----------------------------------------

    /**
     * Aturan #4 — EVA MEREKOMENDASIKAN, tidak pernah membuat tiket. Endpoint
     * ini boleh menyiapkan draf sedetail apa pun, tapi tabel tickets harus
     * tetap kosong sesudahnya.
     */
    public function test_draf_tiket_tidak_pernah_menulis_tiket(): void
    {
        $before = Ticket::count();
        $log = $this->askAndGetLogId('reset password sap');

        $this->postJson('/eva/api/preview/ticket-draft', [
            'answer_log_id' => $log,
            'question' => 'reset password sap',
        ])->assertOk()
            ->assertJsonPath('draft.description', 'reset password sap')
            ->assertJsonPath('draft.subject.subject', 'Password Expired');

        $this->assertSame($before, Ticket::count(), 'aturan #4: EVA berhenti di draf');
    }

    public function test_draf_tiket_menandai_log_dan_percakapan(): void
    {
        $log = $this->askAndGetLogId('reset password sap');

        $this->postJson('/eva/api/preview/ticket-draft', [
            'answer_log_id' => $log,
            'question' => 'reset password sap',
        ])->assertOk();

        $this->assertSame(AnswerLog::OUTCOME_TICKET_DRAFT, AnswerLog::find($log)->outcome);
        $this->assertSame(Conversation::OUTCOME_TICKET, Conversation::sole()->outcome);
    }

    /**
     * Subject TEBAKAN tidak boleh mendarat di kolom catalog_subject_id, yang
     * sudah berarti "subject artikel yang menjawab". Satu kolom dengan dua arti
     * akan merusak hitungan Coverage tanpa gejala apa pun.
     */
    public function test_draf_tiket_tidak_menulis_subject_tebakan_ke_log(): void
    {
        $log = $this->askAndGetLogId('reset password sap');

        $this->postJson('/eva/api/preview/ticket-draft', [
            'answer_log_id' => $log,
            'question' => 'reset password sap',
        ])->assertOk();

        $this->assertNull(AnswerLog::find($log)->catalog_subject_id);
    }

    public function test_draf_tiket_menolak_log_tak_dikenal(): void
    {
        $this->postJson('/eva/api/preview/ticket-draft', [
            'answer_log_id' => 9999,
            'question' => 'halo',
        ])->assertStatus(422);
    }

    /** Pertanyaan gagal → log no_answer, siap dipakai Unanswered Questions. */
    private function askAndGetLogId(string $question = 'cara reset password sap'): int
    {
        return $this->postJson('/eva/api/preview/ask', ['question' => $question])->json('answer_log_id');
    }
}
