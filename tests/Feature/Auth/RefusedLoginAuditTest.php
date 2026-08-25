<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\AuditTrail;
use App\Models\Role;
use App\Models\User;
use App\Support\RoleRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Login yang DITOLAK juga harus meninggalkan jejak.
 *
 * RecordLoginAudit menumpang event Login bawaan Laravel, dan event itu hanya
 * menyala kalau login berhasil. Akibatnya percobaan masuk memakai akun yang
 * sudah dinonaktifkan tidak tercatat di mana pun — bukan di audit_trails,
 * bukan pula di log aplikasi. Justru percobaan itulah yang paling ingin
 * dilihat Administrator saat memeriksa keamanan: siapa mencoba masuk memakai
 * akun pegawai yang sudah resign, kapan, dan dari alamat IP mana.
 *
 * Diuji dari kedua pintu masuk, karena keduanya menolak lewat jalur yang
 * berbeda: DevLoginController memutuskan sendiri, sedangkan pintu SSO memutus
 * di SsoAuthenticator::resolve().
 */
final class RefusedLoginAuditTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'rahasia-uji-tolak-login';

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

    private function user(array $attrs): User
    {
        $user = User::factory()->create(array_merge([
            'status' => 'active',
            'helpdesk_access' => 'enabled',
        ], $attrs));

        $user->roles()->attach(Role::firstOrCreate(
            ['name' => RoleRegistry::roleNameFor('requester')],
            ['type' => 'system', 'status' => 'active'],
        )->id);

        return $user;
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

    private function refusedEntries()
    {
        return AuditTrail::where('module', 'auth')->where('action', 'login_failed')->get();
    }

    public function test_akses_helpdesk_dimatikan_admin_tercatat_saat_ditolak_di_pintu_email(): void
    {
        $user = $this->user([
            'email' => 'dimatikan@adhi.co.id',
            'helpdesk_access' => 'disabled',
        ]);

        $this->post(route('login.attempt'), ['email' => 'dimatikan@adhi.co.id'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();

        $entries = $this->refusedEntries();

        $this->assertCount(1, $entries);
        $this->assertSame($user->id, $entries[0]->actor_id);
        $this->assertSame($user->id, $entries[0]->target_id);
        $this->assertSame($user->name, $entries[0]->target_name);
        $this->assertNotNull($entries[0]->ip_address);
        $this->assertStringContainsString('login', (string) $entries[0]->url);
    }

    public function test_akun_nonaktif_tercatat_saat_ditolak_di_pintu_sso(): void
    {
        $user = $this->user([
            'email' => 'resign@adhi.co.id',
            'username' => 'resign@adhi.co.id',
            'status' => 'inactive',
        ]);

        $this->get(route('sso.entry', $this->sign(['email' => $user->email])))
            ->assertRedirect(route('sso.login'));

        $this->assertGuest();

        $entries = $this->refusedEntries();

        $this->assertCount(1, $entries);
        $this->assertSame($user->id, $entries[0]->actor_id);
        $this->assertStringContainsString('sso/entry', (string) $entries[0]->url);
    }

    /**
     * Alasan penolakan ikut tersimpan, bukan hanya fakta "ditolak". Tanpa itu
     * Administrator masih harus menebak apakah orangnya resign, aksesnya
     * dicabut, atau dua-duanya.
     */
    public function test_alasan_penolakan_ikut_dicatat(): void
    {
        $user = $this->user([
            'email' => 'duaalasan@adhi.co.id',
            'status' => 'inactive',
            'helpdesk_access' => 'disabled',
        ]);

        $this->post(route('login.attempt'), ['email' => 'duaalasan@adhi.co.id']);

        $entry = $this->refusedEntries()->first();

        $this->assertNotNull($entry);
        $this->assertSame($user->inactiveReason(), $entry->new_value['reason'] ?? null);
        $this->assertStringContainsString($user->name, (string) $entry->description);
    }

    public function test_login_yang_berhasil_tidak_menulis_baris_penolakan(): void
    {
        $this->user(['email' => 'sehat@adhi.co.id']);

        $this->post(route('login.attempt'), ['email' => 'sehat@adhi.co.id'])
            ->assertRedirect(route('dashboard.requester'));

        $this->assertCount(0, $this->refusedEntries());
        $this->assertDatabaseHas('audit_trails', ['module' => 'auth', 'action' => 'login']);
    }

    /**
     * Email yang tidak punya akun sama sekali sengaja TIDAK menulis baris
     * audit: kolom actor_id wajib menunjuk ke satu user, dan tidak ada orang
     * yang bisa ditunjuk. Percobaan semacam itu sudah tercatat sebagai
     * peringatan di log aplikasi lewat SsoAuthenticator.
     */
    public function test_email_tanpa_akun_tidak_menulis_baris_audit(): void
    {
        $this->post(route('login.attempt'), ['email' => 'bukan.siapa-siapa@adhi.co.id'])
            ->assertSessionHasErrors('email');

        $this->assertCount(0, $this->refusedEntries());
    }
}
