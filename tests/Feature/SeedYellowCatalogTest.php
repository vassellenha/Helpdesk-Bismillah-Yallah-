<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\IssueCategory;
use App\Models\Role;
use App\Models\ServiceCatalogService;
use App\Models\ServiceCatalogSubcategory;
use App\Models\ServiceCatalogSubject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * support:seed-yellow-catalog menambahkan 21 kategori "Layanan — Subcategory"
 * yang ditandai kuning di spreadsheet referensi. Yang paling penting
 * dibuktikan di sini bukan sekadar "barisnya muncul", tapi bahwa Layanan yang
 * SUDAH ADA (CCM, DHIERA, SAP) tidak pernah dibuat ulang — Admin secara
 * eksplisit meminta command ini murni tambahan.
 */
class SeedYellowCatalogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        IssueCategory::create(['name' => 'Service Request']);
        IssueCategory::create(['name' => 'Incident']);
        IssueCategory::create(['name' => 'Access Request']);

        // Tidak ada yang login di konteks CLI — command jatuh ke Administrator
        // pertama untuk atribusi Audit Trail (lihat auditActor() di command).
        $admin = User::factory()->create(['status' => 'active', 'helpdesk_access' => 'enabled']);
        $adminRole = Role::firstOrCreate(['name' => 'Administrator']);
        $admin->roles()->attach($adminRole->id);
    }

    public function test_layanan_yang_sudah_ada_tidak_dibuat_ulang(): void
    {
        $ccm = ServiceCatalogService::create(['name' => 'CCM']);

        $this->artisan('support:seed-yellow-catalog')->assertSuccessful();

        $this->assertSame(1, ServiceCatalogService::where('name', 'CCM')->count());
        $this->assertSame($ccm->id, ServiceCatalogService::where('name', 'CCM')->first()->id);
    }

    public function test_subcategory_dan_subject_baru_dibuat_tanpa_pic(): void
    {
        $this->artisan('support:seed-yellow-catalog')->assertSuccessful();

        $service = ServiceCatalogService::where('name', 'CCM')->firstOrFail();
        $subcategory = ServiceCatalogSubcategory::where('service_id', $service->id)
            ->where('name', 'Kendala Aplikasi')
            ->first();

        $this->assertNotNull($subcategory);

        $subject = ServiceCatalogSubject::where('subcategory_id', $subcategory->id)->first();
        $this->assertNotNull($subject);
        $this->assertNull($subject->support_agent_id);
        $this->assertNull($subject->it_agent_id);
        $this->assertTrue($subject->is_active);
    }

    public function test_layanan_baru_ikut_terbuat_untuk_kategori_yang_belum_ada(): void
    {
        $this->artisan('support:seed-yellow-catalog')->assertSuccessful();

        $this->assertTrue(ServiceCatalogService::where('name', 'Lisensi')->exists());
        $this->assertTrue(ServiceCatalogService::where('name', 'QHSE')->exists());
        $this->assertTrue(ServiceCatalogService::where('name', 'SDM')->exists());
    }

    // 20, bukan 21: baris kuning ke-21 di spreadsheet ("CRM" polos) sudah
    // ada persis sebagai Layanan — tidak ada Subcategory/Subject baru untuk itu.
    public function test_total_subject_baru_persis_20(): void
    {
        $before = ServiceCatalogSubject::count();

        $this->artisan('support:seed-yellow-catalog')->assertSuccessful();

        $this->assertSame($before + 20, ServiceCatalogSubject::count());
    }

    public function test_idempotent_dijalankan_dua_kali_tidak_menduplikasi(): void
    {
        $this->artisan('support:seed-yellow-catalog')->assertSuccessful();
        $countAfterFirst = ServiceCatalogSubject::count();

        $this->artisan('support:seed-yellow-catalog')->assertSuccessful();

        $this->assertSame($countAfterFirst, ServiceCatalogSubject::count());
        $this->assertSame(1, ServiceCatalogService::where('name', 'DHIERA')->count());
    }

    public function test_layanan_lain_yang_tidak_disebut_tetap_utuh(): void
    {
        ServiceCatalogService::create(['name' => 'VPN']);

        $this->artisan('support:seed-yellow-catalog')->assertSuccessful();

        $this->assertSame(1, ServiceCatalogService::where('name', 'VPN')->count());
        $this->assertSame(0, ServiceCatalogSubcategory::whereHas('service', fn ($q) => $q->where('name', 'VPN'))->count());
    }
}
