<?php

namespace Database\Seeders;

use App\Models\ServiceCatalogService;
use App\Models\ServiceCatalogSubject;
use App\Models\SlaPolicy;
use App\Models\Ticket;
use App\Support\TicketNumber;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Seeds 30 believable tickets for the Requester demo persona (Andi Pratama),
 * built to mirror exactly what TicketController@store produces for a real
 * submission — same PIC-from-catalog and status rules — so Requester and
 * Admin screens both reflect what's actually possible right now: Approver
 * and Support have no real workflow yet, so a ticket can only ever end up
 * Draft, Open, or Waiting for Approval. Nothing here reaches
 * Assigned/In Progress/Resolved/Closed — those require actions from roles
 * that don't exist yet.
 */
class TicketSeeder extends Seeder
{
    private const PRIORITIES = ['Low', 'Medium', 'High', 'Critical'];

    public function run(): void
    {
        $requester = User::where('email', 'andi.pratama@adhi.co.id')->first();
        $approver = User::where('email', 'karina.putri@adhi.co.id')->first();

        if (! $requester || Ticket::where('requester_id', $requester->id)->exists()) {
            return;
        }

        $policies = SlaPolicy::where('status', 'active')->get()->keyBy('priority');
        $subjects = ServiceCatalogSubject::with(['service', 'subcategory', 'issueCategory'])
            ->where('is_active', true)
            ->get();

        $approvalSubjects = $subjects->where('requires_approval', true)->values();
        $directSubjects = $subjects->where('requires_approval', false)->values();
        $otherServices = ServiceCatalogService::inRandomOrder()->take(4)->get();

        $now = Carbon::now();
        // Aturan penomoran tidak disalin ulang di sini. Deretnya per layanan,
        // jadi menghitungnya sendiri dengan satu $seq bersama akan menghasilkan
        // nomor yang berbeda dari yang dibuat form Requester.
        $nextNo = fn (string $prefix, ?string $serviceName) => TicketNumber::next($prefix, $serviceName, $now);

        // 6 Waiting for Approval — real catalog subject that requires approval.
        for ($i = 0; $i < 6; $i++) {
            $subject = $approvalSubjects->isNotEmpty() ? $approvalSubjects->random() : $directSubjects->random();
            $this->makeTicket($requester, $subject, $policies, $this->randomAge(), $now, $nextNo, true, false, $approver);
        }

        // 17 Open — real catalog subject, no approval needed.
        for ($i = 0; $i < 17; $i++) {
            $subject = $directSubjects->isNotEmpty() ? $directSubjects->random() : $subjects->random();
            $this->makeTicket($requester, $subject, $policies, $this->randomAge(), $now, $nextNo, false, false, null);
        }

        // 4 "Other" — requester couldn't find a matching catalog subject, so
        // there is no support_agent_id to inherit; PIC must stay unassigned.
        for ($i = 0; $i < 4; $i++) {
            $this->makeOtherTicket($requester, $otherServices->isNotEmpty() ? $otherServices->random() : null, $policies, $this->randomAge(), $now, $nextNo);
        }

        // 3 Draft — a real subject picked but never submitted.
        for ($i = 0; $i < 3; $i++) {
            $subject = $subjects->random();
            $this->makeTicket($requester, $subject, $policies, $this->randomAge(), $now, $nextNo, $subject->requires_approval, true, $subject->requires_approval ? $approver : null);
        }
    }

    private function randomAge(): int
    {
        // Minutes ago, spread across today only (up to 8 hours back) — these
        // are freshly created demo tickets, not a months-old backlog. A
        // Critical ticket (3h resolution target) created ~5-8h ago can
        // still land in a real SLA breach/warning, which is an honest
        // reflection of "nobody has picked it up yet" rather than a
        // fabricated one.
        return random_int(0, 8 * 60 - 1);
    }

