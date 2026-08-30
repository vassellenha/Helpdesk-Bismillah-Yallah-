<?php

namespace Tests\Feature\Knowledge;

use App\Services\Knowledge\SubjectMatcher;
use App\Services\Knowledge\SubjectSearch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Mengunci perilaku "pertanyaan menyebut nama aplikasi".
 *
 * Lahir dari satu tiket nyata di produksi: "bagaimana saya bisa melaporkan
 * ketika ada kendala di elisa" diarahkan ke `Service Request › CCM › Kendala
 * Aplikasi` dengan keyakinan 40 — subject milik APLIKASI LAIN yang menang
 * hanya karena namanya memakai dua kata paling umum dalam keluhan IT.
 *
 * Kata "elisa" menyumbang nol: penilaian lama mengukur "berapa kata subject
 * yang disebut penanya" dan tidak pernah bertanya balik "apakah calon ini
 * MEMBANTAH pertanyaannya". Fixture di bawah meniru bentuk katalog asli yang
 * membuat itu terjadi.
 */
final class ServiceFallbackTest extends TestCase
{
    use RefreshDatabase;

    private SubjectSearch $matcher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCatalog();
        SubjectMatcher::forget();
        $this->matcher = app(SubjectSearch::class);
    }

    /**
     * Empat subject yang meniru jebakan aslinya:
     *  - ELISA punya subject SPESIFIK saja, tak satu pun bernama "kendala"
     *  - CCM punya subject GENERIK "Kendala Aplikasi" — penjaring tak sengaja
     *  - SAP dan AKUN APLIKASI › SAP menguji bahwa nama aplikasi yang muncul
     *    sebagai SUB CATEGORY di layanan lain tetap dianggap nyambung
     */
    private function seedCatalog(): void
    {
        DB::table('issue_categories')->insert([
            ['id' => 1, 'name' => 'Incident'],
            ['id' => 2, 'name' => 'Service Request'],
            ['id' => 3, 'name' => 'Access Request'],
        ]);

        DB::table('service_catalog_services')->insert([
            ['id' => 1, 'name' => 'ELISA'],
            ['id' => 2, 'name' => 'CCM'],
            ['id' => 3, 'name' => 'SAP'],
            ['id' => 4, 'name' => 'AKUN APLIKASI'],
        ]);

        DB::table('service_catalog_subcategories')->insert([
            ['id' => 1, 'service_id' => 1, 'name' => 'VENDOR MANAGEMENT'],
            ['id' => 2, 'service_id' => 2, 'name' => 'Kendala Aplikasi'],
            ['id' => 3, 'service_id' => 3, 'name' => 'LAYANAN KONSULTASI'],
            ['id' => 4, 'service_id' => 4, 'name' => 'SAP'],
        ]);

        $subject = fn (int $id, int $ic, int $service, int $subcat, string $name) => [
            'id' => $id, 'issue_category_id' => $ic, 'service_id' => $service,
            'subcategory_id' => $subcat, 'name' => $name,
            'requires_approval' => false, 'support_level' => 1, 'is_active' => true,
        ];

        DB::table('service_catalog_subjects')->insert([
            $subject(1, 1, 1, 1, 'Tidak bisa release vendor'),
            $subject(2, 2, 2, 2, 'Kendala Aplikasi'),
            $subject(3, 2, 3, 3, 'Konsultasi Data & Laporan SAP'),
            $subject(4, 3, 4, 4, 'Reset Password'),
        ]);
    }

    /** @return string[] nama subject hasil, terurut dari paling yakin */
    private function subjectsOf(string $q): array
    {
        return array_map(fn ($m) => $m->subject, $this->matcher->cocokkan($q, 5));
    }

    /**
     * INTI REGRESI. Pertanyaan generik yang menyebut ELISA tidak boleh lagi
     * mendarat di subject milik CCM.
     */
    public function test_pertanyaan_generik_bernama_aplikasi_tidak_mendarat_di_aplikasi_lain(): void
    {
        $hasil = $this->subjectsOf('bagaimana saya bisa melaporkan ketika ada kendala di elisa');

        $this->assertNotContains('Kendala Aplikasi', $hasil);
    }

    /** Varian aplikasi lain — buktinya polanya umum, bukan tambal satu kata. */
    public function test_pola_sama_berlaku_untuk_aplikasi_lain(): void
    {
        $hasil = $this->subjectsOf('bagaimana saya melaporkan kendala di SAP');

        $this->assertNotContains('Kendala Aplikasi', $hasil);
        $this->assertContains('Konsultasi Data & Laporan SAP', $hasil);
    }

    /** EVA tetap tahu aplikasinya walau tak tahu masalahnya. */
    public function test_layanan_dikenali_saat_subject_tidak_ada_yang_cocok(): void
    {
        $layanan = $this->matcher->layananTerbaik('bagaimana saya bisa melaporkan ketika ada kendala di elisa');

        $this->assertNotNull($layanan);
        $this->assertSame('ELISA', $layanan->service);
        $this->assertSame(1, $layanan->serviceId);
    }

    /** Tanpa nama aplikasi, tidak ada yang bisa disimpulkan — null itu sah. */
    public function test_layanan_null_saat_pertanyaan_tidak_menyebut_aplikasi(): void
    {
        $this->assertNull($this->matcher->layananTerbaik('printer di lantai lima rusak'));
    }

    /** Subject spesifik tetap menang telak — jalur lama tidak boleh bergeser. */
    public function test_subject_spesifik_tetap_menang(): void
    {
        $best = $this->matcher->terbaik('elisa tidak bisa release vendor');

        $this->assertNotNull($best);
        $this->assertSame('Tidak bisa release vendor', $best->subject);
        $this->assertSame(95, $best->confidence);
    }

    /**
     * REGRESI: nama aplikasi yang muncul sebagai SUB CATEGORY di layanan lain
     * tetap dianggap nyambung. "reset password akun sap" menyebut SAP, tapi
     * yang dimaksud `AKUN APLIKASI › SAP` — calon itu tidak boleh dihukum.
     */
    public function test_nama_aplikasi_pada_sub_category_ikut_menyelamatkan_calon(): void
    {
        $best = $this->matcher->terbaik('reset password akun sap');

        $this->assertNotNull($best);
        $this->assertSame('Reset Password', $best->subject);
    }

    /** Pertanyaan tanpa nama aplikasi sama sekali tidak berubah perilakunya. */
    public function test_pertanyaan_tanpa_nama_aplikasi_tidak_terpengaruh(): void
    {
        $this->assertContains('Kendala Aplikasi', $this->subjectsOf('kendala aplikasi'));
    }
}
