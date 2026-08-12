<?php

namespace App\Http\Controllers;

use App\Models\AuditTrail;
use App\Models\Role;
use App\Models\ServiceCatalogSubject;
use App\Models\SlaPolicy;
use App\Models\Ticket;
use App\Models\User;
use App\Support\DummyData;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AdminController extends Controller
{
    public function dashboard(): View
    {
        $tickets = Ticket::all();

        return view('admin.dashboard', [
            'role' => 'admin',
            'currentUser' => DummyData::currentAdmin(),
            'auditTrail' => AuditTrail::with('actor')->latest('created_at')->take(4)->get()
                ->map(fn ($a) => [
                    'waktu' => $a->created_at->format('d M Y, H:i'),
                    'pengguna' => $a->actor->name,
                    'aktivitas' => $a->description,
                    'modul' => match ($a->module) {
                        'service_catalog' => 'Service Catalog',
                        'sla_configuration' => 'Konfigurasi SLA',
                        'user_role_management' => 'User & Role Management',
                        default => $a->module,
                    },
                ]),
            'auditLogToday' => AuditTrail::whereDate('created_at', today())->count(),
            'slaPolicyActiveCount' => SlaPolicy::active()->count(),
            'totalTicketCount' => $tickets->count(),
            'categoryDistribution' => $this->categoryDistribution($tickets),
            'slaStatus' => $this->slaStatusBreakdown($tickets),
            'slaTrend' => $this->slaTrendByPriority($tickets),
            'avgResolution' => $this->avgResolutionByCategory($tickets),
            'ticketTrend' => $this->ticketTrendByCategory($tickets),
            'topServiceCatalog' => $this->topServiceCatalog($tickets, truncate: true),
            'slaTrendInsight' => $this->slaTrendInsight($tickets),
            'ticketTrendInsight' => $this->ticketTrendInsight($tickets),
            'topServiceInsight' => $this->topServiceInsight($this->topServiceCatalog($tickets, truncate: false)),
            'totalUsers' => User::count(),
            'activeRoles' => Role::where('status', 'active')->count(),
            'serviceCatalogCount' => ServiceCatalogSubject::where('is_active', true)->count(),
        ]);
    }

    public function placeholder(string $title): View
    {
        return view('admin.placeholder', [
            'role' => 'admin',
            'currentUser' => DummyData::currentAdmin(),
            'title' => $title,
        ]);
    }

    /**
     * `value` is a percentage (0-100) — TicketCategoryDonut's legend
     * appends "%" to it directly, and the pie slices need proportional
     * weights either way, so counts are normalized here rather than in JS.
     */
    private function categoryDistribution(Collection $tickets): array
    {
        $colors = ['Incident' => '#2563eb', 'Service Request' => '#f59e0b', 'Access Request' => '#10b981'];
        $total = max($tickets->count(), 1);

        return collect($colors)->map(fn ($color, $label) => [
            'label' => $label,
            'value' => (int) round($tickets->where('issue_category', $label)->count() / $total * 100),
            'color' => $color,
        ])->values()->all();
    }

    /**
     * Same "active tickets with a computable SLA state" scope Requester's
     * own SLA donut uses (DashboardController@requester) — Draft/Waiting for
     * Approval/Rejected never have a real countdown, so they're excluded
     * rather than silently counted as "on track".
     */
    private function slaStatusBreakdown(Collection $tickets): array
    {
        $active = $tickets->whereIn('status', Ticket::ACTIVE_STATUSES)
            ->filter(fn (Ticket $t) => $t->sla_minutes_remaining !== null);

        $onTrack = $active->filter(fn (Ticket $t) => $t->sla_kind === 'ontrack')->count();
        $warning = $active->filter(fn (Ticket $t) => $t->sla_kind === 'warning')->count();
        $breach = $active->filter(fn (Ticket $t) => $t->sla_kind === 'breach')->count();
        $total = max($onTrack + $warning + $breach, 1);

        return [
            ['label' => 'Within SLA', 'percent' => (int) round($onTrack / $total * 100), 'color' => '#10b981'],
            ['label' => 'Warning', 'percent' => (int) round($warning / $total * 100), 'color' => '#f59e0b'],
            ['label' => 'Breach', 'percent' => (int) round($breach / $total * 100), 'color' => '#ef4444'],
        ];
    }

    /**
     * A ticket "breaches" once its resolution_due_at has passed while still
     * unresolved, or — for the (currently nonexistent) Resolved/Closed rows —
     * if it was resolved after that deadline. Draft/Waiting for
     * Approval/Rejected never had an active countdown, so they can't breach.
     */
    private function isBreached(Ticket $t): bool
    {
        if (in_array($t->status, Ticket::DONE_STATUSES, true)) {
            return $t->resolved_at && $t->resolved_at->greaterThan($t->resolution_due_at);
        }

        if (in_array($t->status, Ticket::NO_SLA_STATUSES, true)) {
            return false;
        }

        return $t->sla_kind === 'breach';
    }

    private function lastSixMonths(): array
    {
        // startOfMonth() first — see DashboardController::createdVsResolvedByMonth()
        // for why subtracting months from a late-month day can overflow into
        // the wrong target month and repeat/skip a label.
        return collect(range(5, 0))->map(fn (int $m) => Carbon::now()->startOfMonth()->subMonths($m))->all();
    }

    private function slaTrendByPriority(Collection $tickets): array
    {
        $priorities = ['Critical', 'High', 'Medium', 'Low'];

        return collect($this->lastSixMonths())->map(function (Carbon $month) use ($tickets, $priorities) {
            $inMonth = $tickets->filter(fn (Ticket $t) => $t->created_at->isSameMonth($month) && $t->created_at->isSameYear($month));

            $row = ['month' => $month->translatedFormat('M')];
            foreach ($priorities as $priority) {
                $row[$priority] = $inMonth->where('priority', $priority)->filter(fn (Ticket $t) => $this->isBreached($t))->count();
            }

            return $row;
        })->values()->all();
    }

    private function ticketTrendByCategory(Collection $tickets): array
    {
        $categories = ['Incident', 'Service Request', 'Access Request'];

        return collect($this->lastSixMonths())->map(function (Carbon $month) use ($tickets, $categories) {
            $inMonth = $tickets->filter(fn (Ticket $t) => $t->created_at->isSameMonth($month) && $t->created_at->isSameYear($month));

            $row = ['month' => $month->translatedFormat('M')];
            foreach ($categories as $category) {
                $row[$category] = $inMonth->where('issue_category', $category)->count();
            }

            return $row;
        })->values()->all();
    }

    /**
     * Only tickets that have actually been resolved contribute — with no
     * real Support workflow yet, every category legitimately averages 0
     * until the first ticket is resolved.
     */
    private function avgResolutionByCategory(Collection $tickets): array
    {
        $categories = ['Incident', 'Service Request', 'Access Request'];

        return collect($categories)->map(function (string $category) use ($tickets) {
            $resolved = $tickets->where('issue_category', $category)->filter(fn (Ticket $t) => $t->resolved_at !== null);
            $avgHours = $resolved->isNotEmpty()
                ? $resolved->avg(fn (Ticket $t) => $t->created_at->diffInMinutes($t->resolved_at) / 60)
                : 0;

            return ['label' => $category, 'hours' => round($avgHours, 1)];
        })->values()->all();
    }

    private function topServiceCatalog(Collection $tickets, bool $truncate): array
    {
        return $tickets->whereNotNull('subject_name')
            ->countBy('subject_name')
            ->sortDesc()
            ->take(5)
            ->map(fn (int $total, string $label) => ['label' => $truncate ? Str::limit($label, 18) : $label, 'total' => $total])
            ->values()
            ->all();
    }

    private function slaTrendInsight(Collection $tickets): string
    {
        $priorities = ['Critical', 'High', 'Medium', 'Low'];
        $now = Carbon::now();
        $thisMonth = $tickets->filter(fn (Ticket $t) => $t->created_at->isSameMonth($now) && $t->created_at->isSameYear($now));

        $counts = collect($priorities)->mapWithKeys(
            fn ($p) => [$p => $thisMonth->where('priority', $p)->filter(fn (Ticket $t) => $this->isBreached($t))->count()]
        );

        $top = $counts->sortDesc()->keys()->first();

        if ($counts->sum() === 0) {
            return 'Belum ada pelanggaran SLA yang tercatat bulan ini.';
        }

        return "Prioritas {$top} memiliki pelanggaran SLA terbanyak bulan ini ({$counts[$top]} tiket). Administrator perlu meninjau SLA policy, routing tiket, dan kapasitas support.";
    }

    private function ticketTrendInsight(Collection $tickets): string
    {
        $categories = ['Incident', 'Service Request', 'Access Request'];
        $now = Carbon::now();
        $thisMonth = $tickets->filter(fn (Ticket $t) => $t->created_at->isSameMonth($now) && $t->created_at->isSameYear($now));

        $counts = collect($categories)->mapWithKeys(fn ($c) => [$c => $thisMonth->where('issue_category', $c)->count()]);
        $top = $counts->sortDesc()->keys()->first();

        if ($counts->sum() === 0) {
            return 'Belum ada tiket yang tercatat bulan ini.';
        }

        return "{$top} menjadi kategori dengan tiket terbanyak bulan ini ({$counts[$top]} tiket).";
    }

    private function topServiceInsight(array $topServiceCatalog): string
    {
        if ($topServiceCatalog === []) {
            return 'Belum ada data pemakaian layanan.';
        }

        $names = collect($topServiceCatalog)->take(2)->pluck('label');

        return $names->count() > 1
            ? "<strong>{$names[0]}</strong> dan <strong>{$names[1]}</strong> menjadi layanan dengan volume tertinggi. Administrator dapat mempertimbangkan peningkatan FAQ, troubleshooting guide, dan automation untuk mengurangi tiket berulang."
            : "<strong>{$names[0]}</strong> menjadi layanan dengan volume tertinggi. Administrator dapat mempertimbangkan peningkatan FAQ, troubleshooting guide, dan automation untuk mengurangi tiket berulang.";
    }
}
