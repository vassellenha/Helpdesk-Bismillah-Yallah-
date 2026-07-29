<?php

namespace Tests\Feature\Knowledge;

use App\Models\Knowledge\Article;
use App\Models\Knowledge\CoverageSnapshot;
use App\Services\Knowledge\CoverageCalculator;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Garis tren Coverage harus terbuat dari angka yang PERNAH BENAR-BENAR TERJADI.
 *
 * Sebelumnya seeder mengarang lima titik dari array persentase hardcoded, dan
 * layar menampilkannya sebagai riwayat sungguhan — satu-satunya angka fiktif
 * yang tersisa di konsol, dan justru di grafik yang dipakai orang menyimpulkan
 * "kita sedang membaik". Sekarang riwayat hanya lahir dari perintah
 * `eva:snapshot-coverage`, dan titik terakhir selalu hitungan nyata hari ini.
 */
final class CoverageTrendTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCatalog();
    }

    /** Empat subject; dua di antaranya akan ditutup artikel → 50%. */
    private function seedCatalog(): void
    {
        DB::table('issue_categories')->insert(['id' => 1, 'name' => 'Access Request']);
        DB::table('service_catalog_services')->insert(['id' => 1, 'name' => 'SAP']);
        DB::table('service_catalog_subcategories')->insert(['id' => 1, 'service_id' => 1, 'name' => 'AKUN']);

        $subject = fn (int $id, string $name) => [
            'id' => $id, 'issue_category_id' => 1, 'service_id' => 1, 'subcategory_id' => 1,
            'name' => $name, 'requires_approval' => false, 'support_level' => 1, 'is_active' => true,
        ];

        DB::table('service_catalog_subjects')->insert([
            $subject(1, 'Password Expired'),
            $subject(2, 'User Locked'),
            $subject(3, 'Akun Baru'),
            $subject(4, 'Hapus Akun'),
        ]);
    }

    private function coverSubject(int $subjectId): void
    {
        Article::create([
            'title' => 'SOP #'.$subjectId,
            'body' => 'Langkahnya begini.',
            'catalog_subject_id' => $subjectId,
            'status' => Article::STATUS_PUBLISHED,
            'is_eva_visible' => true,
        ]);
    }

    private function trend(): array
    {
        return app(CoverageCalculator::class)->trend();
    }

    /**
     * Tanpa riwayat, grafik menunjukkan SATU titik — kondisi hari ini. Bukan
     * garis naik yang dikarang supaya terlihat berisi.
     */
    public function test_tanpa_riwayat_hanya_ada_titik_hari_ini(): void
    {
        $this->coverSubject(1);
        $this->coverSubject(2);

        $trend = $this->trend();

        $this->assertCount(1, $trend);
        $this->assertSame(50, $trend[0]['percent']);
    }

    public function test_perintah_mencatat_angka_nyata(): void
    {
        $this->coverSubject(1);

        $this->artisan('eva:snapshot-coverage')->assertSuccessful();

        $snapshot = CoverageSnapshot::sole();
        $this->assertTrue($snapshot->captured_on->isToday());
        $this->assertSame(4, $snapshot->total_subjects);
        $this->assertSame(1, $snapshot->covered_subjects);
        $this->assertSame(25, $snapshot->coverage_percent);
    }

    /**
     * Dijalankan dua kali dalam hari yang sama, hasilnya tetap satu baris —
     * kalau tidak, penjadwalan yang kelewat rajin akan memenuhi grafik dengan
     * belasan titik untuk satu hari yang sama.
     */
    public function test_perintah_idempoten_dalam_satu_hari(): void
    {
        $this->coverSubject(1);
        $this->artisan('eva:snapshot-coverage')->assertSuccessful();

        $this->coverSubject(2);
        $this->artisan('eva:snapshot-coverage')->assertSuccessful();

        $this->assertSame(1, CoverageSnapshot::count());
        $this->assertSame(50, CoverageSnapshot::sole()->coverage_percent, 'baris hari ini diperbarui, bukan digandakan');
    }

    private function snapshotOn(CarbonInterface $date, int $percent): void
    {
        CoverageSnapshot::create([
            'captured_on' => $date,
            'total_subjects' => 4,
            'covered_subjects' => (int) round(4 * $percent / 100),
            'coverage_percent' => $percent,
        ]);
    }

    /** Riwayat bulan lalu + titik nyata hari ini. */
    public function test_riwayat_digabung_dengan_titik_hari_ini(): void
    {
        $this->snapshotOn(today()->subMonth(), 25);

        $this->coverSubject(1);
        $this->coverSubject(2);

        $trend = $this->trend();

        $this->assertCount(2, $trend);
        $this->assertSame(25, $trend[0]['percent']);
        $this->assertSame(50, $trend[1]['percent'], 'titik terakhir selalu hitungan nyata hari ini');
    }

    /**
     * Perekaman harian, tampilan BULANAN.
     *
     * Merekam tiap hari itu murah dan tidak bisa diulang kalau terlewat;
     * menampilkannya tiap hari membuat grafik jadi lima hari yang hampir selalu
     * rata — tidak memberi tahu apa pun tentang kemajuan penulisan materi. Jadi
     * tiap bulan diwakili satu titik: rekaman TERAKHIR di bulan itu.
     */
    public function test_satu_bulan_diwakili_rekaman_terakhirnya(): void
    {
        $bulanLalu = today()->subMonth();

        $this->snapshotOn($bulanLalu->copy()->startOfMonth(), 10);
        $this->snapshotOn($bulanLalu->copy()->startOfMonth()->addDays(9), 20);
        $this->snapshotOn($bulanLalu->copy()->endOfMonth(), 25);

        $this->coverSubject(1);
        $this->coverSubject(2);

        $trend = $this->trend();

        $this->assertCount(2, $trend, 'tiga rekaman di satu bulan hanya jadi satu titik');
        $this->assertSame(25, $trend[0]['percent'], 'yang mewakili bulan itu rekaman terakhirnya');
    }

    /**
     * Rekaman bulan BERJALAN tidak muncul sebagai titik sendiri — bulan ini
     * sudah diwakili titik hari ini, yang selalu angka sebenarnya.
     */
    public function test_rekaman_bulan_berjalan_tidak_menggandakan_titik(): void
    {
        $this->snapshotOn(today()->startOfMonth(), 25);

        $this->coverSubject(1);
        $this->coverSubject(2);

        $trend = $this->trend();

        $this->assertCount(1, $trend);
        $this->assertSame(50, $trend[0]['percent']);
    }

    /**
     * Snapshot yang diambil HARI INI tidak boleh muncul sebagai titik terpisah
     * di samping titik hari ini — itu akan menampilkan satu hari dua kali,
     * dengan angka yang bisa berbeda kalau materi bertambah sesudah snapshot.
     */
    public function test_snapshot_hari_ini_tidak_menggandakan_titik(): void
    {
        $this->coverSubject(1);
        $this->artisan('eva:snapshot-coverage')->assertSuccessful();

        $this->coverSubject(2);
        $trend = $this->trend();

        $this->assertCount(1, $trend);
        $this->assertSame(50, $trend[0]['percent'], 'yang tampil kondisi sekarang, bukan snapshot pagi tadi');
    }

    /** Grafik dibatasi beberapa bulan terakhir supaya tetap terbaca. */
    public function test_hanya_bulan_terakhir_yang_ditampilkan(): void
    {
        foreach (range(1, 10) as $monthsAgo) {
            $this->snapshotOn(today()->subMonths($monthsAgo), 25);
        }

        $this->assertLessThanOrEqual(6, count($this->trend()));
    }

    /** Perekaman harian tidak boleh bikin grafik jadi deretan hari yang rata. */
    public function test_perekaman_harian_tetap_menghasilkan_grafik_bulanan(): void
    {
        foreach (range(1, 40) as $daysAgo) {
            $this->snapshotOn(today()->subDays($daysAgo), 25);
        }

        $this->assertLessThanOrEqual(3, count($this->trend()), '40 hari rekaman paling banyak menyentuh tiga bulan');
    }
}
