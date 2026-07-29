<?php

namespace Tests\Feature\Knowledge;

use App\Services\Knowledge\SubjectMatcher;
use App\Services\Knowledge\SubjectSearch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Mengunci logika SubjectMatcher (Pencarian B) — kode dengan kepadatan bug
 * tertinggi di seluruh pengembangan EVA, semua ditemukan lewat coba-coba.
 *
 * Memakai fixture katalog MINIMAL (empat subject), bukan seluruh CSV tim,
 * supaya angkanya bisa dinalar tangan dan tesnya deterministik. Migrasi
 * FULLTEXT kini sadar-driver, jadi RefreshDatabase jalan di SQLite :memory:;
 * SubjectMatcher sendiri tidak memakai FULLTEXT sama sekali.
 */
final class SubjectMatcherTest extends TestCase
{
    use RefreshDatabase;

    private SubjectMatcher $matcher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCatalog();
        SubjectMatcher::forget();
        $this->matcher = app(SubjectSearch::class);
    }

    /**
     * Empat subject yang cukup untuk seluruh perilaku yang diuji:
     *  - "Password Expired" & "SAP Lambat" di layanan SAP (uji double-count)
     *  - dua "Reset Password" identik di layanan AKUN APLIKASI, beda sub
     *    category SAP vs SILO (uji seri, tie-guard, calonSeri)
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
            ['id' => 2, 'service_id' => 1, 'name' => 'PERFORMANCE'],
            ['id' => 3, 'service_id' => 2, 'name' => 'SAP'],
            ['id' => 4, 'service_id' => 2, 'name' => 'SILO (OTHER APPS)'],
        ]);

        $subject = fn (int $id, int $service, int $subcat, string $name) => [
            'id' => $id, 'issue_category_id' => 1, 'service_id' => $service,
            'subcategory_id' => $subcat, 'name' => $name,
            'requires_approval' => false, 'support_level' => 1, 'is_active' => true,
        ];

        DB::table('service_catalog_subjects')->insert([
            $subject(1, 1, 1, 'Password Expired'),
            $subject(2, 1, 2, 'SAP Lambat'),
            $subject(3, 2, 3, 'Reset Password'),
            $subject(4, 2, 4, 'Reset Password'),
        ]);
    }

    /** @return string[] nama subject hasil, terurut */
    private function subjectsOf(string $q): array
    {
        return array_map(fn ($m) => $m->subject, $this->matcher->cocokkan($q, 5));
    }

    /**
     * REGRESI double-count: "SAP" tidak boleh dihitung dua kali (subject +
     * layanan). "lupa password SAP" harus mengunggulkan subject password nyata,
     * dan "SAP Lambat" — yang cuma berbagi nama layanan — tak boleh muncul.
     */
    public function test_nama_layanan_tidak_dihitung_dua_kali(): void
    {
        $hasil = $this->subjectsOf('lupa password SAP');

        $this->assertSame('Password Expired', $hasil[0] ?? null);
        $this->assertNotContains('SAP Lambat', $hasil, '"SAP Lambat" hanya berbagi nama layanan, bukan masalahnya');
    }

    /**
     * Dua subject identik beda cabang, tanpa nama layanan di pertanyaan → seri
     * sempurna → isi-otomatis MENAHAN DIRI (null), tidak menebak salah satu.
     */
    public function test_seri_membuat_terbaik_menahan_diri(): void
    {
        $this->assertNull(
            $this->matcher->terbaik('reset password'),
            'seri 70/70 tidak boleh diisikan otomatis',
        );
    }

    /** Menyebut layanan memecah seri: "reset password SAP" → subcat SAP menang. */
    public function test_menyebut_layanan_memecah_seri(): void
    {
        $best = $this->matcher->terbaik('reset password SAP');

        $this->assertNotNull($best);
        $this->assertSame('SAP', $best->subcategory);
    }

    /** calonSeri mengembalikan kedua cabang seri yang berbagi nama subject. */
    public function test_calon_seri_mengembalikan_kedua_cabang(): void
    {
        $seri = $this->matcher->calonSeri('reset password');

        $this->assertCount(2, $seri);
        $this->assertSame(['Reset Password', 'Reset Password'], array_map(fn ($m) => $m->subject, $seri));
        $this->assertEqualsCanonicalizing(
            ['SAP', 'SILO (OTHER APPS)'],
            array_map(fn ($m) => $m->subcategory, $seri),
        );
    }

    /** Pertanyaan yang tak dipahami tidak memicu pertanyaan-balik seri. */
    public function test_pertanyaan_lemah_tidak_menghasilkan_seri(): void
    {
        $this->assertSame([], $this->matcher->calonSeri('kucing lapar sekali'));
        $this->assertNull($this->matcher->terbaik('kucing lapar sekali'));
    }

    /**
     * Jarak edit: typo satu huruf pada kata panjang tetap ketemu, tapi dengan
     * nilai lebih rendah daripada ejaan tepat (kecocokan persis harus menang).
     */
    public function test_typo_tetap_ketemu_dengan_nilai_lebih_rendah(): void
    {
        $tepat = $this->matcher->cocokkan('password SAP', 1);
        $typo = $this->matcher->cocokkan('pasword SAP', 1);

        $this->assertSame('Password Expired', $typo[0]->subject ?? null, 'typo "pasword" tetap menemukan subject password');
        $this->assertLessThan(
            $tepat[0]->confidence,
            $typo[0]->confidence,
            'ejaan tepat harus bernilai lebih tinggi daripada typo',
        );
    }

    /**
     * Penjaga kata pendek: "sup" (3 huruf) tidak boleh dianggap typo "sap" —
     * satu huruf pada kata pendek mengubah makna. Buktinya: "reset password
     * sup" tetap seri (tak memihak cabang SAP), sedangkan "...sap" memecahnya.
     */
    public function test_kata_pendek_wajib_persis(): void
    {
        $this->assertNull(
            $this->matcher->terbaik('reset password sup'),
            '"sup" tidak boleh memihak cabang SAP lewat jarak edit',
        );
        $this->assertNotNull(
            $this->matcher->terbaik('reset password sap'),
            'kontras: "sap" persis memang memecah seri',
        );
    }
}
