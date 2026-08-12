<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AuditTrail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\ActsAsRole;
use Tests\TestCase;

/**
 * Filter Audit Trail.
 *
 * Dulu layar ini memuat 500 baris terbaru lalu menyaringnya di browser. Dua
 * akibatnya, dan keduanya diam — tidak ada error, hanya hasil kosong:
 *
 *   1. Log yang lebih tua dari 500 baris itu tidak pernah bisa ditemukan lewat
 *      filter apa pun.
 *   2. Daftar pilihan "Pengguna" hanya berisi orang yang kebetulan muncul di
 *      jendela itu, sehingga orang yang aktivitasnya sudah lewat menghilang
 *      dari pilihan — seolah-olah ia tidak pernah berbuat apa-apa.
 *
 * Angka 500 di berkas ini disebut eksplisit karena ITU-lah ambang yang dulu
 * memutus: kalau seseorang mengembalikan penyaringan ke browser, tes ini gagal.
 */
final class AuditTrailFilterTest extends TestCase
{
    use ActsAsRole, RefreshDatabase;

    private const AMBANG_LAMA = 500;

    /**
     * `created_at` TIDAK ada di $fillable milik AuditTrail, jadi menitipkannya
     * lewat create() diabaikan diam-diam dan Eloquent mengisinya dengan now().
     * Tanpa penulisan ulang di bawah, setiap baris di tes ini bertanggal hari
     * ini dan seluruh pengujian rentang tanggal jadi tidak berarti — lulus
     * tanpa pernah menguji apa pun.
     */
    private function log(User $aktor, string $module, string $action, ?Carbon $waktu = null): AuditTrail
    {
        $log = AuditTrail::create([
            'actor_id' => $aktor->id,
            'module' => $module,
            'action' => $action,
            'target_type' => 'user',
            'target_name' => 'Sasaran Uji',
            'description' => "{$aktor->name} melakukan {$action}.",
        ]);

        if ($waktu !== null) {
            AuditTrail::whereKey($log->id)->update(['created_at' => $waktu]);
            $log->refresh();
        }

        return $log;
    }

    private function pegawai(string $nama): User
    {
        return User::factory()->create([
            'name' => $nama,
            'status' => 'active',
            'helpdesk_access' => 'enabled',
        ]);
    }

    /** @return array{administrators: list<string>, meta: array<string,mixed>} */
    private function propsHalaman(): array
    {
        $html = $this->get(route('admin.audit-trail'))->assertOk()->getContent();

        preg_match('/data-react="AuditTrailConsole"\s*data-props="([^"]*)"/', $html, $m);
        $props = json_decode(html_entity_decode($m[1] ?? '{}'), true) ?: [];

        return [
            'administrators' => $props['administrators'] ?? [],
            'meta' => $props['logsMeta'] ?? [],
        ];
    }

    public function test_pilihan_pengguna_memuat_aktor_yang_lognya_di_luar_500_terbaru(): void
    {
        $admin = $this->actingAsRole('admin');

        // Satu orang yang aktivitasnya SUDAH LAMA…
        $lama = $this->pegawai('Pegawai Lama');
        $this->log($lama, 'user_role_management', 'create', Carbon::now()->subYear());

        // …lalu tertimbun log yang jauh lebih baru, melewati ambang 500.
        for ($i = 0; $i < self::AMBANG_LAMA + 10; $i++) {
            $this->log($admin, 'integration', 'sync', Carbon::now()->subMinutes($i));
        }

        $this->assertContains('Pegawai Lama', $this->propsHalaman()['administrators']);
    }

