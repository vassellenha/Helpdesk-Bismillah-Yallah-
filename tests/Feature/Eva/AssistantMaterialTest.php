<?php

declare(strict_types=1);

namespace Tests\Feature\Eva;

use App\Models\Knowledge\Article;
use App\Models\Knowledge\Faq;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\ActsAsRole;
use Tests\TestCase;

/**
 * Membuka materi rujukan dari gelembung jawaban EVA.
 *
 * Selama ini judul sumber di bawah jawaban hanya teks mati: karyawan tahu
 * jawabannya berasal dari "Reset Password SAP" tapi tidak punya jalan untuk
 * membaca panduan utuhnya. Endpoint ini yang membukanya.
 *
 * Gerbangnya WAJIB sama persis dengan gerbang menjawab — scopeAnswerable().
 * Kalau tidak, endpoint ini berubah menjadi pintu belakang untuk membaca
 * artikel draf dan materi yang sengaja disembunyikan dari EVA: cukup menebak
 * nomornya, tanpa perlu jadi admin. Karena itu setiap materi yang tidak boleh
 * dipakai menjawab harus membalas 404 di sini, bukan 200 dengan isinya.
 */
final class AssistantMaterialTest extends TestCase
{
    use ActsAsRole;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedCatalog();
        $this->actingAsRole('requester');
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

    private function article(array $attributes = []): Article
    {
        return Article::create(array_merge([
            'title' => 'Reset Password SAP',
            'summary' => 'Ringkasan langkah reset password SAP.',
            'body' => "Langkah 1: buka SAP GUI.\nLangkah 2: pilih menu ubah password.",
            'catalog_subject_id' => 1,
            'status' => Article::STATUS_PUBLISHED,
            'is_eva_visible' => true,
        ], $attributes));
    }

    private function faq(array $attributes = []): Faq
    {
        return Faq::create(array_merge([
            'question' => 'Bagaimana cara reset password SAP?',
            'answer' => 'Buka SAP GUI lalu pilih menu ubah password.',
            'catalog_subject_id' => 1,
            'is_eva_visible' => true,
        ], $attributes));
    }

    private function open(string $type, int|string $id)
    {
        return $this->getJson("/assistant/api/material/{$type}/{$id}");
    }

    // ---- jalan yang dituju -------------------------------------------------

    public function test_artikel_terbit_bisa_dibuka_dari_rujukan(): void
    {
        $article = $this->article();

        $this->open('article', $article->id)
            ->assertOk()
            ->assertJsonPath('type', 'article')
            ->assertJsonPath('id', $article->id)
            ->assertJsonPath('title', 'Reset Password SAP')
            ->assertJsonPath('body', "Langkah 1: buka SAP GUI.\nLangkah 2: pilih menu ubah password.");
    }

    public function test_faq_bisa_dibuka_dari_rujukan(): void
    {
        $faq = $this->faq();

        $this->open('faq', $faq->id)
            ->assertOk()
            ->assertJsonPath('type', 'faq')
            ->assertJsonPath('title', 'Bagaimana cara reset password SAP?')
            ->assertJsonPath('body', 'Buka SAP GUI lalu pilih menu ubah password.');
    }

    /** Supaya popup bisa menyebut materi ini melayani layanan/subject apa. */
    public function test_materi_membawa_subject_katalognya(): void
    {
        $article = $this->article();

        $this->open('article', $article->id)
            ->assertOk()
            ->assertJsonPath('subject.subject', 'Reset Password')
            ->assertJsonPath('subject.service', 'SAP');
    }

    public function test_materi_tanpa_subject_tetap_bisa_dibuka(): void
    {
        $faq = $this->faq(['catalog_subject_id' => null]);

        $this->open('faq', $faq->id)
            ->assertOk()
            ->assertJsonPath('subject', null);
    }

    // ---- gerbang: sama persis dengan gerbang menjawab -----------------------

    public function test_artikel_draf_tidak_bisa_dibuka(): void
    {
        $article = $this->article(['status' => Article::STATUS_DRAFT]);

        $this->open('article', $article->id)->assertNotFound();
    }

    public function test_artikel_yang_disembunyikan_dari_eva_tidak_bisa_dibuka(): void
    {
        $article = $this->article(['is_eva_visible' => false]);

        $this->open('article', $article->id)->assertNotFound();
    }

    public function test_faq_yang_disembunyikan_dari_eva_tidak_bisa_dibuka(): void
    {
        $faq = $this->faq(['is_eva_visible' => false]);

        $this->open('faq', $faq->id)->assertNotFound();
    }

    public function test_materi_yang_tidak_ada_membalas_404(): void
    {
        $this->open('article', 4321)->assertNotFound();
    }

    /**
     * Nomor artikel dan nomor FAQ berjalan sendiri-sendiri, jadi id yang sama
     * bisa hidup di kedua tabel. Salah jenis harus berakhir 404, bukan
     * memulangkan materi lain yang kebetulan bernomor sama.
     */
    public function test_jenis_yang_salah_tidak_memulangkan_materi_bernomor_sama(): void
    {
        $faq = $this->faq();

        $this->open('article', $faq->id)->assertNotFound();
    }

    public function test_jenis_materi_tak_dikenal_ditolak(): void
    {
        $this->article();

        $this->open('dokumen', 1)->assertNotFound();
    }

    public function test_tamu_ditolak(): void
    {
        $article = $this->article();

        auth()->logout();

        $this->open('article', $article->id)->assertUnauthorized();
    }
}
