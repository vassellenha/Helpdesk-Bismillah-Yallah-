<?php

namespace Tests\Feature;

use App\Models\SlaPolicy;
use App\Models\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * A resolved/completed/closed ticket used to always report "Met" — green,
 * 'ontrack' — no matter how late resolved_at actually landed relative to
 * resolution_due_at. Kecepatan Respons never had this bug because it derives
 * its status purely from signed minutes; the resolution side special-cased
 * "done" before ever looking at the numbers. See Ticket::getSlaKindAttribute()
 * / getSlaLabelAttribute().
 */
class TicketSlaBreachLabelTest extends TestCase
{
    use RefreshDatabase;

    public function test_tiket_selesai_tepat_waktu_tetap_met(): void
    {
        $start = Carbon::parse('2026-07-22 04:11:00');
        $due = $start->clone()->addHours(4);
        $resolved = $due->clone()->subHour();

        $ticket = $this->buatTiket($start, $due, $resolved, 'Resolved');

        $this->assertSame('met', $ticket->sla_kind);
        $this->assertSame('Selesai dalam SLA', $ticket->sla_label);
    }

    public function test_tiket_selesai_setelah_breach_menampilkan_breach_dan_durasinya(): void
    {
        $start = Carbon::parse('2026-07-22 04:11:00');
        $due = $start->clone()->addHours(4);
        // Resolved 14 days 11 hours after the deadline — mirrors the reported case.
        $resolved = $due->clone()->addDays(14)->addHours(11);

        $ticket = $this->buatTiket($start, $due, $resolved, 'Resolved');

        $this->assertSame('breach', $ticket->sla_kind);
        $this->assertSame('Breach +14d 11h', $ticket->sla_label);
        $this->assertSame(100, $ticket->sla_elapsed_percent);
    }

    public function test_tiket_selesai_tepat_di_batas_dianggap_breach(): void
    {
        $start = Carbon::parse('2026-07-22 04:11:00');
        $due = $start->clone()->addHours(4);

        $ticket = $this->buatTiket($start, $due, $due->clone(), 'Closed');

        $this->assertSame('breach', $ticket->sla_kind);
        $this->assertSame('Breach +0 min', $ticket->sla_label);
    }

    private function buatTiket(Carbon $start, Carbon $due, Carbon $resolvedAt, string $status): Ticket
    {
        return Ticket::create([
            'ticket_no' => 'INC-UJI-2026-'.random_int(1000, 9999),
            'title' => 'Tiket uji SLA',
            'requester_name' => 'Andi Pratama',
            'status' => $status,
            'priority' => 'Critical',
            'sla_policy_id' => $this->slaPolicyId(),
            'response_time_minutes' => 60,
            'resolution_time_minutes' => 240,
            'warning_threshold_percent' => 80,
            'response_due_at' => $start->clone()->addHour(),
            'resolution_due_at' => $due,
            'warning_at' => $due->clone()->subMinutes(48),
            'created_at' => $start,
            'resolved_at' => $resolvedAt,
        ]);
    }

    private ?int $slaPolicyId = null;

    private function slaPolicyId(): int
    {
        return $this->slaPolicyId ??= SlaPolicy::create([
            'policy_name' => 'Uji Breach',
            'priority' => 'Critical',
            'service_type' => 'Incident',
            'response_time_minutes' => 60,
            'resolution_time_minutes' => 240,
            'warning_threshold_percent' => 80,
            'status' => 'active',
        ])->id;
    }
}