    public function test_menyaring_aktor_lama_benar_benar_menemukan_lognya(): void
    {
        $admin = $this->actingAsRole('admin');
        $lama = $this->pegawai('Pegawai Lama');
        $this->log($lama, 'user_role_management', 'create', Carbon::now()->subYear());

        for ($i = 0; $i < self::AMBANG_LAMA + 10; $i++) {
            $this->log($admin, 'integration', 'sync', Carbon::now()->subMinutes($i));
        }

        $hasil = $this->getJson(route('admin.audit-trail.list', ['administrator' => 'Pegawai Lama']))
            ->assertOk()
            ->json();

        $this->assertSame(1, $hasil['meta']['total']);
        $this->assertSame('Pegawai Lama', $hasil['logs'][0]['administrator']);
    }

    /**
     * Invarian yang sama dengan filter Unit Kerja: setiap pilihan yang bisa
     * diklik harus mengembalikan baris.
     */
    public function test_setiap_pilihan_pengguna_mengembalikan_baris(): void
    {
        $admin = $this->actingAsRole('admin');
        $this->log($admin, 'auth', 'login');
        $this->log($this->pegawai('Budi'), 'ticket_support', 'resolve');
        $this->log($this->pegawai('Sari'), 'team_lead', 'remind');

        $pilihan = $this->propsHalaman()['administrators'];
        $this->assertNotEmpty($pilihan);

        foreach ($pilihan as $nama) {
            $total = $this->getJson(route('admin.audit-trail.list', ['administrator' => $nama]))
                ->assertOk()->json('meta.total');

            $this->assertGreaterThan(0, $total, "\"{$nama}\" ditawarkan tapi tidak cocok dengan satu baris pun.");
        }
    }

    public function test_pengguna_tanpa_jejak_audit_tidak_ditawarkan(): void
    {
        $admin = $this->actingAsRole('admin');
        $this->log($admin, 'auth', 'login');
        $this->pegawai('Belum Pernah Berbuat Apa-apa');

        $this->assertNotContains('Belum Pernah Berbuat Apa-apa', $this->propsHalaman()['administrators']);
    }

    public function test_filter_modul_aksi_dan_tanggal_disaring_di_server(): void
    {
        $admin = $this->actingAsRole('admin');
        $this->log($admin, 'user_role_management', 'activate', Carbon::parse('2026-01-10'));
        $this->log($admin, 'user_role_management', 'deactivate', Carbon::parse('2026-01-20'));
        $this->log($admin, 'integration', 'sync', Carbon::parse('2026-02-01'));

        $total = fn (array $q) => $this->getJson(route('admin.audit-trail.list', $q))->assertOk()->json('meta.total');

        $this->assertSame(2, $total(['module' => 'user_role_management']));
        $this->assertSame(1, $total(['action' => 'sync']));
        $this->assertSame(1, $total(['module' => 'user_role_management', 'action' => 'activate']));
        // Batas tanggal mencakup keseluruhan hari yang dipilih, bukan pukul 00:00.
        $this->assertSame(2, $total(['from' => '2026-01-20']));
        $this->assertSame(2, $total(['from' => '2026-01-01', 'to' => '2026-01-20']));
    }

    public function test_pencarian_menjangkau_nama_pelaku_bukan_hanya_kolom_di_baris_audit(): void
    {
        $admin = $this->actingAsRole('admin');
        $this->log($this->pegawai('Zulkarnain'), 'auth', 'login');
        $this->log($admin, 'integration', 'sync');

        // Nama pelaku hidup di tabel users, bukan di baris audit — kalau
        // pencariannya hanya melihat kolom milik audit_trails, ini nol.
        $this->assertSame(
            1,
            $this->getJson(route('admin.audit-trail.list', ['search' => 'Zulkarnain']))->assertOk()->json('meta.total'),
        );
    }

    public function test_hasilnya_dipaginasi_bukan_dikirim_sekaligus(): void
    {
        $admin = $this->actingAsRole('admin');
        for ($i = 0; $i < 40; $i++) {
            $this->log($admin, 'auth', 'login', Carbon::now()->subMinutes($i));
        }

        $meta = $this->propsHalaman()['meta'];

        $this->assertSame(40, $meta['total']);
        $this->assertSame(15, $meta['per_page']);
        $this->assertSame(3, $meta['last_page']);
    }
}
