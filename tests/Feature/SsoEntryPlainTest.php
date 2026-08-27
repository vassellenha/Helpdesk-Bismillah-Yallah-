<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Jalur masuk portal tanpa tanda tangan (SSO_ENTRY_DRIVER=plain).
 *
 * Dipakai sementara sampai portal SINTA siap menandatangani tautannya. Yang
 * dikunci di sini bukan keamanannya — jalur ini memang tidak membuktikan
 * apa-apa — melainkan batas-batas yang TETAP berlaku: email harus berbentuk
 * email, akunnya harus sudah ada, akunnya harus aktif, dan jalurnya harus
 * benar-benar mati saat drivernya tidak disetel.
 */
class SsoEntryPlainTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $email, string $status = 'active'): User
    {
        $role = Role::firstOrCreate(['name' => 'Requester']);
        $user = User::factory()->create([
            'name' => 'Budi Santoso',
            'email' => $email,
            'status' => $status,
            'helpdesk_access' => 'enabled',
        ]);
        $user->roles()->attach($role->id);

        return $user;
    }

    public function test_email_yang_punya_akun_langsung_masuk(): void
    {
        config(['integrations.sso.entry.driver' => 'plain']);
        $user = $this->user('budi@adhi.co.id');

        $this->get('/auth/sso/entry?email=budi@adhi.co.id')
            ->assertRedirect(route('portal.index'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_besar_kecil_huruf_tidak_menghalangi(): void
    {
        config(['integrations.sso.entry.driver' => 'plain']);
        $user = $this->user('budi@adhi.co.id');

        $this->get('/auth/sso/entry?email=Budi@Adhi.co.id')
            ->assertRedirect(route('portal.index'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_email_tanpa_akun_ditolak(): void
    {
        config(['integrations.sso.entry.driver' => 'plain']);

        $this->get('/auth/sso/entry?email=orangasing@adhi.co.id')
            ->assertRedirect(route('sso.login'))
            ->assertSessionHas('sso_error');

        $this->assertGuest();
    }

    public function test_akun_nonaktif_ditolak(): void
    {
        config(['integrations.sso.entry.driver' => 'plain']);
        $this->user('mantan@adhi.co.id', status: 'inactive');

        $this->get('/auth/sso/entry?email=mantan@adhi.co.id')
            ->assertRedirect(route('sso.login'))
            ->assertSessionHas('sso_error');

        $this->assertGuest();
    }

    public function test_email_ngawur_ditolak(): void
    {
        config(['integrations.sso.entry.driver' => 'plain']);

        foreach (['', 'bukan-email', '-', 'tdk ada'] as $ngawur) {
            $this->get('/auth/sso/entry?email='.urlencode($ngawur))
                ->assertRedirect(route('sso.login'));

            $this->assertGuest();
        }
    }

    /**
     * Portal SINTA mendaftarkan /auth/sso/login sebagai alamat tile, bukan
     * /auth/sso/entry. Alamat itu harus menerima identitas juga.
     */
    public function test_alamat_login_ikut_menerima_identitas_dari_portal(): void
    {
        config(['integrations.sso.entry.driver' => 'plain']);
        $user = $this->user('budi@adhi.co.id');

        $this->get('/auth/sso/login?email=budi@adhi.co.id')
            ->assertRedirect(route('portal.index'));

        $this->assertAuthenticatedAs($user);
    }

    /** Tanpa identitas, alamat yang sama tetap menampilkan halaman masuk biasa. */
    public function test_alamat_login_tanpa_identitas_tetap_menampilkan_halaman(): void
    {
        config(['integrations.sso.entry.driver' => 'plain']);

        $this->get('/auth/sso/login')->assertOk()->assertViewIs('auth.login');

        $this->assertGuest();
    }

    /** Email tak dikenal tidak boleh memantul bolak-balik antara dua alamat. */
    public function test_email_tak_dikenal_di_alamat_login_tidak_bikin_loop(): void
    {
        config(['integrations.sso.entry.driver' => 'plain']);

        $this->get('/auth/sso/login?email=orangasing@adhi.co.id')
            ->assertRedirect(route('sso.login'))
            ->assertSessionHas('sso_error');

        // Percobaan kedua sudah tanpa query string -> berhenti di halaman.
        $this->get(route('sso.login'))->assertOk();

        $this->assertGuest();
    }

    /**
     * Bentuk yang sebenarnya dipakai portal SINTA: Auth Type REMOTE_LOGIN,
     * form POST ke Login URL dengan User Param "email" dan Pass Param
     * "password". Sebelum ini rutenya GET saja, jadi POST-nya dijawab 405.
     */
    public function test_post_dari_portal_sinta_langsung_masuk(): void
    {
        config(['integrations.sso.entry.driver' => 'plain']);
        $user = $this->user('budi@adhi.co.id');

        $this->post('/auth/sso/login', [
            'email' => 'budi@adhi.co.id',
            'password' => 'apa-pun-diabaikan',
        ])->assertRedirect(route('portal.index'));

        $this->assertAuthenticatedAs($user);
    }

    /**
     * Password yang ikut dikirim portal tidak menentukan apa pun — helpdesk
     * tidak menyimpan kata sandi siapa pun. Dikunci di sini supaya tidak ada
     * yang kelak menambahkan pemeriksaan yang mustahil dipenuhi.
     */
    public function test_password_dari_portal_diabaikan(): void
    {
        config(['integrations.sso.entry.driver' => 'plain']);
        $user = $this->user('budi@adhi.co.id');

        $this->post('/auth/sso/login', ['email' => 'budi@adhi.co.id'])
            ->assertRedirect(route('portal.index'));

        $this->assertAuthenticatedAs($user);
    }

    /** POST tanpa identitas tetap menampilkan halaman, bukan 405 atau 500. */
    public function test_post_tanpa_email_tidak_meledak(): void
    {
        config(['integrations.sso.entry.driver' => 'plain']);

        $this->post('/auth/sso/login', [])->assertOk();

        $this->assertGuest();
    }

    public function test_jalur_ini_mati_saat_driver_tidak_disetel(): void
    {
        config(['integrations.sso.entry.driver' => 'disabled']);
        $this->user('budi@adhi.co.id');

        // 404, bukan "fitur dimatikan": deployment yang tidak memakainya tidak
        // memberi petunjuk apa pun bahwa jalur ini ada.
        $this->get('/auth/sso/entry?email=budi@adhi.co.id')->assertNotFound();

        $this->assertGuest();
    }
}
