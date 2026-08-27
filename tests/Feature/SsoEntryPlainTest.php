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
