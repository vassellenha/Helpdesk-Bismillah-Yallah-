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
 * Panel lonceng hanya memuat 20 pemberitahuan terbaru supaya payload halaman
 * tidak membengkak, dan itu memang disengaja. Yang tidak boleh ikut terpotong
 * adalah ANGKA pada penanda: sebelumnya penanda dihitung dari daftar yang
 * terpotong itu, sehingga requester dengan 57 notifikasi belum dibaca melihat
 * angka 16 dan mengira sisanya sudah terbaca.
 *
 * Ditemukan saat UAT test case 13.
 */
final class UnreadBadgeCountsEveryNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_jumlah_belum_dibaca_menghitung_seluruhnya_bukan_hanya_yang_termuat(): void
    {
        $user = User::factory()->create();
        $this->buatNotifikasi($user, 'requester', belumDibaca: 30, sudahDibaca: 5);

        $feed = NotificationService::present($user, 'requester');

        $this->assertCount(20, $feed['items'], 'panel tetap dibatasi 20 pemberitahuan terbaru');
        $this->assertSame(30, $feed['unreadCount']);
    }

    public function test_hanya_menghitung_peran_yang_diminta(): void
    {
        $user = User::factory()->create();
        $this->buatNotifikasi($user, 'requester', belumDibaca: 3, sudahDibaca: 0);
        $this->buatNotifikasi($user, 'approver', belumDibaca: 7, sudahDibaca: 0);

        $this->assertSame(3, NotificationService::present($user, 'requester')['unreadCount']);
        $this->assertSame(7, NotificationService::present($user, 'approver')['unreadCount']);
    }

    public function test_tanpa_notifikasi_menghasilkan_daftar_kosong_dan_nol(): void
    {
        $feed = NotificationService::present(User::factory()->create(), 'requester');

        $this->assertSame([], $feed['items']);
        $this->assertSame(0, $feed['unreadCount']);
    }

    private function buatNotifikasi(User $user, string $role, int $belumDibaca, int $sudahDibaca): void
    {
        $urutan = 0;

        foreach ([[$belumDibaca, null], [$sudahDibaca, Carbon::now()]] as [$jumlah, $dibacaPada]) {
            for ($i = 0; $i < $jumlah; $i++) {
                TicketNotification::create([
                    'user_id' => $user->id,
                    'role' => $role,
                    'type' => 'ticket_created',
                    'title' => 'Tiket Baru',
                    'message' => 'Pemberitahuan uji ke-'.(++$urutan),
                    'read_at' => $dibacaPada,
                    'created_at' => Carbon::now()->subMinutes($urutan),
                ]);
            }
        }
    }
}