    private function makeTicket(User $requester, ServiceCatalogSubject $subject, Collection $policies, int $minutesAgo, Carbon $now, callable $nextNo, bool $requiresApproval, bool $isDraft, ?User $approver): void
    {
        $priority = self::PRIORITIES[array_rand(self::PRIORITIES)];
        $policy = $policies[$priority] ?? $policies->first();
        $createdAt = $now->clone()->subMinutes($minutesAgo);
        $prefix = TicketNumber::prefixFor($subject->issueCategory->name);

        $status = match (true) {
            $isDraft => 'Draft',
            $requiresApproval => 'Waiting for Approval',
            default => 'Open',
        };

        Ticket::create([
            'ticket_no' => $nextNo($prefix, $subject->service->name),
            'title' => $subject->name.' — '.$subject->service->name,
            'requester_name' => $requester->name,
            'requester_id' => $requester->id,
            'service_name' => $subject->service->name,
            'subcategory_name' => $subject->subcategory->name,
            'subject_name' => $subject->name,
            'issue_category' => $subject->issueCategory->name,
            'description' => "Permintaan/laporan terkait {$subject->name} pada {$subject->service->name}.",
            'category' => $subject->issueCategory->name,
            'sla_policy_id' => $policy->id,
            'priority' => $priority,
            'approver_id' => $requiresApproval ? $approver?->id : null,
            'assigned_agent_id' => $subject->support_agent_id,
            'response_time_minutes' => $policy->response_time_minutes,
            'resolution_time_minutes' => $policy->resolution_time_minutes,
            'warning_threshold_percent' => $policy->warning_threshold_percent,
            'response_due_at' => $createdAt->clone()->addMinutes($policy->response_time_minutes),
            'resolution_due_at' => $createdAt->clone()->addMinutes($policy->resolution_time_minutes),
            'warning_at' => $createdAt->clone()->addMinutes((int) round($policy->resolution_time_minutes * $policy->warning_threshold_percent / 100)),
            'sla_started_at' => $createdAt,
            'status' => $status,
            'is_draft' => $isDraft,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }

    private function makeOtherTicket(User $requester, ?ServiceCatalogService $service, Collection $policies, int $minutesAgo, Carbon $now, callable $nextNo): void
    {
        $priority = self::PRIORITIES[array_rand(self::PRIORITIES)];
        $policy = $policies[$priority] ?? $policies->first();
        $createdAt = $now->clone()->subMinutes($minutesAgo);
        $issueCategory = collect(['Incident', 'Service Request', 'Access Request'])->random();
        $prefix = TicketNumber::prefixFor($issueCategory);

        Ticket::create([
            'ticket_no' => $nextNo($prefix, $service?->name),
            'title' => 'Permintaan lain-lain'.($service ? ' — '.$service->name : ''),
            'requester_name' => $requester->name,
            'requester_id' => $requester->id,
            'service_name' => $service?->name,
            'subcategory_name' => 'Other',
            'subject_name' => 'Lainnya (belum ada di katalog)',
            'issue_category' => $issueCategory,
            'description' => 'Permintaan di luar daftar layanan yang tersedia pada katalog saat ini.',
            'category' => $issueCategory,
            'sla_policy_id' => $policy->id,
            'priority' => $priority,
            'approver_id' => null,
            'assigned_agent_id' => null,
            'response_time_minutes' => $policy->response_time_minutes,
            'resolution_time_minutes' => $policy->resolution_time_minutes,
            'warning_threshold_percent' => $policy->warning_threshold_percent,
            'response_due_at' => $createdAt->clone()->addMinutes($policy->response_time_minutes),
            'resolution_due_at' => $createdAt->clone()->addMinutes($policy->resolution_time_minutes),
            'warning_at' => $createdAt->clone()->addMinutes((int) round($policy->resolution_time_minutes * $policy->warning_threshold_percent / 100)),
            'sla_started_at' => $createdAt,
            'status' => 'Open',
            'is_draft' => false,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }
}
