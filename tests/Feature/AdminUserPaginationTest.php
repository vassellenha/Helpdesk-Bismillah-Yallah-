<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Layar User Management dulu memuat SELURUH tabel `users` tiap kali dibuka.
 * Dengan 28 akun seed itu tidak terasa; setelah sinkronisasi direktori
 * perusahaan angkanya jadi 3.847, dan seluruhnya ditarik ke memori lalu dikirim
 * ke React sebagai satu blok JSON megabyte-an.
 *
 * Tes ini menjaga dua hal yang mudah rusak diam-diam saat filter dipindah dari
 * klien ke server: hasil penyaringannya harus tetap sama persis dengan aturan
 * lama, dan angka statistik harus dihitung dari seluruh tabel — bukan dari 25
 * baris yang kebetulan sedang ditampilkan.
 */
class AdminUserPaginationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // CurrentActor::admin() mencari persona ini; tanpanya layar Admin 404.
        $this->persona('19870114001', 'Administrator');
    }

    public function test_halaman_pertama_dibatasi_25_baris_walau_datanya_ratusan(): void
    {
        User::factory()->count(120)->create(['status' => 'active', 'helpdesk_access' => 'enabled']);

        $response = $this->get(route('admin.users'))->assertOk();

        $this->assertCount(25, $response->viewData('users'));
        $this->assertSame(121, $response->viewData('usersMeta')['total']);
        $this->assertSame(5, $response->viewData('usersMeta')['last_page']);
    }

    /**
     * Angka kartu statistik dihitung COUNT di database. Kalau dihitung dari
     * daftar yang dikirim, perusahaan berisi 3.847 orang akan dilaporkan sebagai
     * "25 user" — salah, dan salahnya tidak kelihatan seperti bug.
     */
    public function test_statistik_dihitung_dari_seluruh_tabel_bukan_satu_halaman(): void
    {
        User::factory()->count(60)->create(['status' => 'active', 'helpdesk_access' => 'enabled']);
        User::factory()->count(10)->create(['status' => 'inactive', 'helpdesk_access' => 'enabled']);

        $stats = $this->get(route('admin.users'))->assertOk()->viewData('userStats');

        $this->assertSame(71, $stats['total']);
        $this->assertSame(61, $stats['active']);
        $this->assertSame(10, $stats['inactive']);
    }

    public function test_pencarian_mencakup_nip_yang_dulu_tidak_bisa_dicari(): void
    {
        User::factory()->create(['name' => 'Orang Lain', 'nip' => '11112222', 'status' => 'active', 'helpdesk_access' => 'enabled']);
        User::factory()->create(['name' => 'Dicari', 'nip' => 'B/22/07/2410/79', 'status' => 'active', 'helpdesk_access' => 'enabled']);

        $hasil = $this->getJson(route('admin.users.list', ['search' => 'B/22/07']))->assertOk()->json('users');

        $this->assertCount(1, $hasil);
        $this->assertSame('Dicari', $hasil[0]['name']);
    }

    /**
     * "Aktif" di layar ini adalah putusan gabungan DUA kolom milik dua pemilik
     * berbeda (User::isActive()): `status` dari API kepegawaian, dan
     * `helpdesk_access` yang hanya Admin yang boleh ubah. Filter server harus
     * menirukan aturan itu — menyaring `status` saja akan menampilkan orang yang
     * aksesnya sudah dicabut Admin sebagai "Aktif".
     */
    public function test_filter_status_menirukan_aturan_dua_kolom(): void
    {
        User::factory()->create(['name' => 'Aktif Penuh', 'status' => 'active', 'helpdesk_access' => 'enabled']);
        User::factory()->create(['name' => 'Akses Dicabut Admin', 'status' => 'active', 'helpdesk_access' => 'disabled']);
        User::factory()->create(['name' => 'Sudah Resign', 'status' => 'inactive', 'helpdesk_access' => 'enabled']);

        $aktif = collect($this->getJson(route('admin.users.list', ['status' => 'Aktif']))->assertOk()->json('users'))->pluck('name');
        $nonaktif = collect($this->getJson(route('admin.users.list', ['status' => 'Nonaktif']))->assertOk()->json('users'))->pluck('name');

        $this->assertTrue($aktif->contains('Aktif Penuh'));
        $this->assertFalse($aktif->contains('Akses Dicabut Admin'), 'Akses yang dicabut Admin tidak boleh terhitung aktif.');
        $this->assertFalse($aktif->contains('Sudah Resign'));

        $this->assertTrue($nonaktif->contains('Akses Dicabut Admin'));
        $this->assertTrue($nonaktif->contains('Sudah Resign'));
        $this->assertFalse($nonaktif->contains('Aktif Penuh'));
    }

    public function test_filter_role_dan_unit(): void
    {
        $ditandai = User::factory()->create(['name' => 'Punya Role', 'unit' => 'Dept. Keuangan', 'status' => 'active', 'helpdesk_access' => 'enabled']);
        $ditandai->roles()->attach(Role::firstOrCreate(['name' => 'Approver'])->id);
        User::factory()->create(['name' => 'Tanpa Role', 'unit' => 'Dept. Lain', 'status' => 'active', 'helpdesk_access' => 'enabled']);

        $perRole = $this->getJson(route('admin.users.list', ['role' => 'Approver']))->assertOk()->json('users');
        $perUnit = $this->getJson(route('admin.users.list', ['unit' => 'Dept. Keuangan']))->assertOk()->json('users');

        $this->assertCount(1, $perRole);
        $this->assertSame('Punya Role', $perRole[0]['name']);
        $this->assertCount(1, $perUnit);
        $this->assertSame('Punya Role', $perUnit[0]['name']);
    }

    public function test_halaman_kedua_mengembalikan_baris_yang_berbeda(): void
    {
        User::factory()->count(40)->create(['status' => 'active', 'helpdesk_access' => 'enabled']);

        $satu = collect($this->getJson(route('admin.users.list'))->assertOk()->json('users'))->pluck('id');
        $dua = $this->getJson(route('admin.users.list', ['page' => 2]))->assertOk();

        $this->assertSame(2, $dua->json('meta.current_page'));
        $this->assertEmpty($satu->intersect(collect($dua->json('users'))->pluck('id')), 'Halaman 2 tidak boleh mengulang baris halaman 1.');
    }

    /** Filter ikut terbawa ke perhitungan halaman, bukan hanya ke daftarnya. */
    public function test_meta_total_mengikuti_filter_yang_aktif(): void
    {
        User::factory()->count(30)->create(['unit' => 'Dept. A', 'status' => 'active', 'helpdesk_access' => 'enabled']);
        User::factory()->count(5)->create(['unit' => 'Dept. B', 'status' => 'active', 'helpdesk_access' => 'enabled']);

        $meta = $this->getJson(route('admin.users.list', ['unit' => 'Dept. B']))->assertOk()->json('meta');

        $this->assertSame(5, $meta['total']);
        $this->assertSame(1, $meta['last_page']);
    }

    private function persona(string $nip, string $roleName): User
    {
        $user = User::factory()->create([
            'nip' => $nip,
            'status' => 'active',
            'helpdesk_access' => 'enabled',
        ]);

        $user->roles()->attach(Role::firstOrCreate(['name' => $roleName])->id);

        return $user;
    }
}
