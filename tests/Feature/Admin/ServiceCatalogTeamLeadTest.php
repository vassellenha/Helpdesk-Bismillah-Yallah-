<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\AuditTrail;
use App\Models\Role;
use App\Models\ServiceCatalogService;
use App\Models\ServiceCatalogSubcategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsRole;
use Tests\TestCase;

/**
 * Admin menugaskan seorang Team Lead BPO ke satu Subkategori katalog. Dari
 * situlah seluruh cakupan Team Lead diturunkan — siapa yang dia awasi, tiket
 * mana yang dia lihat, dan ke siapa dia boleh memindahkan PIC.
 *
 * Karena itu gerbangnya harus rapat pada dua hal yang tidak kelihatan di
 * layar. Pertama, orang yang ditugaskan wajib benar-benar memegang role
 * "Team Lead BPO" — kalau tidak, Subkategori itu diawasi orang yang portal
 * Team Lead-nya sendiri menolaknya. Kedua, akunnya wajib masih hidup:
 * menugaskan Subkategori ke akun terkunci sama saja dengan tidak
 * menugaskannya kepada siapa pun, dan tidak ada satu layar pun yang akan
 * berbunyi. Dua-duanya gagal dalam diam, dan diam itulah yang berbahaya.
 */
final class ServiceCatalogTeamLeadTest extends TestCase
{
    use ActsAsRole, RefreshDatabase;

    public function test_admin_menugaskan_team_lead_bpo_ke_subkategori(): void
    {
        $this->actingAsRole('admin');
        $subcategory = $this->subkategori();
        $lead = $this->teamLeadBpo('Rizky Hidayat');

        $this->patchJson($this->url($subcategory), ['team_lead_bpo_user_id' => $lead->id])
            ->assertOk()
            ->assertJsonPath('team_lead_bpo_name', 'Rizky Hidayat');

        $this->assertDatabaseHas('service_catalog_subcategories', [
            'id' => $subcategory->id,
            'team_lead_bpo_user_id' => $lead->id,
        ]);
    }

    /**
     * Layar menggabungkan jawaban PATCH ini ke barisnya. Kalau jawabannya
     * tidak membawa hitungan Subjek, kolom "Subjek" berubah jadi 0 tepat
     * setelah penugasan tersimpan — seolah Subkategori itu baru saja
     * dikosongkan. Ditemukan saat menguji di browser.
     */
    public function test_jawaban_penugasan_tetap_membawa_jumlah_subjek(): void
    {
        $this->actingAsRole('admin');
        $subcategory = $this->subkategori();
        $lead = $this->teamLeadBpo('Rizky Hidayat');

        $this->subjek($subcategory, aktif: true);
        $this->subjek($subcategory, aktif: false);

        $this->patchJson($this->url($subcategory), ['team_lead_bpo_user_id' => $lead->id])
            ->assertOk()
            ->assertJsonPath('subject_count', 2)
            ->assertJsonPath('active_subject_count', 1);
    }

    public function test_penugasan_tercatat_di_audit_trail(): void
    {
        $admin = $this->actingAsRole('admin');
        $subcategory = $this->subkategori('Akses & Otorisasi');
        $lead = $this->teamLeadBpo('Rizky Hidayat');

        $this->patchJson($this->url($subcategory), ['team_lead_bpo_user_id' => $lead->id])->assertOk();

        $jejak = AuditTrail::where('action', 'assign_team_lead')->latest('id')->first();

        $this->assertNotNull($jejak, 'penugasan Team Lead tidak tercatat di Audit Trail');
        $this->assertSame($admin->id, $jejak->actor_id);
        $this->assertSame('service_catalog', $jejak->module);
        $this->assertSame('subcategory', $jejak->target_type);
        $this->assertSame('Akses & Otorisasi', $jejak->target_name);
        $this->assertSame('Belum ditugaskan', $jejak->old_value['team_lead_bpo'] ?? null);
        $this->assertSame('Rizky Hidayat', $jejak->new_value['team_lead_bpo'] ?? null);
    }

