<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Exceptions\AccountInactive;
use App\Models\User;
use App\Support\CurrentActor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Concerns\ActsAsRole;
use Tests\TestCase;

/**
 * Aktor sekarang adalah ORANG YANG MASUK, bukan persona tetap.
 *
 * Berkas ini dulu menguji hal yang berlawanan: bahwa tujuh persona tetap
 * ditemukan lewat NIP dan bertahan meski admin mengubah nama & email mereka.
 * Seluruh mekanisme itu dicabut saat login dipasang — selama ada jalur yang
 * mengembalikan identitas tanpa login, hak akses tidak bisa diuji sama sekali,
 * karena URL role mana pun selalu tembus.
 *
 * Yang diuji sekarang adalah gerbangnya: siapa yang diterima, siapa yang
 * ditolak, dan dengan alasan apa.
 */
final class CurrentActorTest extends TestCase
{
    use ActsAsRole, RefreshDatabase;

    public function test_mengembalikan_user_yang_masuk_untuk_role_yang_dipegangnya(): void
    {
        $user = $this->actingAsRole('approver');

        $this->assertSame($user->id, CurrentActor::approver()->id);
    }

    public function test_satu_user_bisa_menjadi_aktor_di_semua_role_yang_dipegangnya(): void
    {
        // Pola yang paling umum di data nyata: hampir semua orang memegang
        // Requester di samping peran fungsionalnya.
        $user = $this->actingAsRole('requester', 'team-lead');

        $this->assertSame($user->id, CurrentActor::requester()->id);
        $this->assertSame($user->id, CurrentActor::teamLead()->id);
    }

    public function test_menolak_user_yang_tidak_memegang_role_itu(): void
    {
        $this->actingAsRole('requester');

        // Inti dari seluruh perubahan: seorang Requester yang membuka layar
        // Admin TIDAK diam-diam menjadi Administrator.
        $this->assertAbortsWith(403, fn () => CurrentActor::admin());
    }

    public function test_menolak_tamu_dengan_401_bukan_403(): void
    {
        // Dua keadaan yang berbeda dan butuh jawaban berbeda: tamu diantar ke
        // halaman masuk, sedangkan orang yang salah role tidak perlu disuruh
        // masuk ulang — akunnya memang tidak berhak.
        $this->assertAbortsWith(401, fn () => CurrentActor::admin());
    }

    public function test_akun_nonaktif_ditolak_meski_rolenya_benar(): void
    {
        $user = User::factory()->create(['helpdesk_access' => 'disabled']);
        $this->actingAsUserWithRoles($user, 'admin');

        // Saklar helpdesk-nya dimatikan Admin SETELAH sesi berjalan; sesi yang
        // sah tidak boleh menjadi alasan untuk melewatinya.
        $this->expectException(AccountInactive::class);

        CurrentActor::admin();
    }

    public function test_akun_yang_nonaktif_di_data_kepegawaian_juga_ditolak(): void
    {
        $user = User::factory()->create(['status' => 'inactive']);
        $this->actingAsUserWithRoles($user, 'admin');

        $this->expectException(AccountInactive::class);

        CurrentActor::admin();
    }

    public function test_user_mengembalikan_null_untuk_tamu(): void
    {
        $this->assertNull(CurrentActor::user());
    }

    public function test_user_tidak_peduli_role_sama_sekali(): void
    {
        // Dipakai portal dan tombol switch role, yang justru bertugas MENYUSUN
        // daftar role — jadi tidak boleh menuntut salah satunya lebih dulu.
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->assertSame($user->id, CurrentActor::user()?->id);
    }

    public function test_menolak_akun_tanpa_role_apa_pun(): void
    {
        // Akun yang sudah ada di direktori pegawai tapi belum diberi role
        // apa pun oleh Admin: masuk sah, tapi belum ada layar yang terbuka.
        $this->actingAs(User::factory()->create());

        $this->assertAbortsWith(403, fn () => CurrentActor::requester());
    }

    /**
     * abort() menyimpan statusnya di getStatusCode(), BUKAN di exception code
     * (yang selalu 0) — expectExceptionCode() lolos begitu saja tanpa pernah
     * memeriksa status yang dimaksud.
     */
    private function assertAbortsWith(int $status, callable $act): void
    {
        try {
            $act();
        } catch (HttpException $e) {
            $this->assertSame($status, $e->getStatusCode());

            return;
        }

        $this->fail("Seharusnya ditolak dengan {$status}, tapi tidak ada penolakan sama sekali.");
    }
}
