<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsRole;
use Tests\TestCase;

/**
 * User yang aksesnya dimatikan Admin harus HILANG dari seluruh daftar pilihan.
 *
 * Aturannya sendiri sudah lama ada — User::isActive() menuntut DUA kolom:
 * `status` (kepegawaian, ditulis EmployeeSync) dan `helpdesk_access` (saklar
 * Admin). Yang bocor adalah daftar-daftar yang menyaring `status` saja,
 * sehingga user yang baru saja dinonaktifkan Admin tetap muncul dan tetap bisa
 * dipilih sebagai tujuan approval.
 *
 * Bocornya diam: tidak ada error, tidak ada layar merah. Tiket berangkat ke
 * approver yang tidak akan pernah bisa membukanya — SsoAuthenticator sudah
 * menolak sesi mereka — dan tiket itu menggantung tanpa ada yang tahu sebabnya.
 */
final class DisabledUserIsHiddenTest extends TestCase
{
    use ActsAsRole, RefreshDatabase;

    public function test_approver_yang_dinonaktifkan_admin_hilang_dari_daftar_pilihan(): void
    {
        // Daftar approver adalah bahan formulir tiket baru, jadi ia ikut
        // dijaga `role:requester` bersama endpoint katalog lainnya.
        $this->actingAsRole('requester');

        $aktif = $this->approver('Gofar Hilman');
        $this->approver('Karina AESPA', helpdeskAccess: 'disabled');

        $daftar = $this->getJson(route('approvers.index'))->assertOk()->json();

        $this->assertCount(1, $daftar);
        $this->assertSame($aktif->id, $daftar[0]['id']);
    }

    /** Nonaktif dari sisi kepegawaian juga tetap disaring, seperti sebelumnya. */
    public function test_approver_yang_nonaktif_kepegawaian_tetap_hilang(): void
    {
        $this->actingAsRole('requester');

        $this->approver('Gofar Hilman');
        $this->approver('Karina AESPA', status: 'inactive');

        $daftar = $this->getJson(route('approvers.index'))->assertOk()->json();

        $this->assertCount(1, $daftar);
        $this->assertSame('Gofar Hilman', $daftar[0]['name']);
    }

    public function test_scope_active_menuntut_kedua_kolom(): void
    {
        $this->approver('Aktif');
        $this->approver('Dimatikan Admin', helpdeskAccess: 'disabled');
        $this->approver('Sudah Resign', status: 'inactive');
        $this->approver('Keduanya', status: 'inactive', helpdeskAccess: 'disabled');

        $this->assertSame(['Aktif'], User::query()->active()->pluck('name')->all());
    }

    private function approver(
        string $name,
        string $status = 'active',
        string $helpdeskAccess = 'enabled',
    ): User {
        $user = User::factory()->create([
            'name' => $name,
            'status' => $status,
            'helpdesk_access' => $helpdeskAccess,
        ]);

        $user->roles()->attach(Role::firstOrCreate(
            ['name' => 'Approver'],
            ['status' => 'active'],
        ));

        return $user;
    }
}
