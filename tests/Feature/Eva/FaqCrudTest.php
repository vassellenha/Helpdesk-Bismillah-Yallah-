<?php

namespace Tests\Feature\Eva;

use App\Models\Knowledge\AnswerLog;
use App\Models\Knowledge\Faq;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\ActsAsEvaAdmin;
use Tests\TestCase;

/**
 * Manage FAQ — satu-satunya materi yang ditulis LANGSUNG di konsol EVA.
 *
 * Aturan #2 dikunci di sini: FAQ tidak punya draf/review, jadi FAQ yang baru
 * disimpan sudah bisa dipakai EVA menjawab pada detik itu juga. Kalau kelak ada
 * yang menambahkan status atau alur persetujuan, `test_faq_baru_langsung_bisa_
 * dipakai_menjawab` yang jatuh duluan — bukan pengguna yang menunggu
 * jawabannya muncul entah kapan.
 *
 * Gerbangnya cuma satu: `is_eva_visible`. Karena hanya itu, ia harus benar-
 * benar menutup — memeriksa balasan JSON saja tidak cukup, tesnya ikut
 * memeriksa `Faq::answerable()`.
 */
final class FaqCrudTest extends TestCase
{
    use ActsAsEvaAdmin;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        User::factory()->create(['name' => 'Marcell Laforteza', 'email' => 'marcell.laforteza@adhi.co.id']);
        $this->seedCatalog();

