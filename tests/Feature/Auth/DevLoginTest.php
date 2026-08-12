<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\Role;
use App\Models\User;
use App\Support\RoleRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Login pengembangan — cukup email, tanpa password.
 *
 * Dua pintu: /login untuk semua pegawai, /admin/login khusus pemegang role
 * Administrator. Yang berbeda hanya gerbang role setelah orangnya dikenali, dan
 * layar tempat ia mendarat.
 */
final class DevLoginTest extends TestCase
{
    use RefreshDatabase;

    private function user(array $attrs, array $roleKeys = []): User
    {
        $user = User::factory()->create(array_merge([
            'status' => 'active',
            'helpdesk_access' => 'enabled',
        ], $attrs));

        foreach ($roleKeys as $key) {
            $user->roles()->attach(Role::firstOrCreate(
                ['name' => RoleRegistry::roleNameFor($key)],
                ['type' => 'system', 'status' => 'active'],
            )->id);
        }

        return $user;
    }

    private function masuk(string $email, bool $admin = false)
    {
        return $this->post(
            $admin ? route('admin.login.attempt') : route('login.attempt'),
            ['email' => $email],
        );
    }

    public function test_masuk_dengan_email(): void
    {
        $user = $this->user(['email' => 'andi@adhi.co.id'], ['requester']);

        $this->masuk('andi@adhi.co.id')->assertRedirect(route('dashboard.requester'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_email_tidak_peduli_besar_kecil_huruf(): void
    {
        $user = $this->user(['email' => 'andi@adhi.co.id'], ['requester']);

        $this->masuk('Andi@ADHI.co.id')->assertRedirect(route('dashboard.requester'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_identitas_tak_dikenal_ditolak(): void
    {
        $this->user(['email' => 'andi@adhi.co.id'], ['requester']);

        $this->masuk('bukan.siapa.siapa@adhi.co.id')->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_kolom_kosong_ditolak(): void
    {
        $this->masuk('')->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_yang_jelas_bukan_email_ditolak_di_muka(): void
    {
        // Aturan `email` menolaknya sebagai galat validasi, bukan meneruskannya
        // ke basis data lalu kembali sebagai "akun tidak ditemukan" — dua hal
        // berbeda yang pantas dibalas pesan berbeda.
        $this->masuk('08123456789')->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    /**
     * Indeks unik `users_email_unique` TIDAK menutup celah ini: keunikan
     * diperiksa apa adanya, sedangkan pencarian login lewat LOWER(). Dua baris
     * yang cuma beda kapitalisasi lolos indeks — dan keduanya cocok dengan satu
     * pencarian yang sama.
     *
     * Ditemukan justru karena tes versi pertama memakai dua email identik dan
     * ditolak basis data; asumsi awal saya bahwa email tidak berindeks unik
     * ternyata keliru.
     */
    public function test_email_yang_hanya_beda_kapitalisasi_ditolak_bukan_ditebak(): void
    {
        $this->user(['email' => 'kembar@adhi.co.id'], ['requester']);
        $this->user(['email' => 'Kembar@adhi.co.id'], ['approver']);

        $this->masuk('kembar@adhi.co.id')->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_akun_nonaktif_ditolak(): void
    {
        $this->user(['email' => 'resign@adhi.co.id', 'status' => 'inactive'], ['requester']);

        $this->masuk('resign@adhi.co.id')->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_administrator_masuk_lewat_pintu_admin(): void
    {
        $admin = $this->user(['email' => 'marcell@adhi.co.id'], ['requester', 'admin']);

        $this->masuk('marcell@adhi.co.id', admin: true)->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($admin);
    }

    /**
     * Orang yang sama, identitas yang sama — tapi pintu biasa mengantarnya ke
     * layar Requester. Yang menentukan tujuan adalah pintu yang dipakai, bukan
     * role tertinggi yang dipegang.
     */
    public function test_administrator_lewat_pintu_biasa_mendarat_di_layar_pegawai(): void
    {
        $this->user(['email' => 'marcell@adhi.co.id'], ['requester', 'admin']);

        $this->masuk('marcell@adhi.co.id')->assertRedirect(route('dashboard.requester'));
    }

    public function test_pintu_admin_menolak_akun_tanpa_role_administrator(): void
    {
        $this->user(['email' => 'andi@adhi.co.id'], ['requester']);

        $this->masuk('andi@adhi.co.id', admin: true)->assertSessionHasErrors('email');

        // Ditolak DAN tidak ditinggalkan setengah masuk.
        $this->assertGuest();
    }

    /** Tautan dalam (mis. dari email notifikasi) tetap dituju — bila berhak. */
    public function test_tujuan_semula_dihormati_bila_berhak(): void
    {
        $this->user(['email' => 'karina@adhi.co.id'], ['requester', 'approver']);

        $this->get(route('dashboard.approver'))->assertRedirect(route('login'));

        $this->masuk('karina@adhi.co.id')->assertRedirect(route('dashboard.approver'));
    }

    /**
     * Tapi tujuan yang BUKAN haknya diabaikan — kalau tidak, orangnya diantar
     * ke halaman masuk, berhasil masuk, lalu disambut 403 seolah-olah login-nya
     * yang gagal.
     */
    public function test_tujuan_semula_diabaikan_bila_tidak_berhak(): void
    {
        $this->user(['email' => 'andi@adhi.co.id'], ['requester']);

        $this->get(route('dashboard.support'))->assertRedirect(route('login'));

        $this->masuk('andi@adhi.co.id')->assertRedirect(route('dashboard.requester'));
    }

    public function test_keluar_memutus_sesi(): void
    {
        $user = $this->user(['email' => 'andi@adhi.co.id'], ['requester']);
        $this->actingAs($user);

        $this->post(route('logout'))->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_user_tanpa_role_diantar_ke_portal_yang_menjelaskan(): void
    {
        $this->user(['email' => 'baru@adhi.co.id']);

        $this->masuk('baru@adhi.co.id')->assertRedirect(route('portal.index'));

        $this->get(route('portal.index'))
            ->assertOk()
            ->assertSee('belum diberi role apa pun', escape: false);
    }

    /*
    |---------------------------------------------------------------------------
    | Gerbang lingkungan — SATU-SATUNYA hal yang memisahkan alat uji ini dari
    | pembajakan akun, karena tidak ada kredensial apa pun yang diminta.
    |---------------------------------------------------------------------------
    */

    public function test_di_produksi_form_login_dialihkan_ke_sso(): void
    {
        config(['helpdesk.dev_login' => false]);

        $this->get(route('login'))->assertRedirect(route('sso.login'));
        $this->get(route('admin.login'))->assertRedirect(route('sso.login'));
    }

    public function test_di_produksi_pengiriman_identitas_ditolak_mentah(): void
    {
        config(['helpdesk.dev_login' => false]);
        $this->user(['email' => 'andi@adhi.co.id'], ['requester']);

        // 404, bukan sekadar gagal masuk: rutenya memang tidak ada di sana.
        $this->masuk('andi@adhi.co.id')->assertNotFound();
        $this->masuk('andi@adhi.co.id', admin: true)->assertNotFound();

        $this->assertGuest();
    }

    /**
     * `route('login')` harus tetap terdaftar meski isinya dimatikan: middleware
     * `auth` mengantar tamu ke nama itu, dan nama yang hilang melempar
     * RouteNotFoundException — 500 di tempat yang seharusnya halaman masuk.
     */
    public function test_nama_rute_login_tetap_ada_saat_login_dev_dimatikan(): void
    {
        config(['helpdesk.dev_login' => false]);

        $this->get(route('dashboard.requester'))->assertRedirect(route('login'));
    }
}
