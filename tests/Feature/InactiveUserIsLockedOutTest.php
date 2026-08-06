<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\SlaPolicy;
use App\Models\User;
use App\Support\CurrentActor;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Tests\TestCase;

/**
 * User nonaktif tidak bisa membuka helpdesk sama sekali.
 *
 * Sebelum ini, penguncian hanya berlaku pada sesi SSO — SsoAuthenticator::user()
 * memutus sesinya tiap permintaan. Tapi selama SSO masih "mock", aplikasi jatuh
 * ke tujuh persona tetap yang dicari lewat NIP, dan jalur itu TIDAK memeriksa
 * apa pun: mematikan akses seorang persona lewat konsol Admin tidak berpengaruh,
 * layarnya tetap terbuka.
 *
 * Konsekuensi yang dijaga di sini disengaja dan perlu diketahui: mematikan
 * persona berarti mematikan SELURUH layar role itu, bukan hanya bagi orang
 * tersebut. Di mode persona, persona ITULAH satu-satunya identitas yang ada —
 * tidak ada "orang lain" yang bisa membuka layar yang sama.
 */
final class InactiveUserIsLockedOutTest extends TestCase
{
    use RefreshDatabase;

    /** NIP persona Requester — lihat CurrentActor. */
    private const REQUESTER_NIP = '19950418102';

    public function test_persona_yang_dimatikan_admin_tidak_bisa_membuka_layarnya(): void
    {
        $this->persona(helpdeskAccess: 'disabled');

        $this->get(route('dashboard.requester'))
            ->assertForbidden()
            ->assertSee('akses helpdesk dinonaktifkan Admin', escape: false);
    }

    public function test_persona_yang_nonaktif_kepegawaian_juga_terkunci(): void
    {
        $this->persona(status: 'inactive');

        $this->get(route('dashboard.requester'))
            ->assertForbidden()
            ->assertSee('nonaktif di data kepegawaian', escape: false);
    }

    /**
     * Aksi lewat API ikut terkunci, bukan cuma halamannya.
     *
     * Diuji pada pembuatan tiket — aksi yang benar-benar ATAS NAMA user. Data
     * referensi seperti /api/catalog sengaja tidak ikut dijaga: isinya daftar
     * layanan yang sama untuk semua orang, tidak ada apa pun yang pribadi, dan
     * setiap layar yang memakainya sudah terkunci lebih dulu.
     */
    public function test_aksi_lewat_api_ikut_menolak(): void
    {
        $this->persona(helpdeskAccess: 'disabled');

        // Permintaannya dibuat SAH lebih dulu. Validasi berjalan sebelum aktor
        // ditentukan, jadi muatan asal-asalan berhenti di 422 dan gerbangnya
        // tidak pernah benar-benar teruji.
        $policy = SlaPolicy::create([
            'policy_name' => 'Uji',
            'priority' => 'Low',
            'service_type' => 'Incident',
            'response_time_minutes' => 60,
            'resolution_time_minutes' => 480,
            'warning_threshold_percent' => 80,
            'status' => 'active',
        ]);

        $this->postJson(route('tickets.store'), [
            'title' => 'Percobaan',
            'sla_policy_id' => $policy->id,
        ])->assertForbidden();
    }

    /**
     * Halaman tolakannya tidak menawarkan jalan yang buntu.
     *
     * "Pilih Role" mengembalikan orang ke portal pemilih role — lalu role mana
     * pun yang mereka klik ditolak lagi oleh gerbang yang sama. Menawarkannya
     * pada akun nonaktif bukan cuma tidak berguna, ia membuat orang mengira
     * masalahnya ada di role yang salah pilih, bukan di akunnya.
     */
    public function test_halaman_tolakan_tidak_menawarkan_pilih_role(): void
    {
        $this->persona(helpdeskAccess: 'disabled');

        $this->get(route('dashboard.requester'))
            ->assertForbidden()
            ->assertDontSee('Pilih Role');
    }

    /**
     * Penolakan BIASA (role tidak cocok) tetap menawarkannya — di situ berpindah
     * role memang jalan keluarnya.
     *
     * Diuji langsung pada view, bukan lewat rute: rute mana yang kebetulan
     * membalas 403 bisa berubah kapan saja, sedangkan yang ingin dijaga di sini
     * adalah percabangan di dalam halamannya.
     */
    public function test_penolakan_biasa_tetap_menawarkan_pilih_role(): void
    {
        $html = view('errors.403', [
            'exception' => new AccessDeniedHttpException('Bukan tanggung jawab role ini.'),
        ])->render();

        $this->assertStringContainsString('Pilih Role', $html);
    }

    public function test_persona_aktif_tetap_bisa_masuk_seperti_biasa(): void
    {
        $this->persona();

        $this->get(route('dashboard.requester'))->assertOk();
    }

    /**
     * Alasannya disebut lengkap, bukan hanya yang pertama ditemukan — seorang
     * Admin yang mematikan akses orang yang sudah resign perlu tahu akunnya
     * tetap terkunci meski data kepegawaiannya nanti aktif lagi.
     */
    public function test_kedua_alasan_disebut_saat_dua_duanya_mati(): void
    {
        $this->persona(status: 'inactive', helpdeskAccess: 'disabled');

        try {
            CurrentActor::requester();
            $this->fail('Seharusnya menolak.');
        } catch (AuthorizationException $e) {
            $this->assertStringContainsString('kepegawaian', $e->getMessage());
            $this->assertStringContainsString('Admin', $e->getMessage());
        }
    }

    private function persona(string $status = 'active', string $helpdeskAccess = 'enabled'): User
    {
        return User::factory()->create([
            'name' => 'Andi Pratama',
            'nip' => self::REQUESTER_NIP,
            'status' => $status,
            'helpdesk_access' => $helpdeskAccess,
        ]);
    }
}
