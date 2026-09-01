<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\User;
use App\Support\CurrentActor;
use App\Support\DashboardPeriod;
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
            ->where('created_at', '>=', Carbon::now()->subMonthsNoOverflow(6));

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
            /*
             | Ringkasan per periode — Minggu / Bulan / Tahun, sama seperti
             | dashboard Support dan Support BPO.
             |
             | Ketiganya dihitung SEKALIGUS di sini, bukan diambil ulang lewat
             | AJAX tiap kali tab ditekan. Datanya sudah ada di memori (tiket
             | requester ini sudah dimuat di atas), jadi menghitung tiga rentang
             | lebih murah daripada satu permintaan HTTP tambahan — dan tabnya
             | berpindah tanpa jeda.
             */
            'periods' => $this->periodSummaries($tickets),
            'slaDonut' => [
                'total' => $onTrack + $warning + $breach,
                'onTrack' => $onTrack,
                'warning' => $warning,
                'breach' => $breach,
                'pctWithinSla' => (int) round($onTrack / $slaTotal * 100),
            ],
            'slaRows' => $slaRows,
            /*
             | Draf titipan EVA, kalau memang ada. `pull` — dibaca SEKALIGUS
             | dibuang: tanpa itu form akan membuka diri sendiri dan terisi lagi
             | setiap kali karyawan kembali ke dashboard, termasuk berhari-hari
             | setelah percakapannya selesai.
             */
            'evaDraft' => session()->pull('eva.ticket_draft'),
        ]);
    }

    public function myTickets(): View
    {
        $requester = CurrentActor::requester();
        $tickets = Ticket::where('requester_id', $requester->id)->latest('created_at')->get();

        $rows = $tickets->map(fn (Ticket $t) => $this->presentRow($t))->values();

        $counts = [
            'All' => $tickets->count(),
            'Draft' => $tickets->whereIn('status', ['Draft', 'Returned'])->count(),
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

    public function eva(): View
    {
        $eva = CurrentActor::knowledgeAdmin();

        return view('dashboard.eva', [
            'role' => 'eva',
            'currentUser' => ['name' => $eva->name, 'title' => $eva->jabatan, 'initials' => $this->initials($eva->name)],
            'profileUrl' => route('eva.profile'),
            'articles' => DummyData::knowledgeArticles(),
            'unanswered' => DummyData::unansweredQuestions(),
            'notifications' => DummyData::notifications(),
        ]);
    }

    private function presentRow(Ticket $t): array
    {
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
            'slaPct' => $t->sla_elapsed_percent,
            'autoClose' => $t->autoClosePayload(),
            'created' => $t->created_at->translatedFormat('j M Y'),
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

    /**
     * Grafik "Dibuat vs Selesai" dan donat SLA untuk tiap rentang ringkasan.
     *
     * @return array<string,array{chart:array<int,array<string,mixed>>,slaDonut:array<string,int>}>
     */
    private function periodSummaries(Collection $tickets): array
    {
        return collect(DashboardPeriod::KEYS)
            ->mapWithKeys(fn (string $period) => [$period => [
                'chart' => $this->createdVsResolvedForPeriod($tickets, $period),
                'slaDonut' => $this->slaDonutFor(
                    $tickets->filter(fn (Ticket $t) => $t->created_at->greaterThanOrEqualTo(DashboardPeriod::cutoff($period))),
                ),
            ]])
            ->all();
    }

    /** @return array<int,array{month:string,created:int,resolved:int}> */
    private function createdVsResolvedForPeriod(Collection $tickets, string $period): array
    {
        return DashboardPeriod::buckets($period)->map(function (array $bucket) use ($tickets) {
            $created = $tickets->filter(fn (Ticket $t) => $t->created_at->between($bucket['start'], $bucket['end']));
            $resolved = $tickets->filter(fn (Ticket $t) => $t->resolved_at && $t->resolved_at->between($bucket['start'], $bucket['end']));

            return [
                // Kuncinya tetap 'month' walau isinya bisa hari atau minggu —
                // CreatedVsResolvedChart membaca kunci itu untuk sumbu X, dan
                // menggantinya berarti menyentuh grafik yang sudah bekerja.
                'month' => $bucket['label'],
                'created' => $created->count(),
                'resolved' => $resolved->count(),
            ];
        })->values()->all();
    }

    /**
     * Donat SLA dari sekumpulan tiket. Dipisahkan dari aksi requester() supaya
     * angka global dan angka per-periode dihitung oleh kode yang sama persis.
     *
     * @return array<string,int>
     */
    private function slaDonutFor(Collection $tickets): array
    {
        $active = $tickets->whereIn('status', Ticket::ACTIVE_STATUSES)
            ->filter(fn (Ticket $t) => $t->sla_minutes_remaining !== null);

        $onTrack = $active->filter(fn (Ticket $t) => $t->sla_kind === 'ontrack')->count();
        $warning = $active->filter(fn (Ticket $t) => $t->sla_kind === 'warning')->count();
        $breach = $active->filter(fn (Ticket $t) => $t->sla_kind === 'breach')->count();

        return [
            'total' => $onTrack + $warning + $breach,
            'onTrack' => $onTrack,
            'warning' => $warning,
            'breach' => $breach,
            'pctWithinSla' => (int) round($onTrack / max($onTrack + $warning + $breach, 1) * 100),
        ];
    }

    private function createdVsResolvedByMonth(Collection $tickets): array
    {
        // startOfMonth() first — subtracting months from day 29-31 of the
        // current month can overflow into the wrong target month (e.g. today
        // the 30th, minus 5 months, lands on a nonexistent Feb 30 and rolls
        // forward into March), producing a repeated month label and silently
        // dropping a real month from the chart.
        $months = collect(range(5, 0))->map(fn (int $m) => Carbon::now()->startOfMonth()->subMonths($m));

        return $months->map(function (Carbon $month) use ($tickets) {
            $created = $tickets->filter(fn (Ticket $t) => $t->created_at->isSameMonth($month) && $t->created_at->isSameYear($month));
            $resolved = $tickets->filter(fn (Ticket $t) => $t->resolved_at && $t->resolved_at->isSameMonth($month) && $t->resolved_at->isSameYear($month));

            return [
                'month' => $month->translatedFormat('M'),
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
        NotificationService::syncSlaAlerts($tickets, $requester, 'requester');

        return NotificationService::present($requester, 'requester');
    }
}
