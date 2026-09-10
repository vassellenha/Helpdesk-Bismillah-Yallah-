<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\IssueCategory;
use App\Models\Role;
use App\Models\ServiceCatalogService;
use App\Models\ServiceCatalogSubcategory;
use App\Models\ServiceCatalogSubject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsRole;
use Tests\TestCase;

/**
 * Layar Service Catalog harus mengirim bahan yang dibutuhkan tab Subkategori:
 * daftar Team Lead BPO yang boleh dipilih, dan keadaan penugasan tiap
 * Subkategori.
 *
 * Daftar pilihannya disaring di server, bukan di React. Nama yang muncul di
 * dropdown adalah janji bahwa orang itu bisa dipasang; kalau daftarnya memuat
 * orang yang kemudian ditolak validasi, Admin baru tahu setelah menekan
 * simpan — dan pesannya tidak menjelaskan kenapa nama itu ditawarkan sejak
 * awal.
 */
final class ServiceCatalogPropsTest extends TestCase
{
    use ActsAsRole, RefreshDatabase;

    public function test_layar_mengirim_daftar_team_lead_bpo_yang_bisa_dipilih(): void
    {
        $this->actingAsRole('admin');

        $aktif = $this->userDenganRole('Rizky Hidayat', 'Team Lead BPO');
        $dimatikan = $this->userDenganRole('Denny Firmansyah', 'Team Lead BPO');
        $dimatikan->update(['helpdesk_access' => 'disabled']);
        $roleLain = $this->userDenganRole('Agung Wijayanto', 'Team Lead IT');

        $nama = collect($this->get('/admin/service-catalog')->assertOk()->viewData('teamLeadBpoOptions'))
            ->pluck('name')->all();

        $this->assertContains('Rizky Hidayat', $nama);
        $this->assertNotContains(
            'Denny Firmansyah',
            $nama,
            'Team Lead yang aksesnya dicabut tidak boleh ditawarkan — memasangnya sama dengan tidak memasang siapa pun',
        );
        $this->assertNotContains(
            'Agung Wijayanto',
            $nama,
            'pemegang role lain tidak boleh ditawarkan sebagai Team Lead BPO',
        );

        $this->assertNotContains($aktif->id, [null]);
    }

    public function test_daftar_subkategori_membawa_team_lead_dan_jumlah_subjek(): void
    {
        $this->actingAsRole('admin');

        $lead = $this->userDenganRole('Rizky Hidayat', 'Team Lead BPO');
        $service = ServiceCatalogService::create(['name' => 'SAP']);
        $sub = ServiceCatalogSubcategory::create([
            'service_id' => $service->id,
            'name' => 'Akses & Otorisasi',
            'team_lead_bpo_user_id' => $lead->id,
        ]);

        $this->subjek($sub, aktif: true);
        $this->subjek($sub, aktif: false);

        $baris = collect($this->get('/admin/service-catalog')->assertOk()->viewData('subcategories'))
            ->firstWhere('id', $sub->id);

        $this->assertSame('Akses & Otorisasi', $baris['name']);
        $this->assertSame('SAP', $baris['service_name']);
        $this->assertSame($lead->id, $baris['team_lead_bpo_user_id']);
        $this->assertSame('Rizky Hidayat', $baris['team_lead_bpo_name']);
        $this->assertSame(2, $baris['subject_count']);
        $this->assertSame(
            1,
            $baris['active_subject_count'],
            'Admin perlu tahu berapa Subjek yang benar-benar berpindah tangan sebelum menekan dropdown',
        );
    }

    public function test_subkategori_tanpa_team_lead_dikirim_sebagai_null(): void
    {
        $this->actingAsRole('admin');

        $service = ServiceCatalogService::create(['name' => 'MAILIA']);
        $sub = ServiceCatalogSubcategory::create(['service_id' => $service->id, 'name' => 'Akun Email']);

        $baris = collect($this->get('/admin/service-catalog')->assertOk()->viewData('subcategories'))
            ->firstWhere('id', $sub->id);

        $this->assertNull($baris['team_lead_bpo_user_id']);
        $this->assertNull($baris['team_lead_bpo_name']);
    }

    private function userDenganRole(string $nama, string $role): User
    {
        $row = Role::firstOrCreate(['name' => $role], ['type' => 'system', 'status' => 'active']);

        $user = User::factory()->create([
            'name' => $nama,
            'status' => 'active',
            'helpdesk_access' => 'enabled',
        ]);
        $user->roles()->syncWithoutDetaching([$row->id]);

        return $user;
    }

    private function subjek(ServiceCatalogSubcategory $sub, bool $aktif): ServiceCatalogSubject
    {
        return ServiceCatalogSubject::create([
            'issue_category_id' => IssueCategory::firstOrCreate(['name' => 'Incident'])->id,
            'service_id' => $sub->service_id,
            'subcategory_id' => $sub->id,
            'name' => 'Subjek '.random_int(1000, 9999),
            'requires_approval' => false,
            'support_level' => 1,
            'is_active' => $aktif,
        ]);
    }
}
