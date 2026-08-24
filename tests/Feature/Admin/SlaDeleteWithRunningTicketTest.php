<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\SlaPolicy;
use App\Models\Ticket;
use App\Support\PriorityRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsRole;
use Tests\TestCase;

/**
 * "SLA Impossible saya hapus, padahal ada tiket Impossible yang masih jalan —
 * apa yang terjadi?"
 *
 * Ada DUA cara sebuah tiket bisa berprioritas Impossible, dan keduanya
 * berperilaku berbeda saat policy-nya dihapus. Berkas ini menguji dua-duanya.
 */
final class SlaDeleteWithRunningTicketTest extends TestCase
{
    use ActsAsRole;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        PriorityRegistry::flush();
    }

    private function policy(string $priority, int $resolution): SlaPolicy
    {
        $p = SlaPolicy::create([
            'policy_name' => $priority.' Policy',
            'priority' => $priority,
            'response_time_minutes' => 60,
            'resolution_time_minutes' => $resolution,
            'escalation_extension_minutes' => 60,
            'warning_threshold_percent' => 80,
            'status' => 'active',
        ]);
        PriorityRegistry::flush();

        return $p;
    }

    private function ticket(string $priority, SlaPolicy $policy): Ticket
    {
        return Ticket::create([
            'ticket_no' => 'INC-UJI-2026-'.random_int(1000, 9999),
            'title' => 'Tiket berjalan',
            'requester_name' => 'Andi Pratama',
            'status' => 'In Progress',
            'priority' => $priority,
            'sla_policy_id' => $policy->id,
            'response_time_minutes' => 60,
            'resolution_time_minutes' => $policy->resolution_time_minutes,
            'warning_threshold_percent' => 80,
            'response_due_at' => now()->addHour(),
            'resolution_due_at' => now()->addHours(3),
            'warning_at' => now()->addHours(2),
        ]);
    }

    public function test_tiket_dibuat_dengan_sla_impossible_menahan_penghapusan(): void
    {
        // Jalur normal: requester memilih Impossible saat membuat tiket, jadi
        // tiketnya benar-benar menunjuk policy itu.
        $impossible = $this->policy('Impossible', 150);
        $ticket = $this->ticket('Impossible', $impossible);

        $this->actingAsRole('admin');

        $this->deleteJson("/admin/sla-policies/{$impossible->id}")
            ->assertStatus(422)
            ->assertJson(['tickets_using' => 1]);

        // Tiketnya aman, prioritasnya masih dikenali.
        $ticket = $ticket->fresh();
        $this->assertSame('Impossible', $ticket->priority);
        $this->assertSame($impossible->id, $ticket->sla_policy_id);
        $this->assertContains($ticket->sla_kind, ['ontrack', 'warning', 'breach']);
    }

    public function test_tiket_yang_dinaikkan_team_lead_ikut_menahan_penghapusan(): void
    {
        // Jalur kedua: tiket lahir sebagai Low, lalu Team Lead menaikkannya ke
        // Impossible. raisePriority hanya mengganti kolom `priority` — kolom
        // `sla_policy_id` tetap menunjuk policy Low, jadi ketergantungan ini
        // TIDAK terlihat lewat foreign key. Menghitung foreign key saja akan
        // meloloskan penghapusan dan meninggalkan tiket itu memegang prioritas
        // yang tidak diakui policy mana pun.
        $impossible = $this->policy('Impossible', 150);
        $low = $this->policy('Low', 7200);
        $ticket = $this->ticket('Low', $low);

        $ticket->update(['priority' => 'Impossible']);

        $this->actingAsRole('admin');

        $this->deleteJson("/admin/sla-policies/{$impossible->id}")
            ->assertStatus(422)
            ->assertJson(['tickets_using' => 1]);

        // Prioritasnya tetap dikenali karena policy-nya masih ada.
        PriorityRegistry::flush();
        $this->assertContains('Impossible', PriorityRegistry::all());
        $this->assertSame('Impossible', $ticket->fresh()->priority);
    }

    public function test_policy_kembar_tidak_saling_menahan(): void
    {
        // Dua policy dengan prioritas sama: menghapus salah satunya tidak
        // membuat tiket yatim, karena nama prioritasnya masih dipegang yang
        // lain. Penjaganya tidak boleh ikut menahan di kasus ini.
        $utama = $this->policy('Impossible', 150);
        $cadangan = $this->policy('Impossible', 200);
        $this->ticket('Impossible', $utama);

        $this->actingAsRole('admin');

        $this->deleteJson("/admin/sla-policies/{$cadangan->id}")->assertOk();

        PriorityRegistry::flush();
        $this->assertContains('Impossible', PriorityRegistry::all());
    }
}
