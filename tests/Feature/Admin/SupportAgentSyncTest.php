<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\SlaPolicy;
use App\Models\SupportAgent;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\ActsAsRole;
use Tests\TestCase;

/**
 * Memberi role Support lewat layar Admin harus benar-benar membuat orangnya
 * bisa bekerja.
 *
 * `roles` menentukan siapa boleh MEMBUKA layar Support; `support_agents` adalah
 * identitas KERJANYA, yang dirujuk tickets.assigned_agent_id. Layar Support
 * mencari barisnya dengan firstOrFail(). Sebelum ini, satu-satunya pembuat
 * baris support_agents adalah seeder — jadi Administrator bisa memberi role
 * "Support IT" (tombolnya ada, audit trailnya tercatat, semuanya tampak
 * berhasil) dan orang itu tetap menemukan layarnya 404.
 */
final class SupportAgentSyncTest extends TestCase
{
    use ActsAsRole, RefreshDatabase;

    public function test_memberi_role_support_membuat_baris_agent(): void
    {
        $this->actingAsRole('admin');
        $user = User::factory()->create(['name' => 'Marcell Laforteza']);

        $this->putRoles($user, ['Support IT', 'Support BPO'])->assertOk();

        $agents = SupportAgent::where('user_id', $user->id)->get();
        $this->assertEqualsCanonicalizing(['it', 'bpo'], $agents->pluck('type')->all());
        $this->assertTrue($agents->every(fn (SupportAgent $a) => $a->is_active));
        $this->assertSame(['Marcell Laforteza', 'Marcell Laforteza'], $agents->pluck('name')->all());
    }

    public function test_mencabut_role_menonaktifkan_agent_tanpa_menghapusnya(): void
    {
        $this->actingAsRole('admin');
        $user = User::factory()->create();
        $this->putRoles($user, ['Support IT']);

        $agent = SupportAgent::where('user_id', $user->id)->firstOrFail();

        // Tiket yang pernah ditanganinya menunjuk ke baris ini. Menghapus
        // agentnya berarti memutus riwayat tiket itu.
        Ticket::create([
            'ticket_no' => 'INC-UJI-2026-0001',
            'title' => 'Tiket lama',
            'requester_name' => 'Andi',
            'status' => 'Closed',
            'priority' => 'Medium',
            'assigned_agent_id' => $agent->id,
            'sla_policy_id' => SlaPolicy::create([
                'policy_name' => 'Uji Sinkron Agent',
                'priority' => 'Medium',
                'service_type' => 'Incident',
                'response_time_minutes' => 480,
                'resolution_time_minutes' => 2880,
                'warning_threshold_percent' => 80,
                'status' => 'active',
            ])->id,
            'response_time_minutes' => 480,
            'resolution_time_minutes' => 2880,
            'warning_threshold_percent' => 80,
            'response_due_at' => now()->addHours(8),
            'resolution_due_at' => now()->addDays(2),
            'warning_at' => now()->addDay(),
        ]);

        $this->putRoles($user, ['Requester'])->assertOk();

        $this->assertDatabaseHas('support_agents', ['id' => $agent->id, 'is_active' => false]);
        $this->assertSame($agent->id, Ticket::firstOrFail()->assigned_agent_id);
    }

    public function test_role_yang_dikembalikan_mengaktifkan_agent_yang_sama(): void
    {
        $this->actingAsRole('admin');
        $user = User::factory()->create();

        $this->putRoles($user, ['Support IT']);
        $awal = SupportAgent::where('user_id', $user->id)->firstOrFail()->id;

        $this->putRoles($user, ['Requester']);
        $this->putRoles($user, ['Support IT'])->assertOk();

        // Baris baru berarti agent kembar: satu aktif tanpa riwayat, satu mati
        // memegang seluruh tiket lamanya.
        $this->assertSame(1, SupportAgent::where('user_id', $user->id)->count());
        $this->assertDatabaseHas('support_agents', ['id' => $awal, 'is_active' => true]);
    }

    public function test_role_selain_support_tidak_membuat_agent(): void
    {
        $this->actingAsRole('admin');
        $user = User::factory()->create();

        $this->putRoles($user, ['Requester', 'Approver', 'Team Lead'])->assertOk();

        $this->assertSame(0, SupportAgent::where('user_id', $user->id)->count());
    }

    /**
     * Perintah penyapu untuk DATA LAMA: user yang sudah memegang role Support
     * sejak sebelum sinkronisasi ini ada tidak akan pernah tersentuh oleh
     * pemberian role baru, dan dashboardnya tetap 404 selamanya.
     */
    public function test_perintah_penyapu_memperbaiki_user_lama(): void
    {
        $this->actingAsRole('admin');

        // Ditulis langsung ke tabel pivot, meniru keadaan sebelum sinkronisasi
        // dipasang: rolenya ada, baris agentnya tidak.
        $user = User::factory()->create(['name' => 'Agent Warisan']);
        $user->roles()->attach(Role::firstOrCreate(['name' => 'Support IT'], ['type' => 'system', 'status' => 'active'])->id);

        $this->assertSame(0, SupportAgent::where('user_id', $user->id)->count());

        $this->artisan('support:sync-agents')->assertSuccessful();
        $this->assertSame(0, SupportAgent::where('user_id', $user->id)->count(), 'Tanpa --apply tidak boleh ada yang berubah.');

        $this->artisan('support:sync-agents', ['--apply' => true])->assertSuccessful();

        $this->assertDatabaseHas('support_agents', [
            'user_id' => $user->id,
            'type' => 'it',
            'is_active' => true,
            'name' => 'Agent Warisan',
        ]);
    }

    public function test_perintah_penyapu_diam_saat_semuanya_sudah_sesuai(): void
    {
        $this->actingAsRole('admin');
        $user = User::factory()->create();
        $this->putRoles($user, ['Support IT']);

        $this->artisan('support:sync-agents')
            ->expectsOutputToContain('sudah sesuai')
            ->assertSuccessful();
    }

    /** @param string[] $names */
    private function putRoles(User $user, array $names): TestResponse
    {
        $ids = collect($names)
            ->map(fn (string $name) => Role::firstOrCreate(['name' => $name], ['type' => 'system', 'status' => 'active'])->id)
            ->all();

        return $this->patchJson(route('admin.users.roles', $user), ['role_ids' => $ids]);
    }
}