    public function test_mencabut_penugasan_mengembalikan_subkategori_ke_semua_lead(): void
    {
        $this->actingAsRole('admin');
        $lead = $this->teamLeadBpo('Rizky Hidayat');
        $subcategory = $this->subkategori();
        $subcategory->update(['team_lead_bpo_user_id' => $lead->id]);

        $this->patchJson($this->url($subcategory), ['team_lead_bpo_user_id' => null])->assertOk();

        $this->assertNull($subcategory->fresh()->team_lead_bpo_user_id);

        $jejak = AuditTrail::where('action', 'assign_team_lead')->latest('id')->first();
        $this->assertSame('Rizky Hidayat', $jejak->old_value['team_lead_bpo'] ?? null);
        $this->assertSame('Belum ditugaskan', $jejak->new_value['team_lead_bpo'] ?? null);
    }

    /**
     * Kalau ini lolos, Subkategori diawasi orang yang tidak punya portal
     * Team Lead sama sekali — dan tiketnya tidak muncul di layar siapa pun.
     */
    public function test_user_tanpa_role_team_lead_bpo_ditolak(): void
    {
        $this->actingAsRole('admin');
        $subcategory = $this->subkategori();
        $orangLain = User::factory()->create(['status' => 'active', 'helpdesk_access' => 'enabled']);

        $this->patchJson($this->url($subcategory), ['team_lead_bpo_user_id' => $orangLain->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors('team_lead_bpo_user_id');

        $this->assertNull(
            $subcategory->fresh()->team_lead_bpo_user_id,
            'user tanpa role Team Lead BPO tidak boleh tersimpan sebagai pengawas Subkategori',
        );
    }

    /**
     * Akun terkunci tidak bisa masuk Helpdesk sama sekali (CurrentActor::
     * mustBeActive), jadi menugaskan Subkategori kepadanya menghasilkan
     * Subkategori yang tampak bertuan tapi sebenarnya tidak diawasi siapa pun.
     */
    public function test_team_lead_bpo_yang_aksesnya_dimatikan_tidak_bisa_ditugaskan(): void
    {
        $this->actingAsRole('admin');
        $subcategory = $this->subkategori();
        $lead = $this->teamLeadBpo('Rizky Hidayat');
        $lead->update(['helpdesk_access' => 'disabled']);

        $this->patchJson($this->url($subcategory), ['team_lead_bpo_user_id' => $lead->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors('team_lead_bpo_user_id');

        $this->assertNull(
            $subcategory->fresh()->team_lead_bpo_user_id,
            'Team Lead yang aksesnya dicabut tidak boleh dipasang sebagai pengawas',
        );
    }

    public function test_selain_admin_ditolak(): void
    {
        $subcategory = $this->subkategori();
        $lead = $this->teamLeadBpo('Rizky Hidayat');
        $this->actingAsRole('team-lead-bpo');

        $this->patchJson($this->url($subcategory), ['team_lead_bpo_user_id' => $lead->id])
            ->assertStatus(403);

        $this->assertNull(
            $subcategory->fresh()->team_lead_bpo_user_id,
            'hanya Admin yang boleh membagi cakupan Team Lead',
        );
    }

    private function url(ServiceCatalogSubcategory $subcategory): string
    {
        return "/admin/service-catalog/subcategories/{$subcategory->id}/team-lead";
    }

    private function subkategori(string $nama = 'Akun Email'): ServiceCatalogSubcategory
    {
        $service = ServiceCatalogService::create(['name' => 'SAP '.random_int(1000, 9999)]);

        return ServiceCatalogSubcategory::create(['service_id' => $service->id, 'name' => $nama]);
    }

    private function subjek(ServiceCatalogSubcategory $sub, bool $aktif): void
    {
        \App\Models\ServiceCatalogSubject::create([
            'issue_category_id' => \App\Models\IssueCategory::firstOrCreate(['name' => 'Incident'])->id,
            'service_id' => $sub->service_id,
            'subcategory_id' => $sub->id,
            'name' => 'Subjek '.random_int(1000, 9999),
            'requires_approval' => false,
            'support_level' => 1,
            'is_active' => $aktif,
        ]);
    }

    private function teamLeadBpo(string $nama): User
    {
        $role = Role::firstOrCreate(
            ['name' => 'Team Lead BPO'],
            ['type' => 'system', 'status' => 'active'],
        );

        $user = User::factory()->create([
            'name' => $nama,
            'status' => 'active',
            'helpdesk_access' => 'enabled',
        ]);
        $user->roles()->syncWithoutDetaching([$role->id]);

        return $user;
    }
}
