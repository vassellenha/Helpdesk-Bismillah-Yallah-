<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\AuditTrail;
use App\Models\ServiceCatalogSubject;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsRole;
use Tests\TestCase;

/**
 * Menambah, mengubah, dan menonaktifkan subjek katalog semuanya tercatat di
 * Audit Trail — hanya penghapusan yang tidak. Padahal justru penghapusan yang
 * permanen dan tidak bisa dibatalkan, dan subtitle Audit Trail Viewer sendiri
 * menjanjikan cakupan Service Catalog. SLA Policy sudah mencatat penghapusan,
 * jadi ini satu aksi yang terlewat, bukan pola yang disengaja.
 *
 * Ditemukan saat UAT test case 18.
 */
final class ServiceCatalogDeleteIsAuditedTest extends TestCase
{
    use ActsAsRole, RefreshDatabase;

    public function test_menghapus_subjek_katalog_tercatat_pada_audit_trail(): void
    {
        $admin = $this->admin();
        $subject = $this->subjek('Reset Password E-mail (Uji)');

        $this->deleteJson("/admin/service-catalog/subjects/{$subject->id}")
            ->assertOk();

        $this->assertDatabaseMissing('service_catalog_subjects', ['id' => $subject->id]);

        $jejak = AuditTrail::where('module', 'service_catalog')->where('action', 'delete')->latest('id')->first();

        $this->assertNotNull($jejak, 'penghapusan subjek katalog tidak tercatat di Audit Trail');
        $this->assertSame($admin->id, $jejak->actor_id);
        $this->assertSame('subject', $jejak->target_type);
        $this->assertSame('Reset Password E-mail (Uji)', $jejak->target_name);
        $this->assertStringContainsString('Reset Password E-mail (Uji)', (string) $jejak->description);
    }

    public function test_nilai_sebelum_penghapusan_ikut_tersimpan(): void
    {
        $admin = $this->admin();
        $subject = $this->subjek('Subjek Akan Dihapus');

        $this->deleteJson("/admin/service-catalog/subjects/{$subject->id}")->assertOk();

        $jejak = AuditTrail::where('action', 'delete')->latest('id')->first();

        // Setelah baris hilang, satu-satunya jejak isinya ada di old_value.
        // Kuncinya mengikuti snapshot yang sudah dipakai update(): 'subject'.
        $this->assertSame('Subjek Akan Dihapus', $jejak->old_value['subject'] ?? null);
        $this->assertSame('active', $jejak->old_value['status'] ?? null);
        $this->assertNull($jejak->new_value);
    }

    private function admin(): User
    {
        return $this->actingAsRole('admin');
    }

    private function subjek(string $nama): ServiceCatalogSubject
    {
        $serviceId = DB::table('service_catalog_services')->insertGetId(['name' => 'MAILIA '.random_int(1000, 9999)]);
        $subcategoryId = DB::table('service_catalog_subcategories')->insertGetId([
            'service_id' => $serviceId,
            'name' => 'AKUN EMAIL '.random_int(1000, 9999),
        ]);

        return ServiceCatalogSubject::create([
            'issue_category_id' => DB::table('issue_categories')->insertGetId(['name' => 'Incident '.random_int(1000, 9999)]),
            'service_id' => $serviceId,
            'subcategory_id' => $subcategoryId,
            'name' => $nama,
            'requires_approval' => false,
            'support_level' => 1,
            'is_active' => true,
        ]);
    }
}
