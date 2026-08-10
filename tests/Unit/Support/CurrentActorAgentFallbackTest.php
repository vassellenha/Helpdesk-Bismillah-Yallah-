<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Models\SupportAgent;
use App\Models\User;
use App\Support\CurrentActor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * support() dan supportBpo() dulu berakhir di NIP hasil seed yang di-firstOrFail().
 * Denny Firmansyah (nip 19960130096) dihapus 10 Agu 2026 setelah switcher agent
 * membuatnya tidak diperlukan, dan sejak itu fallback-nya menunjuk baris yang
 * tidak ada.
 *
 * Yang bikin ini luput dari perhatian: sesi yang SUDAH memilih agent tidak
 * pernah menyentuh fallback-nya. Hanya sesi baru — browser lain, incognito,
 * cookie dibersihkan — yang jatuh ke sana, tepat pada permintaan pertama,
 * sebelum switcher-nya sempat muncul untuk dipakai. Karena itu tes di bawah
 * sengaja tidak menyetel session apa pun kecuali saat memang mengujinya.
 */
class CurrentActorAgentFallbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_sesi_baru_tanpa_pilihan_dapat_agent_bpo_aktif_pertama(): void
    {
        $this->agent('bpo', 'Genta Pratama');
        $this->agent('bpo', 'Maya Prameswari');

        $this->assertSame('Genta Pratama', CurrentActor::supportBpo()->name);
    }

    public function test_sesi_baru_tanpa_pilihan_dapat_agent_it_aktif_pertama(): void
    {
        $this->agent('it', 'Aditya Dwi Nugraha');

        $this->assertSame('Aditya Dwi Nugraha', CurrentActor::support()->name);
    }

    /**
     * Fallback hanya boleh jadi jaring pengaman. Kalau ia sampai mengalahkan
     * pilihan yang ada di sesi, switcher-nya berhenti berfungsi tanpa gejala:
     * layarnya tetap terbuka, hanya saja menampilkan tiket milik orang lain.
     */
    public function test_pilihan_di_sesi_menang_atas_fallback(): void
    {
        $this->agent('bpo', 'Genta Pratama');
        $dipilih = $this->agent('bpo', 'Rio Saputra');

        session(['acting_support_bpo_agent_id' => $dipilih->id]);

        $this->assertSame('Rio Saputra', CurrentActor::supportBpo()->name);
    }

    public function test_agent_nonaktif_dilewati(): void
    {
        $this->agent('bpo', 'Sudah Resign', agentActive: false);
        $this->agent('bpo', 'Masih Aktif');

        $this->assertSame('Masih Aktif', CurrentActor::supportBpo()->name);
    }

    /**
     * Agent-nya aktif, tapi ORANGNYA dinonaktifkan — dua kolom berbeda dengan
     * dua pemilik berbeda (support_agents.is_active vs users.status). Kalau
     * fallback hanya memeriksa yang pertama, mustBeActive() akan melempar
     * AccountInactive dan layar Support mati total, padahal ada rekan lain yang
     * seharusnya bisa dipakai.
     */
    public function test_agent_aktif_tapi_akun_penggunanya_nonaktif_ikut_dilewati(): void
    {
        $this->agent('bpo', 'Akun Dimatikan', userActive: false);
        $this->agent('bpo', 'Masih Aktif');

        $this->assertSame('Masih Aktif', CurrentActor::supportBpo()->name);
    }

    /**
     * Tidak ada agent sama sekali harus berbunyi jelas. Sebelumnya kondisi ini
     * berakhir sebagai ModelNotFoundException, yang Laravel ubah jadi 404 polos
     * — tidak memberi tahu siapa pun bahwa yang salah adalah datanya, bukan URL.
     */
    public function test_tanpa_agent_aktif_melempar_pesan_yang_bisa_ditindaklanjuti(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Tidak ada agent bpo aktif/');

        CurrentActor::supportBpo();
    }

    private function agent(string $type, string $name, bool $agentActive = true, bool $userActive = true): SupportAgent
    {
        $user = User::factory()->create([
            'name' => $name,
            'status' => $userActive ? 'active' : 'inactive',
            'helpdesk_access' => $userActive ? 'enabled' : 'disabled',
        ]);

        return SupportAgent::create([
            'name' => $name,
            'type' => $type,
            'is_active' => $agentActive,
            'user_id' => $user->id,
        ]);
    }
}
