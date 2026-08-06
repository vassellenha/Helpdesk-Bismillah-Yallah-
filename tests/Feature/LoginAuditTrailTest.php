<?php

namespace Tests\Feature;

use App\Models\AuditTrail;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * SsoAuthenticator::login() is the one place every role — requester, approver,
 * support IT, support BPO, team lead, admin — actually opens the helpdesk, so
 * that's where a "login" audit entry belongs. These tests go in through the
 * public SSO entry link (same as SsoEntryTest) rather than calling the
 * authenticator directly, so they also prove the IP/URL auto-capture in
 * AuditTrail::record() works for a real routed request.
 */
class LoginAuditTrailTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'rahasia-uji-login';

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

    private function userWithRole(string $roleName): User
    {
        $user = User::factory()->create([
            'email' => 'login.audit@adhi.co.id',
            'username' => 'login.audit@adhi.co.id',
            'nip' => '19950418199',
            'status' => 'active',
            'helpdesk_access' => 'enabled',
        ]);

        $role = Role::firstOrCreate(['name' => $roleName]);
        $user->roles()->attach($role);

        return $user;
    }

    public function test_login_via_sso_entry_menulis_audit_trail_login(): void
    {
        $user = $this->userWithRole('Requester');

        $this->get(route('sso.entry', $this->sign(['email' => $user->email])))
            ->assertRedirect(route('portal.index'));

        $entry = AuditTrail::where('module', 'auth')->where('action', 'login')->first();

        $this->assertNotNull($entry);
        $this->assertSame($user->id, $entry->actor_id);
        $this->assertSame($user->id, $entry->target_id);
        $this->assertNotNull($entry->ip_address);
        $this->assertNotNull($entry->url);
        $this->assertStringContainsString('sso/entry', $entry->url);
    }

    public function test_login_dicatat_untuk_tiap_role_yang_membuka_helpdesk(): void
    {
        foreach (['Requester', 'Approver', 'Support BPO', 'Support IT'] as $roleName) {
            $user = User::factory()->create([
                'email' => strtolower(str_replace(' ', '.', $roleName)).'@adhi.co.id',
                'username' => strtolower(str_replace(' ', '.', $roleName)).'@adhi.co.id',
                'nip' => (string) random_int(19900000000, 19999999999),
                'status' => 'active',
                'helpdesk_access' => 'enabled',
            ]);
            $role = Role::firstOrCreate(['name' => $roleName]);
            $user->roles()->attach($role);

            $this->get(route('sso.entry', $this->sign(['email' => $user->email])))
                ->assertRedirect(route('portal.index'));

            $this->assertDatabaseHas('audit_trails', [
                'module' => 'auth',
                'action' => 'login',
                'actor_id' => $user->id,
            ]);
        }
    }
}