        $this->actingAsEvaAdmin();
    }

    private function seedCatalog(): void
    {
        DB::table('issue_categories')->insert(['id' => 1, 'name' => 'Access Request']);
        DB::table('service_catalog_services')->insert(['id' => 1, 'name' => 'SAP']);
        DB::table('service_catalog_subcategories')->insert([
            ['id' => 1, 'service_id' => 1, 'name' => 'LOGIN SAP'],
        ]);
        DB::table('service_catalog_subjects')->insert([[
            'id' => 1, 'issue_category_id' => 1, 'service_id' => 1,
            'subcategory_id' => 1, 'name' => 'Reset Password',
            'requires_approval' => false, 'support_level' => 1, 'is_active' => true,
        ]]);
    }

    /** @param array<string,mixed> $overrides */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'question' => 'Bagaimana cara reset password SAP?',
            'answer' => 'Buka SAP GUI lalu pilih menu ubah password.',
            'catalog_subject_id' => 1,
            'is_eva_visible' => true,
            'tags' => 'sap,password',
        ], $overrides);
    }

    private function faq(array $attributes = []): Faq
    {
        return Faq::create(array_merge([
            'question' => 'Bagaimana cara reset password SAP?',
            'answer' => 'Buka SAP GUI lalu pilih menu ubah password.',
            'is_eva_visible' => true,
        ], $attributes));
    }

    // ---- layar -------------------------------------------------------------

    public function test_halaman_faq_tampil(): void
    {
        $this->get('/eva/faq')->assertOk();
    }

    public function test_kartu_statistik_dihitung_dari_data_nyata(): void
    {
        $this->faq(['catalog_subject_id' => 1]);
        $this->faq(['question' => 'Apa itu SILO?', 'is_eva_visible' => false]);

        $stats = $this->get('/eva/faq')->assertOk()->viewData('stats');

        $this->assertSame(2, $stats['total']);
        $this->assertSame(1, $stats['eva_visible']);
        $this->assertSame(1, $stats['unlinked'], 'FAQ tanpa subject katalog adalah pekerjaan yang tertinggal');
    }

    // ---- membuat -----------------------------------------------------------

    public function test_faq_baru_tersimpan_dan_dibalas_201(): void
    {
        $this->postJson('/eva/api/faqs', $this->payload())
            ->assertStatus(201)
            ->assertJsonPath('question', 'Bagaimana cara reset password SAP?')
            ->assertJsonPath('catalog_subject_id', 1)
            ->assertJsonPath('subject_name', 'Reset Password')
            ->assertJsonPath('author_name', 'Marcell Laforteza');

        $this->assertSame(1, Faq::count());
    }

    /**
     * Inti aturan #2. FAQ tidak melewati draf, jadi tidak ada jeda antara
     * "disimpan" dan "bisa menjawab".
     */
    public function test_faq_baru_langsung_bisa_dipakai_menjawab(): void
    {
        $this->postJson('/eva/api/faqs', $this->payload())->assertStatus(201);

        $this->assertSame(1, Faq::answerable()->count());
    }

    public function test_penulisnya_dicatat(): void
    {
        $this->postJson('/eva/api/faqs', $this->payload())->assertStatus(201);

        $this->assertSame(
            User::where('email', 'marcell.laforteza@adhi.co.id')->value('id'),
            Faq::sole()->author_id,
        );
    }

    public function test_faq_bisa_dibuat_tanpa_subject_katalog(): void
    {
        $this->postJson('/eva/api/faqs', $this->payload(['catalog_subject_id' => null]))
            ->assertStatus(201)
            ->assertJsonPath('subject_name', null);
    }

    // ---- validasi ----------------------------------------------------------

    public function test_pertanyaan_dan_jawaban_wajib(): void
    {
        $this->postJson('/eva/api/faqs', ['is_eva_visible' => true])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['question', 'answer']);

        $this->assertSame(0, Faq::count());
    }

    public function test_gerbang_tayang_wajib_disebut_eksplisit(): void
    {
        $payload = $this->payload();
        unset($payload['is_eva_visible']);

        $this->postJson('/eva/api/faqs', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('is_eva_visible');
    }

    /**
     * Subject katalog yang tidak ada ditolak, bukan disimpan jadi FK
     * menggantung yang membuat Coverage menghitung subject hantu.
     */
    public function test_subject_katalog_asing_ditolak(): void
    {
        $this->postJson('/eva/api/faqs', $this->payload(['catalog_subject_id' => 999]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('catalog_subject_id');

        $this->assertSame(0, Faq::count());
    }

    public function test_pertanyaan_kepanjangan_ditolak(): void
    {
        $this->postJson('/eva/api/faqs', $this->payload(['question' => str_repeat('a', 501)]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('question');
    }

    // ---- menyunting --------------------------------------------------------

    public function test_faq_bisa_disunting(): void
    {
        $faq = $this->faq();

        $this->putJson("/eva/api/faqs/{$faq->id}", $this->payload([
            'answer' => 'Hubungi Helpdesk di ext. 1234.',
        ]))->assertOk()->assertJsonPath('answer', 'Hubungi Helpdesk di ext. 1234.');

        $this->assertSame('Hubungi Helpdesk di ext. 1234.', $faq->fresh()->answer);
    }

    /** Menyunting tidak mengganti penulis aslinya. */
    public function test_menyunting_tidak_mengganti_penulis(): void
    {
        $penulisLain = User::factory()->create(['name' => 'Penulis Lama']);
        $faq = $this->faq(['author_id' => $penulisLain->id]);

        $this->putJson("/eva/api/faqs/{$faq->id}", $this->payload())->assertOk();

        $this->assertSame($penulisLain->id, $faq->fresh()->author_id);
    }

    public function test_menyunting_faq_yang_tidak_ada_membalas_404(): void
    {
        $this->putJson('/eva/api/faqs/999', $this->payload())->assertNotFound();
    }

    // ---- gerbang tayang ----------------------------------------------------

    public function test_toggle_menyembunyikan_faq_dari_eva(): void
    {
        $faq = $this->faq();

        $this->postJson("/eva/api/faqs/{$faq->id}/toggle")
            ->assertOk()
            ->assertJsonPath('is_eva_visible', false);

        $this->assertFalse($faq->fresh()->is_eva_visible);
        $this->assertSame(0, Faq::answerable()->count(), 'gerbang harus benar-benar menutup');
    }

    public function test_toggle_bisa_menayangkan_kembali(): void
    {
        $faq = $this->faq(['is_eva_visible' => false]);

        $this->postJson("/eva/api/faqs/{$faq->id}/toggle")
            ->assertOk()
            ->assertJsonPath('is_eva_visible', true);

        $this->assertSame(1, Faq::answerable()->count());
    }

    // ---- menghapus ---------------------------------------------------------

    public function test_faq_bisa_dihapus(): void
    {
        $faq = $this->faq();

        $this->deleteJson("/eva/api/faqs/{$faq->id}")
            ->assertOk()
            ->assertJsonPath('deleted', true);

        $this->assertSame(0, Faq::count());
    }

    /**
     * Menghapus FAQ TIDAK menghapus riwayat pertanyaan yang pernah dijawabnya.
     * kb_answer_logs adalah catatan kejadian — deflection rate bulan lalu tidak
     * boleh berubah gara-gara materi hari ini dirapikan.
     */
    public function test_menghapus_faq_tidak_menghapus_riwayat_jawabannya(): void
    {
        $faq = $this->faq();

        AnswerLog::create([
            'question' => 'cara reset password sap',
            'source_type' => Faq::class,
            'source_id' => $faq->id,
            'outcome' => AnswerLog::OUTCOME_ANSWERED,
            'confidence' => 88,
        ]);

        $this->deleteJson("/eva/api/faqs/{$faq->id}")->assertOk();

        $this->assertSame(1, AnswerLog::count());
    }

    /** Pemakaian FAQ oleh EVA dihitung dari log, bukan dari kolom yang disalin. */
    public function test_jumlah_pemakaian_dihitung_dari_log_jawaban(): void
    {
        $faq = $this->faq();

        foreach (range(1, 2) as $ignored) {
            AnswerLog::create([
                'question' => 'cara reset password sap',
                'source_type' => Faq::class,
                'source_id' => $faq->id,
                'outcome' => AnswerLog::OUTCOME_ANSWERED,
                'confidence' => 88,
            ]);
        }

        $faqs = $this->get('/eva/faq')->assertOk()->viewData('faqs');

        $this->assertSame(2, $faqs->firstWhere('id', $faq->id)['eva_uses']);
    }
}
