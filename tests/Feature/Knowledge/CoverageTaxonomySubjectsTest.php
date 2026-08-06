<?php

namespace Tests\Feature\Knowledge;

use App\Services\Knowledge\CoverageCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * taxonomyTree() membawa daftar SUBJECT di dalam tiap sub category, bukan
 * cuma angka rekap.
 *
 * Sebelumnya subcategory hanya membawa tally (subjects/covered/percent/
 * articles/faqs) — cukup untuk progress bar, tidak cukup untuk menjawab
 * "subject mana saja yang ada di sini". Category & Taxonomy adalah satu-
 * satunya layar EVA yang menampilkan struktur katalog secara utuh; tanpa
 * daftar subject-nya, admin yang ingin tahu isi persis sebuah sub category
 * masih harus pindah ke Service Catalog.
 */
final class CoverageTaxonomySubjectsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_tiap_subcategory_membawa_daftar_subject_dengan_namanya(): void
    {
        $this->seedSubject(1, 'AKUN APLIKASI', 'Reset Password');
        $this->seedSubject(2, 'AKUN APLIKASI', 'Unlock Akun', subcategoryId: 1);

        $tree = app(CoverageCalculator::class)->taxonomyTree();
        $subcategory = $tree[0]['services'][0]['subcategories'][0];

        $this->assertArrayHasKey('subjectList', $subcategory);
        $this->assertSame(
            ['Reset Password', 'Unlock Akun'],
            collect($subcategory['subjectList'])->pluck('name')->sort()->values()->all(),
        );
    }

    /**
     * 'subjects' pada subcategory harus tetap JUMLAH (int), bukan daftar.
     *
     * Sebelumnya daftar Subject ditulis ke key yang sama dengan tally()
     * ('subjects'), menimpa angkanya dengan array. Front-end menampilkan
     * "{covered}/{subjects}" untuk level ini dan memakai angka itu untuk
     * urutan — array di sana membuat React gagal me-render seluruh pohon
     * begitu dropdown Layanan dibuka.
     */
    public function test_subjects_pada_subcategory_tetap_angka_bukan_daftar(): void
    {
        $this->seedSubject(1, 'AKUN APLIKASI', 'Reset Password');
        $this->seedSubject(2, 'AKUN APLIKASI', 'Unlock Akun', subcategoryId: 1);

        $tree = app(CoverageCalculator::class)->taxonomyTree();
        $subcategory = $tree[0]['services'][0]['subcategories'][0];

        $this->assertIsInt($subcategory['subjects']);
        $this->assertSame(2, $subcategory['subjects']);
    }

    /**
     * Status tertutup per subject dihitung sendiri, bukan diwarisi dari angka
     * sub category-nya. Sub category "60% tertutup" tidak menyebut SUBJECT mana
     * yang masih kosong — itulah justru yang ingin dilihat admin dengan membuka
     * dropdown-nya.
     */
    public function test_status_tertutup_dihitung_per_subject_bukan_diwarisi_dari_induknya(): void
    {
        $this->seedSubject(1, 'AKUN APLIKASI', 'Sudah Ada Artikel');
        $this->seedSubject(2, 'AKUN APLIKASI', 'Belum Ada Materi', subcategoryId: 1);

        DB::table('kb_articles')->insert([
            'title' => 'Panduan', 'summary' => 's', 'body' => 'b', 'status' => 'published',
            'catalog_subject_id' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $tree = app(CoverageCalculator::class)->taxonomyTree();
        $subjects = collect($tree[0]['services'][0]['subcategories'][0]['subjectList'])->keyBy('name');

        $this->assertSame(1, $subjects['Sudah Ada Artikel']['covered']);
        $this->assertSame(0, $subjects['Belum Ada Materi']['covered']);
    }

    /** Subject dari FAQ ikut terhitung tertutup, sama seperti angka rekap di atasnya. */
    public function test_faq_ikut_menutup_status_subject(): void
    {
        $this->seedSubject(1, 'AKUN APLIKASI', 'Ditutup FAQ');

        DB::table('kb_faqs')->insert([
            'question' => 'q', 'answer' => 'a', 'is_eva_visible' => true,
            'catalog_subject_id' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $tree = app(CoverageCalculator::class)->taxonomyTree();
        $subject = $tree[0]['services'][0]['subcategories'][0]['subjectList'][0];

        $this->assertSame(1, $subject['covered']);
    }

    private function seedSubject(int $id, string $subcategoryName, string $subjectName, ?int $subcategoryId = null): void
    {
        $subcategoryId ??= $id;

        DB::table('issue_categories')->insertOrIgnore(['id' => 1, 'name' => 'Incident']);
        DB::table('service_catalog_services')->insertOrIgnore(['id' => 1, 'name' => 'SAP']);
        DB::table('service_catalog_subcategories')->insertOrIgnore([
            'id' => $subcategoryId, 'service_id' => 1, 'name' => $subcategoryName,
        ]);

        DB::table('service_catalog_subjects')->insert([
            'id' => $id, 'issue_category_id' => 1, 'service_id' => 1, 'subcategory_id' => $subcategoryId,
            'name' => $subjectName, 'requires_approval' => false, 'support_level' => 1, 'is_active' => true,
        ]);
    }
}
