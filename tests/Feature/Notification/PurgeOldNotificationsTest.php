<?php

declare(strict_types=1);

namespace Tests\Feature\Notification;

use App\Models\TicketNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Notifikasi menumpuk tanpa batas: satu requester aktif mengumpulkan sekitar
 * dua per hari, jadi setelah setahun loncengnya berisi ratusan baris dan
 * halaman riwayatnya belasan. Tidak ada yang hilang kalau yang lama dibuang —
 * catatan permanennya ada di Audit Trail dan pada tiketnya sendiri; notifikasi
 * hanya pemberitahuan.
 *
 * Dua ambang, bukan satu. Yang sudah dibaca adalah mayoritas dan sudah selesai
 * tugasnya, jadi dibuang lebih cepat. Yang belum dibaca masih berupa sinyal
 * yang belum sempat dilihat orangnya — pegawai yang cuti sebulan tidak boleh
 * pulang ke lonceng bersih padahal ada yang terlewat — jadi ditahan lebih lama.
 */
final class PurgeOldNotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_yang_sudah_dibaca_dibuang_setelah_lewat_ambangnya(): void
    {
        config(['helpdesk.notification_retention.read_days' => 30]);
        $user = User::factory()->create();

        $lama = $this->notifikasi($user, umurHari: 31, dibaca: true);
        $baru = $this->notifikasi($user, umurHari: 29, dibaca: true);

        $this->artisan('notifications:purge')->assertSuccessful();

        $this->assertDatabaseMissing('ticket_notifications', ['id' => $lama->id]);
        $this->assertDatabaseHas('ticket_notifications', ['id' => $baru->id]);
    }

    public function test_yang_belum_dibaca_ditahan_lebih_lama(): void
    {
        config([
            'helpdesk.notification_retention.read_days' => 30,
            'helpdesk.notification_retention.unread_days' => 90,
        ]);
        $user = User::factory()->create();

        // Umur yang sama-sama melewati ambang "sudah dibaca", tapi belum dibaca.
        $masihDitahan = $this->notifikasi($user, umurHari: 60, dibaca: false);
        $sudahLewat = $this->notifikasi($user, umurHari: 91, dibaca: false);

        $this->artisan('notifications:purge')->assertSuccessful();

        $this->assertDatabaseHas('ticket_notifications', ['id' => $masihDitahan->id]);
        $this->assertDatabaseMissing('ticket_notifications', ['id' => $sudahLewat->id]);
    }

    public function test_dry_run_tidak_menghapus_apa_pun(): void
    {
        config(['helpdesk.notification_retention.read_days' => 30]);
        $user = User::factory()->create();
        $lama = $this->notifikasi($user, umurHari: 200, dibaca: true);

        $this->artisan('notifications:purge --dry-run')->assertSuccessful();

        $this->assertDatabaseHas('ticket_notifications', ['id' => $lama->id]);
    }

    public function test_aman_dijalankan_saat_tidak_ada_yang_perlu_dibuang(): void
    {
        $user = User::factory()->create();
        $this->notifikasi($user, umurHari: 1, dibaca: true);

        $this->artisan('notifications:purge')->assertSuccessful();

        $this->assertSame(1, TicketNotification::count());
    }

    private function notifikasi(User $user, int $umurHari, bool $dibaca): TicketNotification
    {
        $waktu = Carbon::now()->subDays($umurHari);

        $notifikasi = TicketNotification::create([
            'user_id' => $user->id,
            'role' => 'requester',
            'type' => 'ticket_created',
            'title' => 'Tiket Baru',
            'message' => 'Pemberitahuan uji berumur '.$umurHari.' hari.',
            'read_at' => $dibaca ? $waktu : null,
        ]);

        // created_at yang dikirim ke create() diabaikan Eloquent — ia mengisi
        // timestamp sendiri. Umurnya disetel lewat query builder supaya
        // benar-benar tersimpan.
        TicketNotification::where('id', $notifikasi->id)->update(['created_at' => $waktu]);

        return $notifikasi->fresh();
    }
}
