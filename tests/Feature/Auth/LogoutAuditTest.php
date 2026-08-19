<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\AuditTrail;
use App\Models\Role;
use App\Models\User;
use App\Support\RoleRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Setiap logout tercatat di audit trail — dari pintu mana pun.
 *
 * Kembarannya, login, sudah dijamin lewat event sejak RecordLoginAudit; sisi
 * keluarnya tertinggal, jadi konsol Admin bisa menunjukkan kapan seseorang
 * masuk tapi tidak kapan ia pergi. Untuk pertanyaan audit yang sebenarnya
 * ditanyakan orang — "sesi ini terbuka dari jam berapa sampai jam berapa" —
 * separuh datanya hilang.
 *
 * Dipasang pada event Logout dengan alasan yang sama seperti login: helpdesk
 * punya dua pintu keluar (DevLoginController dan SsoController), keduanya
 * memanggil Auth::logout(), dan pintu ketiga yang ditulis besok ikut tercatat
 * tanpa perlu ada yang mengingatnya.
 */
final class LogoutAuditTest extends TestCase
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

    /** @return \Illuminate\Database\Eloquent\Collection<int,AuditTrail> */
    private function barisLogout(): \Illuminate\Database\Eloquent\Collection
    {
        return AuditTrail::where('module', 'auth')->where('action', 'logout')->get();
    }

    public function test_logout_pengembangan_tercatat(): void
    {
        $user = $this->pegawai();

        $this->post(route('login.attempt'), ['email' => $user->email]);
        $this->post(route('logout'));

        $baris = $this->barisLogout();

        $this->assertCount(1, $baris);
        $this->assertSame($user->id, $baris->first()->actor_id);
        $this->assertSame($user->id, $baris->first()->target_id);
        $this->assertSame('Andi Pratama', $baris->first()->target_name);
    }

    public function test_logout_lewat_pintu_sso_tercatat(): void
    {
        $user = $this->pegawai();

        $this->post(route('login.attempt'), ['email' => $user->email]);
        $this->post(route('sso.logout'));

        $baris = $this->barisLogout();

        $this->assertCount(1, $baris);
        $this->assertSame($user->id, $baris->first()->actor_id);
    }

    /**
     * Auth::logout() melempar event Logout walau tidak ada siapa-siapa di guard
     * (SessionGuard mengirimkannya dengan user null). Tanpa penjagaan, menekan
     * "keluar" dua kali — atau membuka URL logout langsung sebagai tamu —
     * menghasilkan baris audit tanpa pelaku.
     */
    public function test_logout_tanpa_ada_yang_login_tidak_menulis_apa_apa(): void
    {
        $this->post(route('logout'));

        $this->assertCount(0, $this->barisLogout());
    }

    /**
     * Listener ditemukan otomatis oleh Laravel lewat tipe argumen handle().
     * Mendaftarkannya sekali lagi di AppServiceProvider membuatnya terpasang
     * dua kali dan setiap logout menghasilkan dua baris kembar — persis jebakan
     * yang sempat kena di sisi login.
     */
    public function test_satu_logout_menghasilkan_tepat_satu_baris(): void
    {
        $user = $this->pegawai();

        $this->post(route('login.attempt'), ['email' => $user->email]);
        $this->post(route('logout'));

        $this->assertCount(1, $this->barisLogout());
    }

    public function test_baris_logout_membawa_ip_dan_url(): void
    {
        $user = $this->pegawai();

        $this->post(route('login.attempt'), ['email' => $user->email]);
        $this->post(route('logout'));

        $baris = $this->barisLogout()->first();

        $this->assertNotNull($baris->ip_address);
        $this->assertNotNull($baris->url);
        $this->assertStringContainsString('logout', $baris->url);
    }
}
