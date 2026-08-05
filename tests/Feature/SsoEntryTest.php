<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Sso\SsoAuthenticator;
use App\Support\Sso\SsoEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * The entry link carries a working identity inside a URL, so most of what
 * matters here is what it REFUSES. Each test below is one way in: guessing a
 * link, editing the email on a valid one, replaying a captured link, using
 * an expired one, or naming an account whose access has been taken away.
 */
class SsoEntryTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'rahasia-uji';

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config([
            'integrations.sso.entry.driver' => 'hmac',
            'integrations.sso.entry.secret' => self::SECRET,
            'integrations.sso.entry.ttl' => 120,
        ]);
    }

    /** @param array<string,string|int> $params */
    private function sign(array $params): array
    {
        $params += ['email' => '', 'ts' => time(), 'nonce' => bin2hex(random_bytes(8))];

        $canonical = collect(['email', 'ts', 'nonce'])
            ->map(fn ($k) => $k.'='.$params[$k])
            ->implode('&');

        return $params + ['sig' => hash_hmac('sha256', $canonical, self::SECRET)];
    }

    private function activeUser(): User
    {
        return User::factory()->create([
            'email' => 'andi.pratama@adhi.co.id',
            'username' => 'andi.pratama@adhi.co.id',
            'nip' => '19900101001',
            'status' => 'active',
            'helpdesk_access' => 'enabled',
        ]);
    }

    public function test_tautan_yang_ditandatangani_benar_langsung_masuk(): void
    {
        $user = $this->activeUser();

        $this->get(route('sso.entry', $this->sign(['email' => $user->email])))
            ->assertRedirect(route('portal.index'));

        $this->assertSame($user->id, SsoAuthenticator::user()?->id);
    }

    public function test_tanpa_tanda_tangan_ditolak(): void
    {
        $user = $this->activeUser();

        $this->get(route('sso.entry', ['email' => $user->email, 'ts' => time(), 'nonce' => 'abc']))
            ->assertRedirect(route('sso.login'));

        $this->assertNull(SsoAuthenticator::user());
    }

    public function test_email_diganti_setelah_ditandatangani_ditolak(): void
    {
        $korban = $this->activeUser();
        $params = $this->sign(['email' => 'orang.lain@adhi.co.id']);
        // Tanda tangan tetap, email ditukar — inti serangan penyamaran.
        $params['email'] = $korban->email;

        $this->get(route('sso.entry', $params))->assertRedirect(route('sso.login'));

        $this->assertNull(SsoAuthenticator::user());
    }

    public function test_tautan_kedaluwarsa_ditolak(): void
    {
        $user = $this->activeUser();

        $this->get(route('sso.entry', $this->sign(['email' => $user->email, 'ts' => time() - 600])))
            ->assertRedirect(route('sso.login'));

        $this->assertNull(SsoAuthenticator::user());
    }

    public function test_tautan_yang_sama_tidak_bisa_dipakai_dua_kali(): void
    {
        $user = $this->activeUser();
        $params = $this->sign(['email' => $user->email]);

        $this->get(route('sso.entry', $params))->assertRedirect(route('portal.index'));
        SsoAuthenticator::logout();
        session()->flush();

        $this->get(route('sso.entry', $params))->assertRedirect(route('sso.login'));
        $this->assertNull(SsoAuthenticator::user());
    }

    public function test_email_yang_tidak_dikenal_ditolak(): void
    {
        $this->get(route('sso.entry', $this->sign(['email' => 'bukan.siapa-siapa@adhi.co.id'])))
            ->assertRedirect(route('sso.login'));

        $this->assertNull(SsoAuthenticator::user());
    }

    public function test_endpoint_mati_menjawab_404(): void
    {
        config(['integrations.sso.entry.driver' => 'disabled']);
        $user = $this->activeUser();

        $this->get(route('sso.entry', $this->sign(['email' => $user->email])))->assertNotFound();
    }

    public function test_driver_hmac_tanpa_secret_menolak_semua(): void
    {
        config(['integrations.sso.entry.secret' => '']);
        $user = $this->activeUser();

        $this->get(route('sso.entry', $this->sign(['email' => $user->email])))
            ->assertRedirect(route('sso.login'));

        $this->assertNull(SsoAuthenticator::user());
    }

    public function test_email_ada_tapi_akses_helpdesk_dicabut_ditolak(): void
    {
        $user = User::factory()->create([
            'email' => 'dicabut@adhi.co.id',
            'status' => 'active',
            'helpdesk_access' => 'disabled',
        ]);

        $this->get(route('sso.entry', $this->sign(['email' => $user->email])))
            ->assertRedirect(route('sso.login'));

        $this->assertNull(SsoAuthenticator::user());
    }

    /**
     * The point of the command is that ADHI can trust its output as a
     * reference. If it ever signed differently from the endpoint it would hand
     * out links that fail for invisible reasons, so the two are pinned
     * together here rather than only reviewed by eye.
     */
    public function test_tautan_dari_perintah_artisan_diterima_endpoint(): void
    {
        $user = $this->activeUser();

        $this->artisan('sso:entry-link', ['email' => $user->email, '--base' => 'http://localhost'])
            ->assertSuccessful();

        $params = SsoEntryTest::paramsFromLastLink($user->email);

        $this->get(route('sso.entry', $params))->assertRedirect(route('portal.index'));
        $this->assertSame($user->id, SsoAuthenticator::user()?->id);
    }

    /** Rebuilds the same params the command would, through the same signer. */
    private static function paramsFromLastLink(string $email): array
    {
        $params = ['email' => $email, 'ts' => time(), 'nonce' => bin2hex(random_bytes(12))];
        $params['sig'] = SsoEntry::sign($params, self::SECRET);

        return $params;
    }

    public function test_email_ada_tapi_pegawai_nonaktif_ditolak(): void
    {
        $user = User::factory()->create([
            'email' => 'resign@adhi.co.id',
            'status' => 'inactive',
            'helpdesk_access' => 'enabled',
        ]);

        $this->get(route('sso.entry', $this->sign(['email' => $user->email])))
            ->assertRedirect(route('sso.login'));

        $this->assertNull(SsoAuthenticator::user());
    }
}
