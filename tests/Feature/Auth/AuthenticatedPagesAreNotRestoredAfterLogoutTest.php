<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsRole;
use Tests\TestCase;

/**
 * Halaman yang sudah login tidak boleh bisa dipulihkan browser setelah logout.
 *
 * Sesi memang sudah dimatikan dengan benar — permintaan baru ke halaman mana
 * pun dialihkan ke layar Masuk. Yang bocor bukan aksesnya, melainkan
 * TAMPILANNYA: `Cache-Control: no-cache` hanya menyuruh browser memvalidasi
 * ulang sebelum memakai cache HTTP, dan sama sekali tidak mematikan
 * back-forward cache. Chrome menyimpan halaman terakhir apa adanya di memori
 * lalu memulihkannya utuh saat tombol Back ditekan.
 *
 * Akibatnya, di komputer bersama, orang berikutnya cukup menekan Back untuk
 * membaca daftar tiket, nama, unit kerja, dan jumlah notifikasi orang
 * sebelumnya. Ia tidak bisa berbuat apa-apa dari sana — setiap tindakan mental
 * ke halaman Masuk — tapi ia bisa membacanya, dan itu sudah cukup.
 *
 * `no-store` adalah satu-satunya arahan yang membuat browser tidak menyimpan
 * salinannya sama sekali.
 *
 * Ditemukan saat UAT test case 45.
 */
final class AuthenticatedPagesAreNotRestoredAfterLogoutTest extends TestCase
{
    use ActsAsRole, RefreshDatabase;

    public function test_halaman_requester_tidak_boleh_disimpan_browser(): void
    {
        $this->actingAsRole('requester');

        $this->get(route('requester.tickets'))
            ->assertOk()
            ->assertHeader('Cache-Control', 'max-age=0, must-revalidate, no-cache, no-store, private');
    }

    public function test_halaman_administrator_tidak_boleh_disimpan_browser(): void
    {
        $this->actingAsRole('admin');

        $response = $this->get(route('admin.users'))->assertOk();

        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    /**
     * Tamu tidak membawa apa pun yang perlu dirahasiakan, dan layar Masuk
     * adalah halaman yang paling sering dibuka ulang. Memaksanya no-store
     * hanya menambah beban tanpa melindungi apa pun.
     */
    public function test_halaman_masuk_tidak_ikut_dipaksa_no_store(): void
    {
        $response = $this->get(route('login'))->assertOk();

        $this->assertStringNotContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }
}
