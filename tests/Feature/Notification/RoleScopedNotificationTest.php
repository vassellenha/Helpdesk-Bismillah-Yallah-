<?php

declare(strict_types=1);

namespace Tests\Feature\Notification;

use App\Models\Role;
use App\Models\TicketNotification;
use App\Models\User;
use App\Support\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Notifikasi milik SATU peran, bukan milik orang.
 *
 * Orang yang memegang dua peran — Marcell memegang Administrator dan Requester,
 * Karina memegang Approver dan Requester — dulu melihat seluruh notifikasinya
 * bercampur di lonceng mana pun ia berada. Sedang membuka layar Requester, tapi
 * yang muncul "Tiket menunggu keputusan Anda"; ditekan, dan ia mendarat di
 * layar yang bukan miliknya saat itu.
 *
 * Penyebabnya satu baris: present() hanya menyaring user_id. Kolom `role`
 * menutupnya di sumbernya — tiap baris notifikasi kini menyatakan untuk peran
 * apa ia dibuat, dan lonceng hanya membaca miliknya sendiri.
 */
final class RoleScopedNotificationTest extends TestCase
{
    use RefreshDatabase;

    /** Marcell: satu orang, dua peran. */
    private function orangDuaPeran(): User
    {
        $user = User::factory()->create([
            'name' => 'Marcell Laforteza',
            'email' => 'marcell.duaperan@adhi.co.id',
            'status' => 'active',
            'helpdesk_access' => 'enabled',
        ]);

        foreach (['Approver', 'Requester'] as $nama) {
            $user->roles()->attach(Role::firstOrCreate(
                ['name' => $nama],
                ['type' => 'system', 'status' => 'active'],
            )->id);
        }

        return $user;
    }

    public function test_notifikasi_approver_tidak_muncul_di_lonceng_requester(): void
    {
        $user = $this->orangDuaPeran();

        NotificationService::notify($user, 'approver', null, 'waiting_decision', 'Menunggu Keputusan', 'Ada tiket menunggu persetujuan Anda.');

        $feedRequester = NotificationService::present($user, 'requester');

        $this->assertCount(0, $feedRequester['items']);
        $this->assertSame(0, $feedRequester['unreadCount']);
    }

    public function test_notifikasi_approver_muncul_di_lonceng_approver(): void
    {
        $user = $this->orangDuaPeran();

        NotificationService::notify($user, 'approver', null, 'waiting_decision', 'Menunggu Keputusan', 'Ada tiket menunggu persetujuan Anda.');

        $feedApprover = NotificationService::present($user, 'approver', 20, 'approver.tickets.show', 'approver.notifications.read');

        $this->assertCount(1, $feedApprover['items']);
        $this->assertSame('Ada tiket menunggu persetujuan Anda.', $feedApprover['items'][0]['text']);
    }

    public function test_tiap_peran_hanya_melihat_miliknya_sendiri(): void
    {
        $user = $this->orangDuaPeran();

        NotificationService::notify($user, 'requester', null, 'ticket_created', 'Tiket Dibuat', 'Tiket Anda berhasil dibuat.');
        NotificationService::notify($user, 'approver', null, 'waiting_decision', 'Menunggu Keputusan', 'Ada tiket menunggu persetujuan Anda.');

        $requester = NotificationService::present($user, 'requester');
        $approver = NotificationService::present($user, 'approver', 20, 'approver.tickets.show', 'approver.notifications.read');

        $this->assertCount(1, $requester['items']);
        $this->assertCount(1, $approver['items']);
        $this->assertSame('Tiket Anda berhasil dibuat.', $requester['items'][0]['text']);
        $this->assertSame('Ada tiket menunggu persetujuan Anda.', $approver['items'][0]['text']);
    }

    /**
     * Peran lain yang TIDAK dipegang orang ini tetap kosong — kolom role bukan
     * sekadar label, ia benar-benar menyaring.
     */
    public function test_peran_yang_tidak_punya_notifikasi_tampil_kosong(): void
    {
        $user = $this->orangDuaPeran();

        NotificationService::notify($user, 'approver', null, 'waiting_decision', 'Menunggu Keputusan', 'Ada tiket menunggu persetujuan Anda.');

        $this->assertCount(0, NotificationService::present($user, 'support', 20, 'support.tickets.show', 'support.notifications.read')['items']);
        $this->assertCount(0, NotificationService::present($user, 'support-bpo', 20, 'support-bpo.tickets.show', 'support-bpo.notifications.read')['items']);
    }

    public function test_peran_ikut_tersimpan_di_basis_data(): void
    {
        $user = $this->orangDuaPeran();

        NotificationService::notify($user, 'approver', null, 'waiting_decision', 'Menunggu Keputusan', 'Pesan.');

        $this->assertDatabaseHas('ticket_notifications', [
            'user_id' => $user->id,
            'role' => 'approver',
        ]);
    }

    /**
     * Menandai satu notifikasi terbaca tidak boleh ikut menyentuh peran lain.
     * Keduanya milik orang yang sama, jadi tanpa penyaringan peran hitungan
     * belum-dibaca akan turun di dua lonceng sekaligus.
     */
    public function test_hitungan_belum_dibaca_dihitung_per_peran(): void
    {
        $user = $this->orangDuaPeran();

        NotificationService::notify($user, 'requester', null, 'ticket_created', 'Tiket Dibuat', 'A');
        NotificationService::notify($user, 'approver', null, 'waiting_decision', 'Menunggu', 'B');
        NotificationService::notify($user, 'approver', null, 'waiting_decision', 'Menunggu', 'C');

        $this->assertSame(1, TicketNotification::where('user_id', $user->id)->where('role', 'requester')->whereNull('read_at')->count());
        $this->assertSame(2, TicketNotification::where('user_id', $user->id)->where('role', 'approver')->whereNull('read_at')->count());
    }
}
