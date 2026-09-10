<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\AuditTrail;
use App\Models\IssueCategory;
use App\Models\ServiceCatalogService;
use App\Models\ServiceCatalogSubcategory;
use App\Models\ServiceCatalogSubject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsRole;
use Tests\TestCase;

/**
 * Menambah Subjek ikut MEMBUAT Layanan dan Sub Kategori-nya kalau belum ada
 * (firstOrCreate di store()), tapi menghapus Subjek dulu hanya menghapus
 * Subjek itu sendiri. Wadahnya tertinggal, kosong, selamanya.
 *
 * Selama bertahun bulan tidak ada yang sadar karena tidak satu layar pun
 * menampilkan Sub Kategori. Tab "Cakupan Team Lead" adalah yang pertama —
 * dan sampah lama itu langsung muncul di sana sebagai baris berisi 0 Subjek
 * yang menunggu ditugaskan, membuat pekerjaan Admin tampak lebih besar dari
 * yang sebenarnya.
 *
 * Yang dihapus otomatis HANYA Sub Kategori yang benar-benar nol. LAYANAN
 * TIDAK, meski jadi kosong: tanpa Sub Kategori pun ia tetap muncul di
 * pemilih Aplikasi pada form Tiket Baru (MASTER_APPLICATIONS di
 * ServiceCatalogSeeder), dan di data nyata ada 28 Layanan seperti itu yang
 * semuanya disengaja. Membuangnya otomatis menghapus 28 pilihan dari layar
 * requester tanpa ada yang meminta.
 */
final class ServiceCatalogPruneEmptyTest extends TestCase
{
    use ActsAsRole, RefreshDatabase;

    public function test_menghapus_subjek_terakhir_ikut_menghapus_wadah_yang_jadi_kosong(): void
    {
        $this->actingAsRole('admin');

        $service = ServiceCatalogService::create(['name' => 'Vito Pemay']);
        $subcategory = ServiceCatalogSubcategory::create(['service_id' => $service->id, 'name' => 'Vito Pemay']);
        $subject = $this->subjek($subcategory, 'Vito Pemay');

        $this->deleteJson("/admin/service-catalog/subjects/{$subject->id}")->assertOk();

        $this->assertDatabaseMissing('service_catalog_subjects', ['id' => $subject->id]);
        $this->assertDatabaseMissing('service_catalog_subcategories', ['id' => $subcategory->id]);
        // Layanan tidak boleh ikut terhapus otomatis — ia masih jadi pilihan
        // Aplikasi di form Tiket Baru meski tanpa Sub Kategori.
        $this->assertDatabaseHas('service_catalog_services', ['id' => $service->id]);
    }

    public function test_sub_kategori_yang_masih_dipakai_subjek_lain_tidak_ikut_dihapus(): void
    {
        $this->actingAsRole('admin');

        $service = ServiceCatalogService::create(['name' => 'SAP']);
        $subcategory = ServiceCatalogSubcategory::create(['service_id' => $service->id, 'name' => 'LOGIN SAP']);
        $dihapus = $this->subjek($subcategory, 'Password Expired');
        $bertahan = $this->subjek($subcategory, 'User Locked');

        $this->deleteJson("/admin/service-catalog/subjects/{$dihapus->id}")->assertOk();

        $this->assertDatabaseHas('service_catalog_subcategories', ['id' => $subcategory->id]);
        $this->assertDatabaseHas('service_catalog_services', ['id' => $service->id]);
        $this->assertDatabaseHas('service_catalog_subjects', ['id' => $bertahan->id]);
    }

    public function test_layanan_yang_masih_punya_sub_kategori_lain_tidak_ikut_dihapus(): void
    {
        $this->actingAsRole('admin');

        $service = ServiceCatalogService::create(['name' => 'SAP']);
        $dikosongkan = ServiceCatalogSubcategory::create(['service_id' => $service->id, 'name' => 'PRINTING']);
        $lain = ServiceCatalogSubcategory::create(['service_id' => $service->id, 'name' => 'REPORT']);
        $this->subjek($lain, 'Report Tidak Muncul');
        $subject = $this->subjek($dikosongkan, 'Gagal Print');

        $this->deleteJson("/admin/service-catalog/subjects/{$subject->id}")->assertOk();

        $this->assertDatabaseMissing('service_catalog_subcategories', ['id' => $dikosongkan->id]);
        $this->assertDatabaseHas('service_catalog_subcategories', ['id' => $lain->id]);
        $this->assertDatabaseHas(
            'service_catalog_services',
            ['id' => $service->id],
        );
    }

