<?php

namespace Database\Seeders;

use App\Models\ServiceCatalogSubject;
use App\Models\SlaPolicy;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Seeds a believable ticket history for the Requester demo persona (Andi
 * Pratama) so the Requester Dashboard / My Tickets screens have real rows to
 * query instead of DummyData arrays. Two passes: a handful of hand-tuned
 * "hero" tickets that drive the SLA table and status tabs, plus lightweight
 * filler rows per month so the Created-vs-Resolved chart reflects an actual
 * GROUP BY on real data rather than a hardcoded series.
 */
class TicketSeeder extends Seeder
{
    public function run(): void
    {
        $requester = User::where('email', 'andi.pratama@adhi.co.id')->first();
        $approver = User::where('email', 'karina.putri@adhi.co.id')->first();

        if (! $requester || Ticket::where('requester_id', $requester->id)->exists()) {
            return;
        }

        $policies = SlaPolicy::where('status', 'active')->get()->keyBy('priority');
        $subjects = ServiceCatalogSubject::with(['service', 'subcategory', 'issueCategory'])->get();

        $find = function (string $serviceContains, ?string $subjectContains = null) use ($subjects) {
            $matches = $subjects->filter(fn ($s) => str_contains(strtoupper($s->service->name), strtoupper($serviceContains)));
            if ($subjectContains) {
                $narrowed = $matches->filter(fn ($s) => str_contains(strtoupper($s->name), strtoupper($subjectContains)));
                if ($narrowed->isNotEmpty()) {
                    $matches = $narrowed;
                }
            }

            return $matches->first() ?? $subjects->first();
        };

        $now = Carbon::now();
        $seq = 400;
        $nextNo = function (string $prefix) use ($now, &$seq) {
            return $prefix.'-'.$now->format('Y').'-'.str_pad((string) $seq++, 4, '0', STR_PAD_LEFT);
        };

        $hero = [
            ['prefix' => 'INC', 'title' => 'VPN keeps disconnecting when accessing SAP', 'subject' => $find('VPN'), 'priority' => 'Critical', 'status' => 'In Progress', 'daysAgo' => 0, 'dueMinutes' => -32, 'warnMinutes' => -180],
            ['prefix' => 'INC', 'title' => 'Network printer on 7th floor offline', 'subject' => $find('PERANGKAT', 'PRINT'), 'priority' => 'Low', 'status' => 'Open', 'daysAgo' => 1, 'dueMinutes' => 1580, 'warnMinutes' => 1100],
            ['prefix' => 'INC', 'title' => 'Cannot send email — mailbox full', 'subject' => $find('SILO'), 'priority' => 'High', 'status' => 'In Progress', 'daysAgo' => 1, 'dueMinutes' => 46, 'warnMinutes' => -10],
            ['prefix' => 'AR', 'title' => 'Additional SAP role for MM module', 'subject' => $find('SAP', 'TRANSAKSI MM'), 'priority' => 'Medium', 'status' => 'Waiting for Response', 'daysAgo' => 2, 'dueMinutes' => 300, 'warnMinutes' => -15],
            ['prefix' => 'SR', 'title' => 'FortiClient installation on new laptop', 'subject' => $find('SILO', 'VPN'), 'priority' => 'Low', 'status' => 'Open', 'daysAgo' => 2, 'dueMinutes' => 2880, 'warnMinutes' => 1440],
            ['prefix' => 'SR', 'title' => 'Data recap request for Q2 project report', 'subject' => $find('SILO'), 'priority' => 'Medium', 'status' => 'Assigned', 'daysAgo' => 3, 'dueMinutes' => 180, 'warnMinutes' => -30],
            ['prefix' => 'AR', 'title' => 'New SAP account for project staff', 'subject' => $find('SAP', 'AKUN'), 'priority' => 'Medium', 'status' => 'Waiting for Approval', 'daysAgo' => 4, 'dueMinutes' => null, 'warnMinutes' => null, 'approver' => true],
            ['prefix' => 'SR', 'title' => 'Software installation — AutoCAD LT', 'subject' => $find('SILO'), 'priority' => 'Medium', 'status' => 'Draft', 'daysAgo' => 5, 'dueMinutes' => null, 'warnMinutes' => null, 'draft' => true],
            ['prefix' => 'INC', 'title' => 'SAP GUI error after Windows update', 'subject' => $find('SAP', 'GUI'), 'priority' => 'High', 'status' => 'Resolved', 'daysAgo' => 23, 'resolvedDaysAgo' => 22],
            ['prefix' => 'AR', 'title' => 'VPN FortiClient activation', 'subject' => $find('VPN'), 'priority' => 'Low', 'status' => 'Completed', 'daysAgo' => 27, 'resolvedDaysAgo' => 26],
            ['prefix' => 'AR', 'title' => 'Access to sensitive finance dashboard', 'subject' => $find('PERUBAHAN'), 'priority' => 'Medium', 'status' => 'Rejected', 'daysAgo' => 31, 'approver' => true],
            ['prefix' => 'INC', 'title' => 'WiFi signal weak in meeting room 3', 'subject' => $find('NETWORK', 'WIFI'), 'priority' => 'Low', 'status' => 'Closed', 'daysAgo' => 39, 'resolvedDaysAgo' => 38],
            ['prefix' => 'SR', 'title' => 'Email setup on new mobile device', 'subject' => $find('SILO'), 'priority' => 'Low', 'status' => 'Closed', 'daysAgo' => 46, 'resolvedDaysAgo' => 45],
        ];

        foreach ($hero as $row) {
            /** @var ServiceCatalogSubject $subject */
            $subject = $row['subject'];
            $policy = $policies[$row['priority']] ?? $policies->first();
            $createdAt = $now->clone()->subDays($row['daysAgo']);

            Ticket::create([
                'ticket_no' => $nextNo($row['prefix']),
                'title' => $row['title'],
                'requester_name' => $requester->name,
                'requester_id' => $requester->id,
                'service_name' => $subject->service->name,
                'subcategory_name' => $subject->subcategory->name,
                'subject_name' => $subject->name,
                'issue_category' => $subject->issueCategory->name,
                'description' => $row['title'].'.',
                'category' => $subject->issueCategory->name,
                'sla_policy_id' => $policy->id,
                'priority' => $row['priority'],
                'approver_id' => ($row['approver'] ?? false) ? $approver?->id : null,
                'response_time_minutes' => $policy->response_time_minutes,
                'resolution_time_minutes' => $policy->resolution_time_minutes,
                'warning_threshold_percent' => $policy->warning_threshold_percent,
                'response_due_at' => $createdAt->clone()->addMinutes($policy->response_time_minutes),
                'resolution_due_at' => isset($row['dueMinutes']) ? $now->clone()->addMinutes($row['dueMinutes']) : $createdAt->clone()->addMinutes($policy->resolution_time_minutes),
                'warning_at' => isset($row['warnMinutes']) ? $now->clone()->addMinutes($row['warnMinutes']) : $createdAt->clone()->addMinutes((int) round($policy->resolution_time_minutes * $policy->warning_threshold_percent / 100)),
                'status' => $row['status'],
                'is_draft' => $row['draft'] ?? false,
                'resolved_at' => isset($row['resolvedDaysAgo']) ? $now->clone()->subDays($row['resolvedDaysAgo']) : null,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
        }

        $this->seedMonthlyFiller($requester, $subjects, $policies, $now);
    }

    /**
     * Backfills Feb–Jul (the 6 months the dashboard chart covers) with
     * enough Resolved/Closed/Open rows that "Created vs Resolved" is a real
     * monthly COUNT() instead of a fixture the chart just displays.
     */
    private function seedMonthlyFiller(User $requester, Collection $subjects, Collection $policies, Carbon $now): void
    {
        $created = [12, 18, 15, 22, 19, 24];
        $resolved = [10, 16, 14, 19, 18, 20];
        $seq = 700;

        for ($m = 5; $m >= 0; $m--) {
            $monthStart = $now->clone()->subMonths($m)->startOfMonth();
            $isCurrentMonth = $m === 0;
            $createdCount = $created[5 - $m];
            $resolvedCount = $resolved[5 - $m];

            for ($i = 0; $i < $createdCount; $i++) {
                $subject = $subjects->random();
                $priority = collect(['Low', 'Medium', 'High', 'Critical'])->random();
                $policy = $policies[$priority] ?? $policies->first();
                $createdAt = $monthStart->clone()->addDays(random_int(0, 26))->addHours(random_int(0, 23));
                if ($createdAt->greaterThan($now)) {
                    $createdAt = $now->clone()->subHours(random_int(1, 48));
                }

                // Past months are fully wrapped up by "today" — only the
                // current month can still have genuinely open/in-progress
                // tickets, otherwise the SLA table fills up with tickets
                // that have been "breached" for months.
                $willResolve = ! $isCurrentMonth || $i < $resolvedCount;
                $isRecent = $isCurrentMonth && $createdAt->greaterThan($now->clone()->subDays(6));
                $status = $willResolve ? 'Closed' : ($isRecent ? 'Open' : 'In Progress');
                $resolvedAt = $willResolve ? $createdAt->clone()->addHours(random_int(2, 96)) : null;
                if ($resolvedAt && $resolvedAt->greaterThan($now)) {
                    $resolvedAt = $now->clone()->subHours(random_int(1, 24));
                }

                Ticket::create([
                    'ticket_no' => 'TCK-'.$now->format('Y').'-'.str_pad((string) $seq++, 4, '0', STR_PAD_LEFT),
                    'title' => $subject->name.' issue — '.$subject->service->name,
                    'requester_name' => $requester->name,
                    'requester_id' => $requester->id,
                    'service_name' => $subject->service->name,
                    'subcategory_name' => $subject->subcategory->name,
                    'subject_name' => $subject->name,
                    'issue_category' => $subject->issueCategory->name,
                    'category' => $subject->issueCategory->name,
                    'sla_policy_id' => $policy->id,
                    'priority' => $priority,
                    'response_time_minutes' => $policy->response_time_minutes,
                    'resolution_time_minutes' => $policy->resolution_time_minutes,
                    'warning_threshold_percent' => $policy->warning_threshold_percent,
                    'response_due_at' => $createdAt->clone()->addMinutes($policy->response_time_minutes),
                    'resolution_due_at' => $createdAt->clone()->addMinutes($policy->resolution_time_minutes),
                    'warning_at' => $createdAt->clone()->addMinutes((int) round($policy->resolution_time_minutes * $policy->warning_threshold_percent / 100)),
                    'status' => $status,
                    'resolved_at' => $resolvedAt,
                    'created_at' => $createdAt,
                    'updated_at' => $resolvedAt ?? $createdAt,
                ]);
            }
        }
    }
}
