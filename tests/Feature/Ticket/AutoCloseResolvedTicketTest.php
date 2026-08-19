<?php

declare(strict_types=1);

namespace Tests\Feature\Ticket;

use App\Models\AuditTrail;
use App\Models\Role;
use App\Models\SlaPolicy;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Tiket yang sudah Resolved tapi tidak dikonfirmasi requester akan menutup
 * sendiri setelah tenggang (bawaan 3 hari, config
 * `helpdesk.auto_close_resolved_after_days`).
 *
 * Yang dijaga tes ini bukan cuma "apakah tertutup", tapi apa yang TIDAK boleh
 * ikut tertutup: tiket yang belum lewat tenggang, tiket yang masih dikerjakan,
 * dan — yang paling mudah lolos — tiket yang sudah dibuka kembali. Reopen
 * menyetel resolved_at kembali ke null, jadi hitungannya harus ikut hilang,
 * bukan meneruskan hitungan lama.
 */
final class AutoCloseResolvedTicketTest extends TestCase
{
    use RefreshDatabase;

    private ?int $slaPolicyId = null;

    private ?User $requester = null;

    private function requester(): User
    {
        if ($this->requester) {
            return $this->requester;
        }

        $user = User::factory()->create([
            'name' => 'Andi Pratama',
            'email' => 'andi.autoclose@adhi.co.id',
            'status' => 'active',
            'helpdesk_access' => 'enabled',
        ]);

        $user->roles()->attach(Role::firstOrCreate(
            ['name' => 'Requester'],
            ['type' => 'system', 'status' => 'active'],
        )->id);

        return $this->requester = $user;
    }

    private function buatTiket(string $status, ?Carbon $resolvedAt, ?User $requester = null): Ticket
    {
        $start = Carbon::now()->subDays(10);

        return Ticket::create([
            'ticket_no' => 'INC-UJI-2026-'.random_int(1000, 9999),
            'title' => 'Tiket uji auto-close',
            'requester_id' => $requester?->id,
            'requester_name' => $requester?->name ?? 'Andi Pratama',
            'status' => $status,
            'priority' => 'Critical',
            'sla_policy_id' => $this->slaPolicyId(),
            'response_time_minutes' => 60,
            'resolution_time_minutes' => 240,
            'warning_threshold_percent' => 80,
            'response_due_at' => $start->clone()->addHour(),
            'resolution_due_at' => $start->clone()->addHours(4),
            'warning_at' => $start->clone()->addHours(3),
            'created_at' => $start,
            'resolved_at' => $resolvedAt,
        ]);
    }

    private function slaPolicyId(): int
    {
        return $this->slaPolicyId ??= SlaPolicy::create([
            'policy_name' => 'Uji Auto Close',
            'priority' => 'Critical',
            'service_type' => 'Incident',
            'response_time_minutes' => 60,
            'resolution_time_minutes' => 240,
            'warning_threshold_percent' => 80,
            'status' => 'active',
        ])->id;
    }

    public function test_tiket_resolved_lewat_tiga_hari_tertutup_sendiri(): void
    {
        $ticket = $this->buatTiket('Resolved', Carbon::now()->subDays(3)->subMinute(), $this->requester());

        $this->artisan('tickets:auto-close')->assertSuccessful();

        $this->assertSame('Closed', $ticket->fresh()->status);
    }

    public function test_tiket_resolved_yang_belum_lewat_tenggang_dibiarkan(): void
    {
        $ticket = $this->buatTiket('Resolved', Carbon::now()->subDays(2)->subHours(23), $this->requester());

        $this->artisan('tickets:auto-close')->assertSuccessful();

        $this->assertSame('Resolved', $ticket->fresh()->status);
    }

    public function test_tiket_yang_masih_dikerjakan_tidak_ikut_tertutup(): void
    {
        $terbuka = $this->buatTiket('In Progress', null, $this->requester());
        $menunggu = $this->buatTiket('Open', null, $this->requester());

        $this->artisan('tickets:auto-close')->assertSuccessful();

        $this->assertSame('In Progress', $terbuka->fresh()->status);
        $this->assertSame('Open', $menunggu->fresh()->status);
    }

    /**
     * Reopen menyetel resolved_at ke null. Kalau penyapu membaca tanggal lain
     * (created_at, updated_at) tiket yang baru dibuka kembali akan langsung
     * tertutup lagi — persis kebalikan dari yang diminta requester.
     */
    public function test_tiket_yang_dibuka_kembali_tidak_tertutup_sendiri(): void
    {
        // Sudah lama lewat tenggang saat masih Resolved…
        $ticket = $this->buatTiket('Resolved', Carbon::now()->subDays(9), $this->requester());

        // …lalu requester membukanya kembali, persis seperti
        // TicketDetailController::reopen(): resolved_at dikosongkan.
        $ticket->update(['status' => 'In Progress', 'resolved_at' => null]);

        $this->artisan('tickets:auto-close')->assertSuccessful();

        $this->assertSame('In Progress', $ticket->fresh()->status);
        $this->assertNull($ticket->fresh()->auto_close_at);
    }

