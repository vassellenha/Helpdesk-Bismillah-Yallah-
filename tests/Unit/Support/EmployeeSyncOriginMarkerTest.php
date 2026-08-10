<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Models\User;
use App\Support\EmployeeSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

/**
 * `users.synced_at` membedakan pegawai dari direktori perusahaan dengan akun
 * lokal (seed, buatan Admin, sisa uji coba). Sesudah sinkronisasi pertama tabel
 * ini bercampur — 3.847 orang sungguhan di antara puluhan akun lokal — dan
 * bedanya menentukan siapa yang aman dihapus saat bersih-bersih.
 *
 * Sempat dipertimbangkan menebaknya dari bentuk NPP (asli berhuruf, seed murni
 * angka). Ditolak: dari 3.847 NPP sungguhan ada 168 pola dan satu di antaranya
 * murni angka, jadi tebakan itu akan salah menandai seorang pegawai sungguhan
 * sebagai sampah — tepat pada keputusan yang paling tidak boleh salah.
 */
class EmployeeSyncOriginMarkerTest extends TestCase
{
    use RefreshDatabase;

    private string $fixturePath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixturePath = tempnam(sys_get_temp_dir(), 'employees_origin_').'.json';
        Config::set('integrations.employee_directory.driver', 'mock');
        Config::set('integrations.employee_directory.mock.fixture', $this->fixturePath);
        Config::set('integrations.employee_directory.overwrite_with_empty', false);

        User::factory()->create(['nip' => '19870114001', 'status' => 'active', 'helpdesk_access' => 'enabled']);
    }

    protected function tearDown(): void
    {
        @unlink($this->fixturePath);
        parent::tearDown();
    }

    public function test_akun_baru_dari_direktori_ditandai(): void
    {
        $this->writeFixture([$this->row('B/22/07/2410/79', 'Pegawai Baru')]);

        EmployeeSync::run();

        $this->assertNotNull(User::where('nip', 'B/22/07/2410/79')->firstOrFail()->synced_at);
    }

    /**
     * Kasus yang paling mudah terlewat. Yang dicatat bukan "kapan barisnya
     * berubah" — itu tugas updated_at — melainkan "kapan orang ini terakhir
     * terlihat di direktori". Pegawai yang datanya stabil bertahun-tahun tidak
     * pernah menghasilkan perubahan apa pun, dan kalau penanda ini hanya ditulis
     * saat ada yang berubah, justru merekalah yang akan tampak seperti akun
     * lokal sisa uji coba lalu ikut terhapus saat bersih-bersih.
     */
    public function test_pegawai_yang_cocok_tanpa_perubahan_tetap_ditandai(): void
    {
        $this->writeFixture([$this->row('B/22/07/2410/80', 'Tidak Berubah')]);

        EmployeeSync::run();
        $pertama = User::where('nip', 'B/22/07/2410/80')->firstOrFail()->synced_at;

        // Jalankan lagi dengan payload identik — tidak ada satu field pun berubah.
        $this->travel(1)->hours();
        $ringkasan = EmployeeSync::run();

        $this->assertSame(1, $ringkasan['unchanged'], 'Payload identik seharusnya terhitung "tidak berubah".');

        $kedua = User::where('nip', 'B/22/07/2410/80')->firstOrFail()->synced_at;
        $this->assertNotNull($kedua);
        $this->assertTrue($kedua->greaterThan($pertama), 'synced_at harus maju walau tidak ada data yang berubah.');
    }

    public function test_akun_lokal_yang_tidak_ada_di_direktori_tidak_ditandai(): void
    {
        $lokal = User::factory()->create([
            'nip' => '90000099',
            'name' => 'Akun Seed Lokal',
            'status' => 'active',
            'helpdesk_access' => 'enabled',
        ]);

        $this->writeFixture([$this->row('B/22/07/2410/81', 'Orang Lain')]);

        EmployeeSync::run();

        $this->assertNull($lokal->fresh()->synced_at);
    }

    /**
     * NPP murni angka memang ada di data sungguhan — satu dari 3.847. Ia harus
     * ditandai seperti pegawai lain, bukan disangka akun seed karena bentuknya.
     */
    public function test_npp_yang_murni_angka_tetap_ditandai_sebagai_direktori(): void
    {
        $this->writeFixture([$this->row('12345678', 'NPP Angka Saja')]);

        EmployeeSync::run();

        $this->assertNotNull(User::where('nip', '12345678')->firstOrFail()->synced_at);
    }

    /** @return array<string,mixed> */
    private function row(string $npp, string $name): array
    {
        return [
            'npp' => $npp,
            'name' => $name,
            'email' => strtolower(str_replace(' ', '.', $name)).'@adhi.test',
            'active' => 'Y',
            'job_position' => 'Staff',
        ];
    }

    /** @param  array<int,array<string,mixed>>  $rows */
    private function writeFixture(array $rows): void
    {
        file_put_contents($this->fixturePath, json_encode($rows));
    }
}
