<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\SupportAgent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsRole;
use Tests\TestCase;

/**
 * Hak akses per role — alasan seluruh login ini dipasang.
 *
 * Sebelumnya pertanyaan "boleh tidak user X membuka layar Y" tidak punya
 * jawaban yang bisa diuji: tanpa login, CurrentActor jatuh ke persona tetap dan
 * URL role mana pun selalu tembus. Berkas ini yang memastikan keadaan itu tidak
 * diam-diam kembali.
 */
final class RoleAccessControlTest extends TestCase
{
    use ActsAsRole, RefreshDatabase;

    /** Layar utama tiap role, dipakai dua tes di bawah. */
    private const LAYAR = [
        'requester' => 'dashboard.requester',
        'approver' => 'dashboard.approver',
        'support' => 'dashboard.support',
        'support-bpo' => 'dashboard.support-bpo',
        'team-lead' => 'dashboard.team-lead',
        'team-lead-bpo' => 'dashboard.team-lead-bpo',
        'admin' => 'admin.dashboard',
        'eva' => 'eva.coverage',
    ];

    public static function layarProvider(): array
    {
        return array_map(fn ($k, $r) => [$k, $r], array_keys(self::LAYAR), self::LAYAR);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('layarProvider')]
    public function test_tamu_diantar_ke_halaman_masuk(string $key, string $route): void
    {
        $this->get(route($route))->assertRedirect(route('login'));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('layarProvider')]
    public function test_pemegang_role_bisa_membuka_layarnya_sendiri(string $key, string $route): void
    {
        $user = $this->actingAsRole($key);

        // Dua role support menuntut lebih dari sekadar role: layarnya bekerja
        // atas baris `support_agents` milik orang itu, dan tanpa baris tersebut
        // controller-nya berhenti di firstOrFail() — 404, bukan penolakan
        // akses. (Konsekuensi nyata: memberi seseorang role Support IT lewat
        // User & Role Management belum cukup; agent-nya harus ikut dibuat.)
        if (in_array($key, ['support', 'support-bpo'], true)) {
            SupportAgent::create([
                'name' => $user->name,
                'type' => $key === 'support-bpo' ? 'bpo' : 'it',
                'is_active' => true,
                'user_id' => $user->id,
            ]);
        }

        $this->get(route($route))->assertOk();
    }

    /**
     * Inti pengujian: seorang Requester polos ditolak di SETIAP layar yang
     * bukan miliknya — termasuk konsol admin, yang dulu terbuka begitu saja
     * bagi siapa pun yang mengetik /admin.
     */
    public function test_requester_ditolak_di_semua_layar_role_lain(): void
    {
        $this->actingAsRole('requester');

        foreach (self::LAYAR as $key => $route) {
            if ($key === 'requester') {
                continue;
            }

            $this->get(route($route))
                ->assertForbidden();
        }
    }

    public function test_pemegang_banyak_role_bisa_membuka_semuanya(): void
    {
        // Pola paling umum di data nyata: peran fungsional selalu berdampingan
        // dengan Requester.
        $this->actingAsRole('requester', 'approver', 'team-lead');

        $this->get(route('dashboard.requester'))->assertOk();
        $this->get(route('dashboard.approver'))->assertOk();
        $this->get(route('dashboard.team-lead'))->assertOk();

        // Yang TIDAK dipegang tetap tertutup.
        $this->get(route('admin.dashboard'))->assertForbidden();
    }

    /**
     * Endpoint tulis ikut dijaga, bukan cuma halamannya — gerbang yang hanya
     * memasang diri di layar tidak menahan apa pun yang memanggil API langsung.
     */
    public function test_endpoint_tulis_admin_ditolak_untuk_non_admin(): void
    {
        $this->actingAsRole('requester');

        $this->postJson(route('admin.roles.store'), ['name' => 'Role Sisipan'])
            ->assertForbidden();

        $this->assertDatabaseMissing('roles', ['name' => 'Role Sisipan']);
    }

    public function test_pembuatan_tiket_menuntut_role_requester(): void
    {
        // Seorang Approver yang TIDAK memegang Requester tidak bisa membuat
        // tiket atas namanya sendiri.
        $this->actingAsRole('approver');

        $this->postJson(route('tickets.store'), ['title' => 'Percobaan'])
            ->assertForbidden();
    }
}
