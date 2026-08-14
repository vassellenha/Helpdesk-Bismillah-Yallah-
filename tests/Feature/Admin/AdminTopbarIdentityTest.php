<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsRole;
use Tests\TestCase;

/**
 * Bilah atas konsol Admin harus menyebut orang yang sedang masuk.
 *
 * Delapan layar admin dulu memanggil `DummyData::currentAdmin()` — persona
 * tetap dari masa mockup yang selalu mengembalikan "Marcell Laforteza",
 * siapa pun yang masuk. Yang membuatnya bertahan lama: modal "My Profile" di
 * layar yang sama mengambil dari CurrentActor, jadi ia menampilkan nama yang
 * BENAR. Dua tempat bersebelahan menyebut dua orang berbeda, dan tidak ada
 * error apa pun yang menandainya.
 *
 * Diuji di beberapa layar sekaligus karena sumbernya sekarang komposer
 * `layouts.admin`, bukan controller masing-masing.
 */
final class AdminTopbarIdentityTest extends TestCase
{
    use ActsAsRole, RefreshDatabase;

    public function test_topbar_menyebut_nama_yang_sedang_masuk(): void
    {
        $admin = $this->actingAsRole('admin');
        $admin->update(['name' => 'Siti Rahmawati', 'jabatan' => 'Kepala Dept TI', 'unit' => 'Dept Teknologi']);

        foreach (['admin.dashboard', 'admin.users', 'admin.sla', 'admin.audit-trail'] as $layar) {
            $response = $this->get(route($layar))->assertOk();

            $response->assertSee('Siti Rahmawati', false);
            $response->assertSee('Kepala Dept TI', false);
        }
    }

    public function test_admin_kedua_melihat_namanya_sendiri(): void
    {
        $pertama = $this->actingAsRole('admin');
        $pertama->update(['name' => 'Admin Pertama']);
        $this->get(route('admin.dashboard'))->assertOk()->assertSee('Admin Pertama', false);

        // Orang kedua, sesi baru: tidak boleh melihat nama orang pertama.
        $kedua = User::factory()->create(['name' => 'Admin Kedua', 'status' => 'active', 'helpdesk_access' => 'enabled']);
        $this->actingAsUserWithRoles($kedua, 'admin');

        $response = $this->get(route('admin.dashboard'))->assertOk();

        $response->assertSee('Admin Kedua', false);
        $response->assertDontSee('data-props="{&quot;name&quot;:&quot;Admin Pertama', false);
    }
}
