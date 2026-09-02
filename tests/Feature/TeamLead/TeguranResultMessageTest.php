<?php

declare(strict_types=1);

namespace Tests\Feature\TeamLead;

use App\Support\TeguranNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Kalimat hasil teguran tidak boleh menjanjikan lebih dari yang sudah terjadi.
 *
 * Email dititipkan ke antrean; pengiriman SMTP-nya baru terjadi belakangan di
 * worker, dan kegagalannya mendarat di failed_jobs — jauh dari mata orang yang
 * menekan tombolnya. Kalimat lama berbunyi "terkirim" untuk keduanya, dan
 * selama berhari-hari MAIL_MAILER di produksi masih `log` kalimat itu tampil
 * setiap kali tanpa sekali pun keliru secara teknis. Justru itu yang membuat
 * masalahnya tidak ketahuan.
 */
final class TeguranResultMessageTest extends TestCase
{
    use RefreshDatabase;

    public function test_email_yang_masih_diantrekan_tidak_disebut_sudah_terkirim(): void
    {
        config(['queue.default' => 'database']);

        $pesan = TeguranNotifier::resultMessage(['inapp', 'email']);

        $this->assertStringNotContainsString(
            'terkirim',
            $pesan,
            'Selama email masih di antrean, kalimatnya tidak boleh mengaku sudah terkirim.'
        );
        $this->assertStringContainsString('latar belakang', $pesan);
    }

    public function test_antrean_sync_mengirim_inline_jadi_boleh_disebut_terkirim(): void
    {
        config(['queue.default' => 'sync']);

        $this->assertStringContainsString('terkirim', TeguranNotifier::resultMessage(['inapp', 'email']));
    }

    public function test_tanpa_email_kalimatnya_tetap_tegas(): void
    {
        config(['queue.default' => 'database']);

        // Lonceng in-app benar-benar sudah terisi saat kalimat ini disusun.
        $this->assertStringContainsString('terkirim', TeguranNotifier::resultMessage(['inapp']));
    }

    public function test_tidak_ada_channel_yang_berhasil_dikatakan_apa_adanya(): void
    {
        $pesan = TeguranNotifier::resultMessage([]);

        $this->assertStringContainsString('tidak ada channel', $pesan);
        $this->assertStringNotContainsString('terkirim via', $pesan);
    }
}
