<?php

declare(strict_types=1);

namespace Tests\Feature\Notification;

use App\Models\TicketNotification;
use App\Models\User;
use App\Support\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Lonceng hanya memuat 20 pemberitahuan terbaru. Tanpa halaman riwayat,
 * pemberitahuan yang lebih tua dari itu tidak bisa dijangkau sama sekali —
 * requester yang seminggu tidak membuka helpdesk kehilangan aksesnya, dan
 * satu-satunya cara mengosongkan penanda adalah menandai semua terbaca
 * tanpa pernah melihat isinya. Ditemukan saat UAT test case 13.
 */
final class NotificationHistoryPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_halaman_riwayat_menjangkau_pemberitahuan_di_luar_20_terbaru(): void
    {
        $user = $this->userDengan(63, 'requester');

        $halaman1 = NotificationService::history($user, 'requester', page: 1);
        $halaman4 = NotificationService::history($user, 'requester', page: 4);

        $this->assertCount(20, $halaman1['items']);
        $this->assertSame(63, $halaman1['total']);
        $this->assertSame(4, $halaman1['lastPage']);

        // Halaman terakhir memuat sisanya, termasuk pemberitahuan tertua.
        $this->assertCount(3, $halaman4['items']);
        $this->assertSame('Pemberitahuan uji ke-63', $halaman4['items'][2]['text']);
    }

    public function test_nomor_halaman_di_luar_jangkauan_ditarik_ke_batas(): void
    {
        $user = $this->userDengan(25, 'requester');

        $this->assertSame(1, NotificationService::history($user, 'requester', page: 0)['page']);
        $this->assertSame(2, NotificationService::history($user, 'requester', page: 99)['page']);
    }

    public function test_hanya_memuat_peran_yang_diminta(): void
    {
        $user = $this->userDengan(5, 'requester');
        $this->tambah($user, 'approver', 8);

        $this->assertSame(5, NotificationService::history($user, 'requester')['total']);
        $this->assertSame(8, NotificationService::history($user, 'approver')['total']);
    }

    public function test_tanpa_pemberitahuan_tetap_satu_halaman_kosong(): void
    {
        $riwayat = NotificationService::history(User::factory()->create(), 'requester');

        $this->assertSame([], $riwayat['items']);
        $this->assertSame(0, $riwayat['total']);
        $this->assertSame(1, $riwayat['lastPage']);
    }

    private function userDengan(int $jumlah, string $role): User
    {
        $user = User::factory()->create();
        $this->tambah($user, $role, $jumlah);

        return $user;
    }

    private function tambah(User $user, string $role, int $jumlah): void
    {
        for ($i = 1; $i <= $jumlah; $i++) {
            TicketNotification::create([
                'user_id' => $user->id,
                'role' => $role,
                'type' => 'ticket_created',
                'title' => 'Tiket Baru',
                'message' => 'Pemberitahuan uji ke-'.$i,
                'read_at' => null,
                // ke-1 paling baru, ke-63 paling tua
                'created_at' => Carbon::now()->subMinutes($i),
            ]);
        }
    }
}
