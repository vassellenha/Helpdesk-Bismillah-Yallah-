<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\AuditTrail;
use App\Models\Role;
use App\Models\User;
use App\Support\RoleRegistry;
use App\Support\Sso\SsoAuthenticator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Setiap login tercatat di audit trail — dari pintu mana pun.
 *
 * Pernah bocor tanpa gejala: pencatatannya duduk di dalam
 * SsoAuthenticator::login(), lalu login pengembangan ditambahkan dan memanggil
 * Auth::login() sendiri tanpa lewat kelas itu. Tidak ada error, tidak ada yang
 * gagal — audit trail-nya hanya kosong, dan baru ketahuan saat ada yang
 * mencarinya.
 *
 * Sekarang dijamin event Login, jadi pintu masuk apa pun yang ditulis kemudian
 * ikut tercatat tanpa perlu diingat.
 */
final class LoginAuditTest extends TestCase
{
    use RefreshDatabase;

    private function pegawai(string $email = 'andi@adhi.co.id'): User
    {
        $user = User::factory()->create([
            'name' => 'Andi Pratama',
            'email' => $email,
            'status' => 'active',
            'helpdesk_access' => 'enabled',
        ]);

        $user->roles()->attach(Role::firstOrCreate(
            ['name' => RoleRegistry::roleNameFor('requester')],
            ['type' => 'system', 'status' => 'active'],
        )->id);

        return $user;
    }

    private function barisLogin(): \Illuminate\Database\Eloquent\Collection
    {
        return AuditTrail::where('module', 'auth')->where('action', 'login')->get();
    }

    public function test_login_pengembangan_tercatat(): void
    {
        $user = $this->pegawai();

        $this->post(route('login.attempt'), ['email' => 'andi@adhi.co.id'])
            ->assertRedirect(route('dashboard.requester'));

        $baris = $this->barisLogin();

        $this->assertCount(1, $baris);
        $this->assertSame($user->id, $baris->first()->actor_id);
        $this->assertStringContainsString('Andi Pratama', (string) $baris->first()->description);
    }

    public function test_login_lewat_pintu_admin_juga_tercatat(): void
    {
        $admin = $this->pegawai('marcell@adhi.co.id');
        $admin->roles()->attach(Role::firstOrCreate(
            ['name' => RoleRegistry::roleNameFor('admin')],
            ['type' => 'system', 'status' => 'active'],
        )->id);

        $this->post(route('admin.login.attempt'), ['email' => 'marcell@adhi.co.id'])
            ->assertRedirect(route('admin.dashboard'));

        $this->assertCount(1, $this->barisLogin());
    }

    public function test_login_sso_tetap_tercatat_dan_hanya_sekali(): void
    {
        $user = $this->pegawai();

        SsoAuthenticator::login($user);

        // Dulu SsoAuthenticator menulis barisnya sendiri. Sejak pencatatan
        // pindah ke listener, menyisakan tulisan lama di sana akan menghasilkan
        // DUA baris untuk satu login.
        $this->assertCount(1, $this->barisLogin());
    }

    public function test_login_yang_ditolak_tidak_meninggalkan_jejak(): void
    {
        $this->pegawai();

        $this->post(route('login.attempt'), ['email' => 'bukan.siapa.siapa@adhi.co.id'])
            ->assertSessionHasErrors('email');

        $this->assertCount(0, $this->barisLogin());
    }

    public function test_akun_nonaktif_ditolak_tanpa_dicatat_sebagai_login(): void
    {
        $user = $this->pegawai('resign@adhi.co.id');
        $user->update(['status' => 'inactive']);

        $this->post(route('login.attempt'), ['email' => 'resign@adhi.co.id'])
            ->assertSessionHasErrors('email');

        $this->assertCount(0, $this->barisLogin());
    }

    /**
     * actingAs() memasang user langsung ke guard tanpa melewati
     * SessionGuard::login(), jadi ia tidak memicu event Login. Kalau suatu saat
     * itu berubah, ratusan tes yang sekadar butuh identitas akan mulai
     * menghasilkan baris audit palsu — dan angka di layar Audit Trail ikut
     * salah tanpa ada yang gagal.
     */
    public function test_actingas_di_tes_tidak_menghasilkan_baris_audit(): void
    {
        $this->actingAs($this->pegawai());

        $this->get(route('dashboard.requester'))->assertOk();

        $this->assertCount(0, $this->barisLogin());
    }

    public function test_baris_login_memuat_role_yang_dipegang(): void
    {
        $this->pegawai();

        $this->post(route('login.attempt'), ['email' => 'andi@adhi.co.id']);

        $this->assertSame(
            ['Requester'],
            $this->barisLogin()->first()->new_value['roles'] ?? null,
        );
    }
}
