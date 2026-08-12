<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsRole;
use Tests\TestCase;

/**
 * Filter "Unit Kerja" di User & Role Management.
 *
 * Pilihan dropdown-nya harus datang dari isi tabel, bukan dari daftar tetap.
 * Dulu daftarnya DummyData::unitOrganisasi() — delapan nama karangan dari masa
 * mockup — sementara penyaringnya mencocokkan persis ke `users.unit`. Begitu
 * data pegawai ditarik dari API perusahaan, `unit` berisi nama departemen ADHI
 * yang sungguhan dan tak satu pun cocok, jadi setiap pilihan mengembalikan nol
 * baris.
 *
 * Nama unit di berkas ini SENGAJA dipilih yang tidak ada di daftar dummy lama:
 * kalau daftar tetap itu kembali, tes ini gagal.
 */
final class UserUnitFilterTest extends TestCase
{
    use ActsAsRole, RefreshDatabase;

    private const UNIT_API = 'Dept. Teknologi Informasi';

    private function pegawai(string $nama, ?string $unit): User
    {
        return User::factory()->create([
            'name' => $nama,
            'unit' => $unit,
            'status' => 'active',
            'helpdesk_access' => 'enabled',
        ]);
    }

    /**
     * Isi dropdown dibaca dari prop `unitOrganisasi`, BUKAN dari teks halaman.
     *
     * Versi pertama tes ini memakai assertSee() atas nama unitnya dan lulus juga
     * di kode lama — nama itu memang muncul di halaman, tapi lewat baris daftar
     * user, bukan lewat dropdown. Tes yang tidak bisa membedakan keduanya tidak
     * menguji apa pun.
     *
     * @return list<string>
     */
    private function pilihanDropdown(): array
    {
        $html = $this->get(route('admin.users'))->assertOk()->getContent();

        preg_match('/&quot;unitOrganisasi&quot;:(\[.*?\])/', $html, $m);

        return json_decode(html_entity_decode($m[1] ?? '[]'), true) ?: [];
    }

    public function test_dropdown_memuat_unit_dari_data_pegawai(): void
    {
        $this->actingAsRole('admin');
        $this->pegawai('Sari', self::UNIT_API);

        $this->assertContains(self::UNIT_API, $this->pilihanDropdown());
    }

    /**
     * Invarian yang sebenarnya: SETIAP pilihan yang bisa diklik harus
     * mengembalikan baris.
     *
     * Inilah yang dilanggar daftar tetap itu — kedelapan pilihannya bisa diklik
     * dan kedelapannya mengembalikan nol baris, sehingga filternya terasa rusak
     * padahal query-nya benar.
     */
    public function test_setiap_pilihan_dropdown_mengembalikan_baris(): void
    {
        $this->actingAsRole('admin');
        $this->pegawai('Sari', self::UNIT_API);
        $this->pegawai('Budi', 'Dept. Keuangan');

        $pilihan = $this->pilihanDropdown();
        $this->assertNotEmpty($pilihan, 'Dropdown kosong — tidak ada yang bisa diuji.');

        foreach ($pilihan as $unit) {
            $hasil = $this->getJson(route('admin.users.list', ['unit' => $unit]))->assertOk()->json();
            $baris = collect(data_get($hasil, 'users', $hasil));

            $this->assertNotEmpty(
                $baris,
                "Unit \"{$unit}\" ditawarkan di dropdown tapi tidak cocok dengan satu baris pun.",
            );
        }
    }

    public function test_unit_kosong_dan_null_tidak_jadi_pilihan(): void
    {
        $this->actingAsRole('admin');
        $this->pegawai('Tanpa Unit', null);
        $this->pegawai('Unit Kosong', '');
        $this->pegawai('Sari', self::UNIT_API);

        $html = $this->get(route('admin.users'))->assertOk()->getContent();

        // Satu-satunya unit yang layak ditawarkan adalah yang benar-benar terisi.
        preg_match('/&quot;unitOrganisasi&quot;:\[(.*?)\]/', $html, $m);
        $this->assertSame('&quot;'.self::UNIT_API.'&quot;', $m[1] ?? '');
    }

    public function test_daftarnya_unik_dan_terurut(): void
    {
        $this->actingAsRole('admin');
        $this->pegawai('A', 'Dept. Zeta');
        $this->pegawai('B', 'Dept. Alfa');
        $this->pegawai('C', 'Dept. Zeta');

        $html = $this->get(route('admin.users'))->assertOk()->getContent();
        preg_match('/&quot;unitOrganisasi&quot;:\[(.*?)\]/', $html, $m);

        $this->assertSame('&quot;Dept. Alfa&quot;,&quot;Dept. Zeta&quot;', $m[1] ?? '');
    }
}
