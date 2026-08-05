<?php

namespace Tests\Feature;

use App\Support\Sso\MockSsoProvider;
use App\Support\Sso\SsoAuthenticator;
use Tests\TestCase;

/**
 * Mock SSO hands out any employee's identity without a password. It exists for
 * demos; in production it is a way for anyone to become the Administrator. A
 * stale .env or a forgotten default is all it takes to ship it, so production
 * refuses the driver instead of trusting the deploy to be careful.
 */
class SsoMockGuardTest extends TestCase
{
    private function asProduction(): void
    {
        app()->detectEnvironment(fn () => 'production');
    }

    public function test_mock_dipakai_normal_di_luar_produksi(): void
    {
        config(['integrations.sso.driver' => 'mock']);

        $this->assertTrue(SsoAuthenticator::isMock());
        $this->assertInstanceOf(MockSsoProvider::class, SsoAuthenticator::provider());
    }

    public function test_mock_ditolak_di_produksi(): void
    {
        config(['integrations.sso.driver' => 'mock']);
        $this->asProduction();

        $this->assertNotInstanceOf(MockSsoProvider::class, SsoAuthenticator::provider());
        $this->assertFalse(
            SsoAuthenticator::provider()->isConfigured(),
            'Provider pengganti harus dianggap belum terkonfigurasi, supaya alur login berhenti dengan pesan.'
        );
    }

    public function test_layar_login_tidak_menawarkan_mode_mock_di_produksi(): void
    {
        config(['integrations.sso.driver' => 'mock']);
        $this->asProduction();

        // Kalau ini true, layar login menampilkan daftar pegawai untuk dipilih.
        $this->assertFalse(SsoAuthenticator::isMock());
    }

    public function test_oidc_tidak_terpengaruh_pengaman(): void
    {
        config(['integrations.sso.driver' => 'oidc']);
        $this->asProduction();

        $this->assertFalse(SsoAuthenticator::isMock());
        $this->assertNotInstanceOf(MockSsoProvider::class, SsoAuthenticator::provider());
    }
}