    public function test_tiket_yang_sudah_ditutup_tidak_disentuh_lagi(): void
    {
        $ticket = $this->buatTiket('Closed', Carbon::now()->subDays(9), $this->requester());
        $sebelum = $ticket->updated_at;

        $this->artisan('tickets:auto-close')->assertSuccessful();

        $this->assertSame('Closed', $ticket->fresh()->status);
        $this->assertEquals($sebelum, $ticket->fresh()->updated_at);
    }

    /**
     * Penutupan manual mewajibkan rating 1–5. Penutupan otomatis tidak punya
     * siapa-siapa untuk memberi nilai, jadi ratingnya harus tetap kosong —
     * bukan diisi angka karangan yang nanti ikut terhitung di CSAT Team Lead.
     */
    public function test_tiket_yang_tertutup_otomatis_tidak_punya_rating(): void
    {
        $ticket = $this->buatTiket('Resolved', Carbon::now()->subDays(4), $this->requester());

        $this->artisan('tickets:auto-close')->assertSuccessful();

        $this->assertNull($ticket->fresh()->satisfaction_rating);
    }

    public function test_requester_diberi_tahu_tiketnya_ditutup_otomatis(): void
    {
        $requester = $this->requester();
        $ticket = $this->buatTiket('Resolved', Carbon::now()->subDays(4), $requester);

        $this->artisan('tickets:auto-close')->assertSuccessful();

        $this->assertDatabaseHas('ticket_notifications', [
            'user_id' => $requester->id,
            'ticket_id' => $ticket->id,
        ]);
    }

    public function test_penutupan_otomatis_meninggalkan_jejak_audit(): void
    {
        User::factory()->create(['email' => 'admin.autoclose@adhi.co.id'])
            ->roles()->attach(Role::firstOrCreate(
                ['name' => 'Administrator'],
                ['type' => 'system', 'status' => 'active'],
            )->id);

        $ticket = $this->buatTiket('Resolved', Carbon::now()->subDays(4), $this->requester());

        $this->artisan('tickets:auto-close')->assertSuccessful();

        $this->assertDatabaseHas('audit_trails', [
            'action' => 'auto_close',
            'target_name' => $ticket->ticket_no,
        ]);
    }

    public function test_tenggang_bisa_diatur_lewat_config(): void
    {
        config(['helpdesk.auto_close_resolved_after_days' => 7]);

        $ticket = $this->buatTiket('Resolved', Carbon::now()->subDays(4), $this->requester());

        $this->artisan('tickets:auto-close')->assertSuccessful();

        $this->assertSame('Resolved', $ticket->fresh()->status);
    }

    /**
     * Menyetel tenggang ke 0 mematikan fiturnya, bukan menutup semua tiket
     * Resolved seketika.
     */
    public function test_tenggang_nol_mematikan_penutupan_otomatis(): void
    {
        config(['helpdesk.auto_close_resolved_after_days' => 0]);

        $ticket = $this->buatTiket('Resolved', Carbon::now()->subDays(30), $this->requester());

        $this->artisan('tickets:auto-close')->assertSuccessful();

        $this->assertSame('Resolved', $ticket->fresh()->status);
    }

    public function test_countdown_dihitung_dari_waktu_resolve(): void
    {
        $resolvedAt = Carbon::now()->subDay();
        $ticket = $this->buatTiket('Resolved', $resolvedAt, $this->requester());

        $this->assertEquals(
            $resolvedAt->clone()->addDays(3)->timestamp,
            $ticket->auto_close_at->timestamp,
        );
    }

    public function test_tiket_yang_belum_resolved_tidak_punya_countdown(): void
    {
        $ticket = $this->buatTiket('In Progress', null, $this->requester());

        $this->assertNull($ticket->auto_close_at);
        $this->assertNull($ticket->autoClosePayload());
    }

    public function test_payload_countdown_membawa_tenggat_dan_sisa_waktu(): void
    {
        $ticket = $this->buatTiket('Resolved', Carbon::now()->subDay(), $this->requester());

        $payload = $ticket->autoClosePayload();

        $this->assertNotNull($payload);
        $this->assertArrayHasKey('at', $payload);
        $this->assertArrayHasKey('minutesRemaining', $payload);
        // Dua hari tersisa dari tenggang tiga hari.
        $this->assertEqualsWithDelta(2 * 24 * 60, $payload['minutesRemaining'], 5);
    }
}
