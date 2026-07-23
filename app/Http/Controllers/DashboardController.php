<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\User;
use App\Support\CurrentActor;
use App\Support\DummyData;
use App\Support\NotificationService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function requester(): View
    {
        $requester = CurrentActor::requester();
        $tickets = Ticket::where('requester_id', $requester->id)->get();

        $active = $tickets->whereIn('status', Ticket::ACTIVE_STATUSES);
        $awaitingApproval = $tickets->where('status', 'Waiting for Approval');
        $needsResponse = $tickets->where('status', 'Waiting for Response');
        $resolved = $tickets->where('status', 'Resolved');
        $closedLast6Months = $tickets->whereIn('status', ['Closed', 'Completed'])
            ->where('created_at', '>=', Carbon::now()->subMonths(6));

        $approvalBreakdown = $awaitingApproval
            ->groupBy(fn (Ticket $t) => $t->approver?->name ?? 'Unassigned')
            ->map(fn (Collection $g, string $name) => "{$g->count()} at {$name}")
            ->values()
            ->implode(' · ');

        $slaRows = $tickets
            ->whereIn('status', Ticket::ACTIVE_STATUSES)
            ->filter(fn (Ticket $t) => $t->sla_minutes_remaining !== null)
            ->sortBy('sla_minutes_remaining')
            ->take(6)
            ->map(fn (Ticket $t) => $this->presentRow($t))
            ->values();

        $activeForDonut = $tickets->whereIn('status', Ticket::ACTIVE_STATUSES)->filter(fn (Ticket $t) => $t->sla_minutes_remaining !== null);
        $onTrack = $activeForDonut->filter(fn (Ticket $t) => $t->sla_kind === 'ontrack')->count();
        $warning = $activeForDonut->filter(fn (Ticket $t) => $t->sla_kind === 'warning')->count();
        $breach = $activeForDonut->filter(fn (Ticket $t) => $t->sla_kind === 'breach')->count();
        $slaTotal = max($onTrack + $warning + $breach, 1);

        return view('dashboard.requester', [
            'role' => 'requester',
            'currentUser' => ['name' => $requester->name, 'title' => $requester->jabatan.' · '.$requester->unit, 'initials' => $this->initials($requester->name)],
            'notifications' => $this->notifications($requester, $tickets),
            'stats' => [
                'active' => ['count' => $active->count(), 'breakdown' => $this->statusBreakdown($active)],
                'awaitingApproval' => ['count' => $awaitingApproval->count(), 'breakdown' => $approvalBreakdown ?: 'No pending approvals'],
                'needsResponse' => ['count' => $needsResponse->count()],
                'resolved' => ['count' => $resolved->count()],
                'closed' => ['count' => $closedLast6Months->count()],
            ],
            'chart' => $this->createdVsResolvedByMonth($tickets),
            'slaDonut' => [
                'total' => $onTrack + $warning + $breach,
                'onTrack' => $onTrack,
                'warning' => $warning,
                'breach' => $breach,
                'pctWithinSla' => (int) round($onTrack / $slaTotal * 100),
            ],
            'slaRows' => $slaRows,
        ]);
    }

    public function myTickets(): View
    {
        $requester = CurrentActor::requester();
        $tickets = Ticket::where('requester_id', $requester->id)->latest('created_at')->get();

        $rows = $tickets->map(fn (Ticket $t) => $this->presentRow($t))->values();

        $counts = [
            'All' => $tickets->count(),
            'Draft' => $tickets->where('status', 'Draft')->count(),
            'Active' => $tickets->whereIn('status', Ticket::ACTIVE_STATUSES)->count(),
            'Completed' => $tickets->whereIn('status', Ticket::DONE_STATUSES)->count(),
            'Rejected' => $tickets->where('status', 'Rejected')->count(),
        ];

        return view('requester.tickets', [
            'role' => 'requester',
            'currentUser' => ['name' => $requester->name, 'title' => $requester->jabatan.' · '.$requester->unit, 'initials' => $this->initials($requester->name)],
            'notifications' => $this->notifications($requester, $tickets),
            'tickets' => $rows,
            'counts' => $counts,
        ]);
    }

    public function teamLead(): View
    {
        return view('dashboard.team-lead', [
            'role' => 'team-lead',
            'agents' => DummyData::agents(),
            'slaPerformance' => DummyData::slaPerformance(),
            'ticketVolume' => DummyData::ticketVolumeByCategory(),
            'notifications' => DummyData::notifications(),
        ]);
    }

    public function eva(): View
    {
        return view('dashboard.eva', [
            'role' => 'eva',
            'articles' => DummyData::knowledgeArticles(),
            'unanswered' => DummyData::unansweredQuestions(),
            'notifications' => DummyData::notifications(),
        ]);
    }

    private function presentRow(Ticket $t): array
    {
        $elapsedPct = 0;
        if ($t->sla_minutes_remaining !== null && $t->resolution_time_minutes > 0) {
            $elapsedPct = (int) round((1 - max($t->sla_minutes_remaining, 0) / $t->resolution_time_minutes) * 100);
            $elapsedPct = max(0, min(100, $t->sla_kind === 'breach' ? 100 : $elapsedPct));
        }

        return [
            'id' => $t->ticket_no,
            'title' => $t->title,
            'app' => trim(($t->service_name ?? '').($t->subcategory_name ? ' · '.$t->subcategory_name : '')) ?: ($t->subject_name ?? '—'),
            'service' => $t->service_name ?? '—',
            'subcategory' => $t->subcategory_name ?? '—',
            'category' => $t->issue_category ?? $t->category ?? '—',
            'priority' => $t->priority,
            'status' => $t->status,
            'sla' => $t->sla_label,
            'slaKind' => $t->sla_kind,
            'slaMinutes' => $t->sla_minutes_remaining,
            'slaPct' => $elapsedPct,
            'created' => $t->created_at->format('M j, Y'),
            'createdAt' => $t->created_at->toIso8601String(),
            'href' => route('requester.tickets.show', $t),
        ];
    }

    private function statusBreakdown(Collection $active): string
    {
        return $active
            ->countBy('status')
            ->map(fn (int $count, string $status) => "{$count} {$status}")
            ->values()
            ->implode(' · ');
    }

    private function createdVsResolvedByMonth(Collection $tickets): array
    {
        $months = collect(range(5, 0))->map(fn (int $m) => Carbon::now()->subMonths($m));

        return $months->map(function (Carbon $month) use ($tickets) {
            $created = $tickets->filter(fn (Ticket $t) => $t->created_at->isSameMonth($month) && $t->created_at->isSameYear($month));
            $resolved = $tickets->filter(fn (Ticket $t) => $t->resolved_at && $t->resolved_at->isSameMonth($month) && $t->resolved_at->isSameYear($month));

            return [
                'month' => $month->format('M'),
                'created' => $created->count(),
                'resolved' => $resolved->count(),
            ];
        })->values()->all();
    }

    private function initials(string $name): string
    {
        $parts = explode(' ', trim($name));

        return strtoupper(substr($parts[0] ?? '', 0, 1).substr($parts[1] ?? '', 0, 1));
    }

    /**
     * SLA warning/breach alerts have no cron job pushing them — they're
     * synced lazily here, every time the requester loads a page that
     * already fetched their tickets, then read back from the real table.
     */
    private function notifications(User $requester, Collection $tickets): array
    {
        NotificationService::syncSlaAlerts($tickets, $requester);

        return NotificationService::present($requester);
    }
}