    /**
     * Wadah yang ikut terhapus disebut di deskripsi jejak audit yang sudah
     * ada, bukan jadi baris audit sendiri: menghapus satu Subjek tidak boleh
     * meledak jadi tiga baris yang harus dibaca satu per satu.
     */
    public function test_wadah_yang_ikut_terhapus_disebut_di_audit_trail(): void
    {
        $this->actingAsRole('admin');

        $service = ServiceCatalogService::create(['name' => 'Vito Pemay']);
        $subcategory = ServiceCatalogSubcategory::create(['service_id' => $service->id, 'name' => 'Vito Pemay']);
        $subject = $this->subjek($subcategory, 'Vito Pemay');

        $this->deleteJson("/admin/service-catalog/subjects/{$subject->id}")->assertOk();

        $jejak = AuditTrail::where('action', 'delete')->where('target_type', 'subject')->latest('id')->first();

        $this->assertStringContainsString('Vito Pemay', (string) $jejak->description);
        $this->assertStringContainsString(
            'Sub Kategori',
            (string) $jejak->description,
            'pembersihan wadah harus terbaca di Audit Trail, bukan terjadi diam-diam',
        );
        $this->assertSame(1, AuditTrail::where('action', 'delete')->count());
    }

    public function test_perintah_prune_hanya_melapor_tanpa_apply(): void
    {
        $service = ServiceCatalogService::create(['name' => 'Vito Pemay']);
        $subcategory = ServiceCatalogSubcategory::create(['service_id' => $service->id, 'name' => 'Vito Pemay']);

        $this->artisan('catalog:prune-empty')
            ->expectsOutputToContain('Vito Pemay')
            ->assertExitCode(0);

        $this->assertDatabaseHas('service_catalog_subcategories', ['id' => $subcategory->id]);
        $this->assertDatabaseHas('service_catalog_services', ['id' => $service->id]);
    }

    public function test_perintah_prune_menghapus_saat_apply(): void
    {
        $service = ServiceCatalogService::create(['name' => 'Vito Pemay']);
        $subcategory = ServiceCatalogSubcategory::create(['service_id' => $service->id, 'name' => 'Vito Pemay']);

        $dipakai = ServiceCatalogService::create(['name' => 'SAP']);
        $sc = ServiceCatalogSubcategory::create(['service_id' => $dipakai->id, 'name' => 'LOGIN SAP']);
        $this->subjek($sc, 'Password Expired');

        $this->artisan('catalog:prune-empty --apply')->assertExitCode(0);

        $this->assertDatabaseMissing('service_catalog_subcategories', ['id' => $subcategory->id]);
        // --apply hanya menyapu Sub Kategori; Layanan menunggu disebut id-nya.
        $this->assertDatabaseHas('service_catalog_services', ['id' => $service->id]);

        $this->assertDatabaseHas('service_catalog_subcategories', ['id' => $sc->id]);
        $this->assertDatabaseHas('service_catalog_services', ['id' => $dipakai->id]);
    }

    /**
     * Layanan hanya hilang kalau Admin menyebut id-nya. Menyapu semua Layanan
     * kosong sekaligus akan membuang 28 aplikasi perusahaan dari pemilih di
     * form Tiket Baru — aplikasi yang memang belum punya definisi Subjek,
     * bukan sampah.
     */
    public function test_layanan_hanya_dihapus_kalau_id_nya_disebut(): void
    {
        $sampah = ServiceCatalogService::create(['name' => 'Vito Pemay']);
        $sengaja = ServiceCatalogService::create(['name' => 'ADHISEHAT']);

        $this->artisan("catalog:prune-empty --drop-service={$sampah->id}")->assertExitCode(0);

        $this->assertDatabaseMissing('service_catalog_services', ['id' => $sampah->id]);
        // Layanan yang tidak disebut id-nya harus tetap ada.
        $this->assertDatabaseHas('service_catalog_services', ['id' => $sengaja->id]);
    }

    private function subjek(ServiceCatalogSubcategory $sub, string $nama): ServiceCatalogSubject
    {
        return ServiceCatalogSubject::create([
            'issue_category_id' => IssueCategory::firstOrCreate(['name' => 'Incident'])->id,
            'service_id' => $sub->service_id,
            'subcategory_id' => $sub->id,
            'name' => $nama,
            'requires_approval' => false,
            'support_level' => 1,
            'is_active' => true,
        ]);
    }
}
