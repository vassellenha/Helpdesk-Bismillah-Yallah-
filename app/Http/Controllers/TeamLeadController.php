<?php

namespace App\Http\Controllers;

use App\Models\AuditTrail;
use App\Models\ServiceCatalogSubject;
use App\Models\SupportAgent;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\TicketNotification;
use App\Models\User;
use App\Support\CurrentActor;
use App\Support\Export\XlsxWriter;
use App\Support\NotificationService;
use App\Support\PriorityRegistry;
use App\Support\Reports\TeamLeadReport;
use App\Support\TeguranNotifier;
use App\Support\TicketFlow;
use App\Support\TicketTimeline;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Team Lead dashboard — a supervisor's view over the whole support operation.
 * Every number here is computed live from the real tickets/support_agents
 * tables (no DummyData), and the three corrective actions (SLA teguran,
 * reassign, raise priority) are Team-Lead-only endpoints that never touch the
 * Requester/Support/Approver controllers.
 *
 * Scope note: this Team Lead supervises the Support IT team (TEAM_SCOPE).
 * A ticket belongs to the scope when its current PIC is an IT agent — BPO
 * tickets enter the scope the moment BPO escalates them, because escalation
 * reassigns the ticket to the catalog subject's IT agent.
 */
class TeamLeadController extends Controller
{
    /** Support-agent `type` this Team Lead supervises (support_agents.type). */
    private const TEAM_SCOPE = 'it';

    /** Below this average, an agent's satisfaction rating is eligible for a Team-Lead teguran. */
    private const RATING_TEGURAN_THRESHOLD = 4.0;

    private const I_TICKET = 'M4 7a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v3a2 2 0 0 0 0 4v3a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-3a2 2 0 0 0 0-4Z M14 5v14';

    private const I_INBOX = 'M4 13h5l2 3h2l2-3h5 M5 13 6.5 5.6A2 2 0 0 1 8.5 4h7a2 2 0 0 1 2 1.6L19 13v5a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2Z';

    private const I_USER = 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2 M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z';

    private const I_CLOCK = 'M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18Z M12 7v5l3 3';

    private const I_CHECK = 'M9 12l2 2 4-5 M21 12a9 9 0 1 1-9-9';

    /**
     * Active agents in this Team Lead's team.
     *
     * @return Builder
     */
    private function scopedAgentQuery()
    {
        return SupportAgent::where('is_active', true)->where('type', self::TEAM_SCOPE);
    }

    /**
     * Constrains a Ticket query to tickets whose current PIC is in the team,
     * PLUS tickets currently broadcast to the whole IT team and not yet
     * claimed by anyone (assigned_agent_id null, escalated_at set — see
     * App\Support\TicketBroadcast::escalateBroadcast()). Without the second
     * clause, a "Lainnya" ticket BPO just escalated sits with no PIC at all
     * until some IT agent claims it, and whereHas('assignedAgent', ...)
     * silently drops it from every Team Lead view (dashboard, ticket list,
     * SLA monitoring, exports, audit feed) for that whole window — even
     * though it IS already this team's ticket to answer.
     */
    private function scopeTickets($query)
    {
        return $query->where(function ($q) {
            $q->whereHas('assignedAgent', fn ($q2) => $q2->where('type', self::TEAM_SCOPE))
                ->orWhere(function ($q2) {
                    $q2->whereNull('assigned_agent_id')
                        ->whereNull('catalog_subject_id')
                        ->whereNotNull('escalated_at');
                });
        });
    }

    public function dashboard(Request $request): View
    {
        return view('dashboard.team-lead', $this->dashboardData($request));
    }

    /**
     * JSON feed of the exact same dashboard payload. Lets the workspace
     * refetch after a corrective action (raise priority, reassign, teguran)
     * and update every panel + the Riwayat feed in place, so nothing needs a
     * full page reload to reflect the change.
     */
    public function dataFeed(Request $request): JsonResponse
    {
        return response()->json($this->dashboardData($request));
    }

    /** @return array<string,mixed> */
    private function dashboardData(Request $request): array
    {
        $lead = CurrentActor::teamLead();

        $period = in_array($request->query('period'), ['today', '7d', '30d', 'quarter'], true)
            ? $request->query('period') : '30d';
        $since = match ($period) {
            'today' => Carbon::today(),
            '7d' => Carbon::now()->subDays(7),
            'quarter' => Carbon::now()->subMonthsNoOverflow(3),
            default => Carbon::now()->subDays(30),
        };

        $tickets = $this->scopeTickets(Ticket::with(['assignedAgent', 'requester:id,unit'])
            ->where('created_at', '>=', $since))
            ->get();
        $agents = $this->scopedAgentQuery()->with('user')->get();

        $active = $tickets->whereIn('status', Ticket::ACTIVE_STATUSES);
        $withSla = $active->filter(fn (Ticket $t) => $t->sla_minutes_remaining !== null);

        $breach = $withSla->filter(fn (Ticket $t) => $t->sla_kind === 'breach');
        $warning = $withSla->filter(fn (Ticket $t) => $t->sla_kind === 'warning');
        $within = $withSla->filter(fn (Ticket $t) => $t->sla_kind === 'ontrack');
        $slaTotal = max($withSla->count(), 1);

        return [
            'role' => 'team-lead',
            'period' => $period,
            'escalateUrl' => route('team-lead.escalation.raise'),
            'currentUser' => $this->currentUserPayload($lead),
            'notifications' => $this->teamLeadNotifications($tickets, $lead),
            'metrics' => [
                'activeTickets' => $active->count(),
                'slaWarning' => $warning->count(),
                'slaBreach' => $breach->count(),
                'agents' => $agents->count(),
            ],
            'slaDonut' => [
                'total' => $withSla->count(),
                'onTrack' => $within->count(),
                'warning' => $warning->count(),
                'breach' => $breach->count(),
                'pctWithinSla' => (int) round($within->count() / $slaTotal * 100),
            ],
            'slaByPriority' => $this->slaByPriority($withSla),
            'slaTopSubjects' => $this->slaTopSubjects($tickets),
            'categoryBreakdown' => $this->categoryBreakdown($tickets),
            'workload' => $this->workload($agents, $tickets),
            'slaWarnings' => $withSla
                ->filter(fn (Ticket $t) => in_array($t->sla_kind, ['warning', 'breach'], true))
                ->sortBy('sla_minutes_remaining')
                ->map(fn (Ticket $t) => $this->presentWarningRow($t))
                ->values(),
            'escalations' => $this->escalations(),
            'breachEscalations' => $breach
                ->sortBy('sla_minutes_remaining')
                ->map(fn (Ticket $t) => $this->presentBreachRow($t))
                ->values(),
            'agentOptions' => $this->agentOptions($agents, $tickets),
            'reminderLog' => TeguranNotifier::recent()->map(fn (TicketNotification $n) => [
                'id' => $n->id,
                'ticket' => $n->ticket?->ticket_no ?? '—',
                'subject' => $n->ticket?->title ?? '—',
                'agent' => $n->user?->name ?? '—',
                'message' => $n->message,
                'at' => $n->created_at->format('d M Y · H:i'),
                'unread' => $n->read_at === null,
            ])->values(),
            'monitorRows' => $withSla
                ->sortByDesc('created_at')
                ->map(fn (Ticket $t) => $this->presentWarningRow($t))
                ->values(),
            'monitorFilters' => $this->monitorFilters($tickets),
            'opStats' => $this->opStats($tickets),
            'appTrend' => $this->appTrend($tickets),
            'escalationRecs' => $this->escalationRecs($active),
            'categoryTree' => $this->categoryTree($tickets),
            'ticketTrend' => $this->ticketTrend($tickets),
            'topApps' => $this->topApps($tickets),
            'topIssues' => $this->topIssues($tickets),
            'servicePerformance' => $this->servicePerformance($tickets),
            'supportStats' => $this->supportStats($tickets, $agents),
            'picRows' => $this->picRows(),
            'teguranTickets' => $this->teguranTickets($tickets),
            'auditRows' => $this->auditRows(),
            'reportUnits' => $this->reportUnits(),
            'reportTypes' => TeamLeadReport::TYPES,
            'reportDefaults' => $this->reportDefaults(),
            'reportPreviewUrl' => route('team-lead.reports.preview'),
            'reportExportUrl' => route('team-lead.reports.export'),
            'remindUrlBase' => url('/team-lead/tickets'),
            'remindRatingUrlBase' => url('/team-lead/agents'),
            'ratingTeguranThreshold' => self::RATING_TEGURAN_THRESHOLD,
            'markAllReadUrl' => route('team-lead.notifications.read-all'),
            'dashboardDataUrl' => route('team-lead.data-feed'),
        ];
    }

    /**
     * Read-only supervisor view of any ticket — the Team Lead isn't the
     * requester/approver/support owner, so this bypasses those ownership
     * guards while still exposing the Team-Lead corrective actions.
     */
    public function showTicket(Ticket $ticket): View
    {
        $this->assertInScope($ticket);

        return view('dashboard.team-lead-ticket', [
            'role' => 'team-lead',
            ...$this->ticketDetailPayload($ticket),
            'dashboardUrl' => route('dashboard.team-lead'),
        ]);
    }

    /** JSON detail for the 1/3-screen slide-over opened from the dashboard. */
    public function ticketData(Ticket $ticket): JsonResponse
    {
        $this->assertInScope($ticket);

        return response()->json($this->ticketDetailPayload($ticket));
    }

    /**
     * 403 when a ticket is handled by another team, or isn't (yet) this
     * team's to see (direct-URL access) — same definition as scopeTickets()
     * so a broadcast-to-IT ticket that's still unclaimed is viewable here
     * too, not just excluded-then-permitted-by-accident.
     */
    private function assertInScope(Ticket $ticket): void
    {
        $isTeamAgent = $ticket->assignedAgent && $ticket->assignedAgent->type === self::TEAM_SCOPE;
        $isUnclaimedItBroadcast = $ticket->assigned_agent_id === null
            && $ticket->catalog_subject_id === null
            && $ticket->escalated_at !== null;

        abort_unless(
            $isTeamAgent || $isUnclaimedItBroadcast,
            403,
            'Tiket ini ditangani tim lain, di luar cakupan Team Lead.',
        );
    }

    /**
     * Pratinjau laporan di layar — persis penyaring yang dipakai unduhan.
     *
     * SEBELUMNYA tabel pratinjau dibangun sekali dari jendela periode dashboard
     * (30 hari terakhir) dan dikirim sebagai prop, jadi mengubah tanggal atau
     * unit di panel generator tidak mengubah apa pun di layar — padahal berkas
     * yang terunduh memakai tanggal itu. Pratinjau dan unduhan bisa
     * menampilkan angka yang berbeda tanpa ada yang salah ketik.
     */
    public function previewReport(Request $request): JsonResponse
    {
        CurrentActor::teamLead();

        $filters = $request->validate($this->reportRules());
        $composed = $this->composeReport($filters);

        return response()->json([
            'type' => $filters['type'],
            'title' => $composed['report']['title'],
            'columns' => $composed['report']['columns'],
            'rows' => $composed['report']['rows'],
            'periodLabel' => $composed['periodLabel'],
            'unitLabel' => $composed['unitLabel'],
        ]);
    }

    /**
     * Mengunduh laporan sebagai berkas sungguhan: .xlsx (workbook Excel asli)
     * atau PDF hasil dompdf, tersaring periode + unit. Pembangun barisnya sama
     * dengan previewReport() di atas sehingga isinya selalu sama.
     */
    public function exportReport(Request $request)
    {
        CurrentActor::teamLead();

        $filters = $request->validate([
            ...$this->reportRules(),
            'format' => 'required|in:pdf,excel',
        ]);

        $composed = $this->composeReport($filters);
        $report = $composed['report'];
        $base = 'laporan-'.$filters['type'].'-'.now()->format('Ymd-Hi');

        if ($filters['format'] === 'excel') {
            $binary = XlsxWriter::sheet(
                $report['title'],
                array_map(fn (array $c) => $c['label'], $report['columns']),
                $report['rows'],
            );

            return response($binary, 200, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="'.$base.'.xlsx"',
                'Content-Length' => (string) strlen($binary),
            ]);
        }

        // Lima kolom di A4 tegak membuat kolom terakhir terpotong; laporan
        // selebar itu dicetak mendatar.
        $paper = count($report['columns']) > 4 ? 'landscape' : 'portrait';

        return Pdf::loadView('reports.team-lead', [
            'report' => $report,
            'periodLabel' => $composed['periodLabel'],
            'unitLabel' => $composed['unitLabel'],
            'generatedAt' => now()->format('d M Y · H:i'),
        ])->setPaper('a4', $paper)->download($base.'.pdf');
    }

    /**
     * Aturan validasi penyaring laporan, dipakai pratinjau maupun unduhan.
     *
     * `to` wajib tidak mendahului `from`: rentang terbalik dulu diterima
     * diam-diam dan selalu menghasilkan laporan kosong, yang di layar tidak
     * bisa dibedakan dari "memang tidak ada tiket".
     *
     * @return array<string,mixed>
     */
    private function reportRules(): array
    {
        return [
            'type' => ['required', Rule::in(array_keys(TeamLeadReport::TYPES))],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'unit' => ['nullable', 'string', 'max:150'],
        ];
    }

    /**
     * @param  array<string,mixed>  $filters
     * @return array{report:array{title:string,columns:array<int,array{label:string,align:string}>,rows:array<int,array<int,string>>},periodLabel:string,unitLabel:string}
     */
    private function composeReport(array $filters): array
    {
        $noUnitFilter = $this->isAllUnits($filters['unit'] ?? null);
        $tickets = $this->reportTickets($filters, $noUnitFilter);
        $agents = $this->scopedAgentQuery()->get();

        return [
            'report' => TeamLeadReport::build($filters['type'], $tickets, $this->agentOptions($agents, $tickets)),
            'periodLabel' => $this->periodLabel($filters['from'] ?? null, $filters['to'] ?? null),
            'unitLabel' => $noUnitFilter ? __('teamlead.reporting.all_unit') : $filters['unit'],
        ];
    }

    /**
     * @param  array<string,mixed>  $filters
     * @return Collection<int,Ticket>
     */
    private function reportTickets(array $filters, bool $noUnitFilter): Collection
    {
        $query = $this->scopeTickets(Ticket::with(['assignedAgent', 'requester:id,unit']));

        if (! empty($filters['from'])) {
            $query->whereDate('created_at', '>=', $filters['from']);
        }
        if (! empty($filters['to'])) {
            $query->whereDate('created_at', '<=', $filters['to']);
        }
        if (! $noUnitFilter) {
            $query->whereHas('requester', fn ($q) => $q->where('unit', $filters['unit']));
        }

        return $query->get();
    }

    /**
     * ALL_UNITS adalah sentinel bebas-bahasa yang dikirim UI. Label Indonesia
     * lamanya tetap diterima supaya URL ekspor yang sudah di-bookmark masih
     * jalan; label terjemahan tidak boleh terbaca sebagai nama unit sungguhan.
     */
    private function isAllUnits(?string $unit): bool
    {
        return in_array($unit, [null, '', self::ALL_UNITS, 'Semua Unit'], true);
    }

    private function periodLabel(?string $from, ?string $to): string
    {
        return trim((empty($from) ? '' : $from).(empty($to) ? '' : ' s/d '.$to))
            ?: __('teamlead.reporting.all_period');
    }

    /**
     * Rentang awal panel generator: awal bulan berjalan sampai hari ini.
     * Sebelumnya dua tanggal ini ditulis mati di JSX ('2026-07-01' s/d
     * '2026-07-31'), jadi setiap bulan berikutnya membuka layar ini dengan
     * periode yang sudah lewat.
     *
     * @return array{from:string,to:string}
     */
    private function reportDefaults(): array
    {
        return [
            'from' => Carbon::now()->startOfMonth()->toDateString(),
            'to' => Carbon::today()->toDateString(),
        ];
    }

    /** Team-Lead internal note on a ticket (persists to the shared comments). */
    public function addNote(Request $request, Ticket $ticket): JsonResponse
    {
        $this->assertInScope($ticket);

        $lead = CurrentActor::teamLead();
        $data = $request->validate(['message' => 'required|string|max:3000']);

        $comment = TicketComment::create([
            'ticket_id' => $ticket->id,
            'author_name' => $lead->name,
            'author_role' => 'Team Lead',
            'message' => $data['message'],
        ]);

        NotificationService::notifyDiscussionParticipants($ticket, $lead, 'Team Lead', $data['message']);

        return response()->json([
            'id' => $comment->id,
            'authorName' => $comment->author_name,
            'authorRole' => $comment->author_role,
            'message' => $comment->message,
            'at' => $comment->created_at->format('d M · H:i'),
        ], 201);
    }

    private function ticketDetailPayload(Ticket $ticket): array
    {
        $ticket->load(['requester', 'approver', 'assignedAgent', 'comments']);
        $agents = $this->scopedAgentQuery()->get();

        return [
            'ticket' => [
                'id' => $ticket->ticket_no,
                'subject' => $ticket->subject_name ?? $ticket->title,
                'title' => $ticket->title,
                'status' => $ticket->status,
                'priority' => $ticket->priority,
                'type' => $ticket->issue_category ?? $ticket->category ?? '—',
                'service' => $ticket->service_name ?? '—',
                'subcategory' => $ticket->subcategory_name ?? '—',
                'description' => $ticket->description,
                'createdAt' => $ticket->created_at->format('d M Y · H:i'),
                'sla' => $ticket->sla_label,
                'slaKind' => $ticket->sla_kind,
                'slaPanel' => $ticket->slaPayload(),
                'resolutionDue' => optional($ticket->resolution_due_at)->format('d M Y · H:i'),
                'satisfactionRating' => $ticket->satisfaction_rating,
                'feedbackNote' => $ticket->feedback_note,
                'ratingActive' => (bool) $ticket->rating_active,
                'agent' => $ticket->assignedAgent?->name ?? 'Belum ada PIC',
                'agentId' => $ticket->assigned_agent_id,
                'requester' => $ticket->requester ? [
                    'name' => $ticket->requester->name,
                    'unit' => $ticket->requester->unit,
                    'email' => $ticket->requester->email,
                ] : null,
            ],
            'approvalFlow' => $this->approvalFlow($ticket),
            'timeline' => TicketTimeline::steps($ticket),
            'flow' => TicketFlow::stages($ticket),
            'comments' => $ticket->comments->map(fn (TicketComment $c) => [
                'id' => $c->id,
                'authorName' => $c->author_name,
                'authorRole' => $c->author_role,
                'message' => $c->message,
                'at' => $c->created_at->format('d M · H:i'),
            ])->values(),
            'agentOptions' => $this->agentOptions($agents, Ticket::all()),
            'remindUrlBase' => url('/team-lead/tickets'),
        ];
    }

    /**
     * Approval chain implied by the ticket type (Incident has none; Service
     * needs a Manager; Access needs Manager → BPO), with each step resolved to
     * where the ticket actually is right now.
     *
     * Status is the authority, not the approval records: a Closed ticket is
     * finished whether or not TicketApproval rows exist for it (seeded and
     * imported tickets often have none). The records only enrich a step with
     * who decided it and when. The previous version derived the active step
     * from `status === 'Waiting for Approval'` alone, so every other status —
     * including Draft, Resolved and Closed — rendered "IT Support" as still
     * in progress.
     */
    private function approvalFlow(Ticket $ticket): array
    {
        $chain = match ($ticket->issue_category) {
            'Access Request' => [
                [__('teamlead.flow.manager'), __('teamlead.flow.l1')],
                [__('teamlead.flow.bpo_owner'), __('teamlead.flow.l2')],
                [__('teamlead.flow.it_support'), __('teamlead.flow.handling')],
            ],
            'Service Request' => [
                [__('teamlead.flow.manager'), __('teamlead.flow.l1')],
                [__('teamlead.flow.it_support'), __('teamlead.flow.handling')],
            ],
            default => [
                [__('teamlead.flow.no_approval'), __('teamlead.flow.incident')],
                [__('teamlead.flow.it_support'), __('teamlead.flow.handling')],
            ],
        };

        $handlingIdx = count($chain) - 1;
        $decisions = $ticket->approvals()->with('approver:id,name')->get();
        $approved = $decisions->where('decision', 'approved')->values();
        $blocker = $decisions->whereIn('decision', ['rejected', 'revision_requested'])->last();

        $finished = in_array($ticket->status, Ticket::DONE_STATUSES, true);
        $withSupport = in_array($ticket->status, ['Open', 'Assigned', 'In Progress', 'Waiting for Response'], true);
        $stopIdx = $approved->count();

        $stateFor = function (int $i) use ($ticket, $handlingIdx, $stopIdx, $finished, $withSupport): string {
            if ($i === $handlingIdx) {
                return $finished ? 'done' : ($withSupport ? 'current' : 'pending');
            }

            // Reaching Support at all means every approval step above it passed.
            if ($finished || $withSupport) {
                return 'done';
            }

            return match (true) {
                $i < $stopIdx => 'done',
                $i > $stopIdx => 'pending',
                $ticket->status === 'Rejected' => 'rejected',
                $ticket->status === 'Returned' => 'returned',
                $ticket->status === 'Waiting for Approval' => 'current',
                default => 'pending', // Draft — never submitted
            };
        };

        $steps = collect($chain)->map(function (array $c, int $i) use ($ticket, $handlingIdx, $approved, $blocker, $stateFor, $finished) {
            $state = $stateFor($i);

            if ($i === $handlingIdx) {
                // `assigned_agent_id` is frozen at creation, so naming the PIC on
                // a step the ticket has not reached would read as "already with IT".
                $by = $state === 'pending' ? null : $ticket->assignedAgent?->name;
                $at = $finished ? optional($ticket->resolved_at)->format('d M Y · H:i') : null;
            } else {
                $record = $approved[$i] ?? (in_array($state, ['rejected', 'returned'], true) ? $blocker : null);
                $by = $record?->approver?->name;
                $at = optional($record?->created_at)->format('d M Y · H:i');
            }

            return [
                'name' => $c[0],
                'sub' => $c[1],
                'state' => $state,
                'by' => $by,
                'at' => $at,
            ];
        })->all();

        $currentName = collect($steps)->firstWhere('state', 'current')['name'] ?? $chain[0][0];

        return [
            'type' => $ticket->issue_category ?? __('teamlead.flow.incident'),
            'note' => match (true) {
                $finished => __('teamlead.flow.note_done'),
                $withSupport => __('teamlead.flow.note_handling'),
                $ticket->status === 'Rejected' => __('teamlead.flow.note_rejected'),
                $ticket->status === 'Returned' => __('teamlead.flow.note_returned'),
                $ticket->status === 'Waiting for Approval' => __('teamlead.flow.note_waiting', ['step' => $currentName]),
                default => __('teamlead.flow.note_draft'),
            },
            'noteState' => match (true) {
                $finished => 'done',
                $ticket->status === 'Rejected' => 'rejected',
                $ticket->status === 'Returned' => 'returned',
                $withSupport, $ticket->status === 'Waiting for Approval' => 'current',
                default => 'pending',
            },
            'steps' => $steps,
        ];
    }

    /**
     * Support agents enriched for the reassign modal: live load + performance
     * plus the catalog subjects/sub-categories/apps each one owns, so the
     * modal can recommend the nearest PIC and filter by scope.
     */
    private function agentOptions(Collection $agents, Collection $tickets): array
    {
        $map = [];
        foreach (ServiceCatalogSubject::where('is_active', true)->with(['service:id,name', 'subcategory:id,name'])->get() as $s) {
            foreach ([$s->support_agent_id, $s->it_agent_id] as $aid) {
                if (! $aid) {
                    continue;
                }
                $map[$aid]['subjects'][] = $s->name;
                if ($s->subcategory?->name) {
                    $map[$aid]['subcats'][] = $s->subcategory->name;
                }
                if ($s->service?->name) {
                    $map[$aid]['apps'][] = $s->service->name;
                }
            }
        }

        return $agents->map(function (SupportAgent $a) use ($tickets, $map) {
            $mine = $tickets->where('assigned_agent_id', $a->id);
            $done = $mine->whereIn('status', Ticket::DONE_STATUSES)->filter(fn (Ticket $t) => $t->resolved_at !== null);
            $met = $done->filter(fn (Ticket $t) => $t->resolved_at->lessThanOrEqualTo($t->resolution_due_at))->count();
            $rated = $mine->whereNotNull('satisfaction_rating')->where('rating_active', true);
            $m = $map[$a->id] ?? [];

            return [
                'id' => $a->id,
                'name' => $a->name,
                'type' => strtoupper($a->type),
                'initials' => $this->initials($a->name),
                'load' => $mine->whereIn('status', Ticket::ACTIVE_STATUSES)->count(),
                'resolved' => $mine->whereIn('status', Ticket::DONE_STATUSES)->count(),
                'slaPct' => $done->isNotEmpty() ? (int) round($met / $done->count() * 100) : null,
                'avgResolution' => $done->isNotEmpty() ? round($done->avg(fn (Ticket $t) => $t->created_at->diffInMinutes($t->resolved_at)) / 60, 1).'h' : '—',
                'rating' => $rated->isNotEmpty() ? round($rated->avg('satisfaction_rating'), 1) : null,
                'ratingCount' => $rated->count(),
                'subjects' => array_values(array_unique($m['subjects'] ?? [])),
                'subcats' => array_values(array_unique($m['subcats'] ?? [])),
                'apps' => array_values(array_unique($m['apps'] ?? [])),
            ];
        })->sortBy('load')->values()->all();
    }

    /**
     * Sends an SLA teguran to the ticket's currently assigned support agent
     * across the chosen channels (in-app / email / WhatsApp) and logs the
     * real delivery outcome to the shared audit trail.
     */
    public function remind(Request $request, Ticket $ticket): JsonResponse
    {
        $lead = CurrentActor::teamLead();

        $data = $request->validate([
            'message' => 'required|string|max:2000',
            'channels' => 'nullable|array',
            'channels.*' => 'in:inapp,email,whatsapp',
        ]);

        abort_if(
            in_array($ticket->status, Ticket::NOT_YET_RELEASED_STATUSES, true),
            422,
            'Tiket ini belum diteruskan ke Support, jadi PIC-nya belum bisa ditegur.'
        );

        $agent = $ticket->assignedAgent;
        abort_unless($agent !== null, 422, 'Tiket ini belum punya PIC support untuk ditegur.');
        abort_unless($agent->type === self::TEAM_SCOPE, 403, 'Tiket ini ditangani tim lain, di luar cakupan Team Lead.');

        $channels = $data['channels'] ?? config('notifications.teguran_channels');
        $delivered = TeguranNotifier::send($lead, $agent, $ticket, $data['message'], $channels);

        AuditTrail::record($lead, [
            'module' => 'team_lead',
            'action' => 'remind',
            'target_type' => 'ticket',
            'target_id' => $ticket->id,
            'target_name' => $ticket->ticket_no,
            'new_value' => ['agent' => $agent->name, 'channels' => $delivered, 'catatan' => $data['message']],
            'description' => "{$lead->name} mengirim teguran SLA tiket \"{$ticket->ticket_no}\" ke {$agent->name} via ".(empty($delivered) ? 'tidak ada channel' : implode(', ', $delivered)).'.',
        ]);

        return response()->json([
            'delivered' => $delivered,
            'message' => empty($delivered)
                ? 'Teguran tercatat, tapi tidak ada channel yang berhasil (cek kontak PIC / konfigurasi).'
                : 'Teguran terkirim via '.implode(', ', $delivered).'.',
        ]);
    }

    /**
     * Sends a rating teguran to a support agent whose average satisfaction
     * rating has dropped below RATING_TEGURAN_THRESHOLD — a reprimand about
     * overall service quality, not tied to any single ticket's SLA (see
     * remind() above for that one). Same channel fan-out + audit trail.
     */
    public function remindRating(Request $request, SupportAgent $agent): JsonResponse
    {
        $lead = CurrentActor::teamLead();
        abort_unless($agent->type === self::TEAM_SCOPE, 403, 'Agent ini di luar cakupan Team Lead.');

        $data = $request->validate([
            'message' => 'required|string|max:2000',
            'channels' => 'nullable|array',
            'channels.*' => 'in:inapp,email,whatsapp',
        ]);

        $rated = Ticket::where('assigned_agent_id', $agent->id)->whereNotNull('satisfaction_rating')->where('rating_active', true)->get(['satisfaction_rating']);
        abort_if($rated->isEmpty(), 422, 'Agent ini belum punya rating dari Requester.');

        $average = round($rated->avg('satisfaction_rating'), 1);
        abort_unless($average < self::RATING_TEGURAN_THRESHOLD, 422, 'Rating agent ini sudah di atas ambang batas teguran ('.self::RATING_TEGURAN_THRESHOLD.').');

        $channels = $data['channels'] ?? config('notifications.teguran_channels');
        $delivered = TeguranNotifier::sendRating($lead, $agent, $average, $rated->count(), $data['message'], $channels);

        AuditTrail::record($lead, [
            'module' => 'team_lead',
            'action' => 'remind_rating',
            'target_type' => 'support_agent',
            'target_id' => $agent->id,
            'target_name' => $agent->name,
            'new_value' => ['rating' => $average, 'channels' => $delivered, 'catatan' => $data['message']],
            'description' => "{$lead->name} mengirim teguran rating ke {$agent->name} (rating {$average}/5 dari {$rated->count()} ulasan) via ".(empty($delivered) ? 'tidak ada channel' : implode(', ', $delivered)).'.',
        ]);

        return response()->json([
            'delivered' => $delivered,
            'message' => empty($delivered)
                ? 'Teguran tercatat, tapi tidak ada channel yang berhasil (cek kontak agent / konfigurasi).'
                : 'Teguran rating terkirim via '.implode(', ', $delivered).'.',
        ]);
    }

    /**
     * Reassigns the ticket to a different support agent. New Team-Lead-only
     * endpoint — the Support/Requester ticket logic is left untouched.
     *
     * Refuses once the ticket is finished: handing a Closed ticket to another
     * agent gives them work that no longer exists, and it would move the
     * rating already recorded against the previous PIC.
     */
    public function reassign(Request $request, Ticket $ticket): JsonResponse
    {
        $this->assertInScope($ticket);
        abort_if(
            in_array($ticket->status, Ticket::DONE_STATUSES, true),
            422,
            __('teamlead.flow.closed_no_action')
        );

        $lead = CurrentActor::teamLead();

        $data = $request->validate([
            'agent_id' => 'required|integer|exists:support_agents,id',
        ]);

        $newAgent = SupportAgent::findOrFail($data['agent_id']);
        $oldAgent = $ticket->assignedAgent;

        abort_unless($newAgent->is_active && $newAgent->type === self::TEAM_SCOPE, 422, 'Agent tujuan bukan anggota aktif tim yang diawasi Team Lead.');
        abort_if($oldAgent && $oldAgent->id === $newAgent->id, 422, 'Tiket sudah ditangani agent tersebut.');

        $ticket->update(['assigned_agent_id' => $newAgent->id]);

        if ($newAgent->user) {
            NotificationService::notify(
                $newAgent->user,
                $ticket,
                'ticket_created',
                'Tiket Dialihkan ke Anda',
                "Tiket {$ticket->ticket_no} \"{$ticket->title}\" dialihkan ke Anda oleh {$lead->name}.",
            );
        }

        AuditTrail::record($lead, [
            'module' => 'team_lead',
            'action' => 'reassign',
            'target_type' => 'ticket',
            'target_id' => $ticket->id,
            'target_name' => $ticket->ticket_no,
            'old_value' => ['agent' => $oldAgent?->name],
            'new_value' => ['agent' => $newAgent->name],
            'description' => "{$lead->name} mengalihkan tiket \"{$ticket->ticket_no}\" dari ".($oldAgent?->name ?? 'tanpa PIC')." ke {$newAgent->name}.",
        ]);

        return response()->json([
            'agent' => ['id' => $newAgent->id, 'name' => $newAgent->name, 'type' => strtoupper($newAgent->type)],
            'message' => "Tiket dialihkan ke {$newAgent->name}.",
        ]);
    }

    /**
     * Raises (or changes) the ticket priority. Team-Lead-only corrective
     * action for tickets trending toward a breach.
     */
    public function raisePriority(Request $request, Ticket $ticket): JsonResponse
    {
        $this->assertInScope($ticket);

        $lead = CurrentActor::teamLead();

        $data = $request->validate([
            'priority' => 'required|in:Critical,High,Medium,Low',
        ]);

        $old = $ticket->priority;
        abort_if($old === $data['priority'], 422, 'Prioritas tiket sudah sama.');

        $ticket->update(['priority' => $data['priority']]);

        AuditTrail::record($lead, [
            'module' => 'team_lead',
            'action' => 'raise_priority',
            'target_type' => 'ticket',
            'target_id' => $ticket->id,
            'target_name' => $ticket->ticket_no,
            'old_value' => ['priority' => $old],
            'new_value' => ['priority' => $data['priority']],
            'description' => "{$lead->name} mengubah prioritas tiket \"{$ticket->ticket_no}\" dari {$old} ke {$data['priority']}.",
        ]);

        return response()->json([
            'priority' => $data['priority'],
            'message' => "Prioritas tiket diubah ke {$data['priority']}.",
        ]);
    }

    public function markNotificationRead(TicketNotification $notification): JsonResponse
    {
        $lead = CurrentActor::teamLead();
        abort_unless($notification->user_id === $lead->id, 403);

        if ($notification->read_at === null) {
            $notification->update(['read_at' => Carbon::now()]);
        }

        return response()->json(['read' => true]);
    }

    public function markAllNotificationsRead(): JsonResponse
    {
        $lead = CurrentActor::teamLead();
        TicketNotification::where('user_id', $lead->id)->whereNull('read_at')->update(['read_at' => Carbon::now()]);

        return response()->json(['read' => true]);
    }

    private function slaByPriority(Collection $withSla): Collection
    {
        return collect(PriorityRegistry::all())->map(function (string $priority) use ($withSla) {
            $rows = $withSla->where('priority', $priority);
            $within = $rows->filter(fn (Ticket $t) => $t->sla_kind === 'ontrack')->count();
            $total = $rows->count();

            return [
                'priority' => $priority,
                'total' => $total,
                'pct' => $total > 0 ? (int) round($within / $total * 100) : 100,
            ];
        })->values();
    }

    /**
     * "SLA Compliance — Tiket Penyumbang Terbanyak": every ticket subject,
     * ranked by volume, with how many breached SLA and the resulting
     * compliance %. Each row expands to its sub-category breakdown. Built
     * from real tickets so it scales straight up as volume grows.
     */
    private function slaTopSubjects(Collection $tickets): array
    {
        $breached = fn (Ticket $t) => in_array($t->status, Ticket::DONE_STATUSES, true)
            ? ($t->resolved_at && $t->resolved_at->greaterThan($t->resolution_due_at))
            : (in_array($t->status, Ticket::ACTIVE_STATUSES, true) && $t->sla_kind === 'breach');

        $mode = fn (Collection $g, string $field) => $g->groupBy($field)->sortByDesc(fn (Collection $x) => $x->count())->keys()->first();

        $typeLabel = fn (?string $cat) => match ($cat) {
            'Access Request' => 'Access',
            'Service Request' => 'Service',
            default => 'Incident',
        };

        return $tickets
            ->filter(fn (Ticket $t) => ! in_array($t->status, ['Draft', 'Returned'], true))
            ->groupBy(fn (Ticket $t) => $t->subject_name ?: $t->title)
            ->map(function (Collection $g, string $subject) use ($breached, $mode, $typeLabel) {
                $volume = $g->count();
                $breach = $g->filter($breached)->count();

                $causes = $g
                    ->groupBy(fn (Ticket $t) => $t->subcategory_name ?: ($t->issue_category ?? 'Lainnya'))
                    ->map(fn (Collection $s, string $name) => [
                        'name' => $name,
                        'volume' => $s->count(),
                        'breach' => $s->filter($breached)->count(),
                    ])
                    ->sortByDesc('volume')
                    ->values()
                    ->all();

                return [
                    'subject' => $subject,
                    'app' => $mode($g, 'service_name') ?? '—',
                    'subCat' => $mode($g, 'subcategory_name') ?? ($mode($g, 'issue_category') ?? '—'),
                    'type' => $typeLabel($mode($g, 'issue_category')),
                    'volume' => $volume,
                    'breach' => $breach,
                    'compliance' => round(($volume - $breach) / max($volume, 1) * 100, 1),
                    'causes' => $causes,
                ];
            })
            ->sortByDesc('volume')
            ->values()
            ->all();
    }

    private function categoryBreakdown(Collection $tickets): Collection
    {
        $counted = $tickets->whereNotIn('status', ['Draft', 'Returned']);
        $total = max($counted->count(), 1);

        return $counted
            ->groupBy(fn (Ticket $t) => $t->issue_category ?? $t->category ?? 'Lainnya')
            ->map(fn (Collection $g, string $label) => [
                'label' => $label,
                'total' => $g->count(),
                'pct' => (int) round($g->count() / $total * 100),
            ])
            ->sortByDesc('total')
            ->values();
    }

    private function workload(Collection $agents, Collection $tickets): Collection
    {
        return $agents->map(function (SupportAgent $agent) use ($tickets) {
            $mine = $tickets->where('assigned_agent_id', $agent->id);
            $active = $mine->whereIn('status', Ticket::ACTIVE_STATUSES);
            $done = $mine->whereIn('status', Ticket::DONE_STATUSES);

            $resolvedWithTime = $done->filter(fn (Ticket $t) => $t->resolved_at !== null);
            $avgResolutionHours = $resolvedWithTime->isNotEmpty()
                ? round($resolvedWithTime->avg(fn (Ticket $t) => $t->created_at->diffInMinutes($t->resolved_at)) / 60, 1)
                : null;

            // first_response_at (set the moment Support takes any action on a
            // ticket, not just a discussion reply — see SupportController/
            // SupportBpoController::resolve()/escalate()/returnTicket()) is
            // what makes this measurable at all; before it existed there was
            // no per-ticket timestamp for "when did Support actually engage".
            $respondedWithTime = $mine->filter(fn (Ticket $t) => $t->first_response_at !== null);
            $avgResponseMinutes = $respondedWithTime->isNotEmpty()
                ? round($respondedWithTime->avg(fn (Ticket $t) => $t->created_at->diffInMinutes($t->first_response_at)))
                : null;

            $metSla = $resolvedWithTime->filter(fn (Ticket $t) => $t->resolved_at->lessThanOrEqualTo($t->resolution_due_at))->count();
            $productivity = $resolvedWithTime->isNotEmpty() ? (int) round($metSla / $resolvedWithTime->count() * 100) : null;

            $rated = $mine->whereNotNull('satisfaction_rating')->where('rating_active', true);

            return [
                'id' => $agent->id,
                'name' => $agent->name,
                'type' => strtoupper($agent->type),
                'initials' => $this->initials($agent->name),
                'load' => $active->count(),
                'resolved' => $done->count(),
                'avgResponse' => $avgResponseMinutes !== null ? $this->formatDuration($avgResponseMinutes) : '—',
                'avgResolution' => $avgResolutionHours !== null ? $avgResolutionHours.'h' : '—',
                'productivity' => $productivity,
                'rating' => $rated->isNotEmpty() ? round($rated->avg('satisfaction_rating'), 1) : null,
                'ratingCount' => $rated->count(),
                // Availability isn't tracked in the schema — derived from live
                // load so the badge reflects who's currently overloaded.
                'availability' => $active->count() >= 6 ? 'Sibuk' : 'Online',
                'tickets' => $active
                    ->sortBy('sla_minutes_remaining')
                    ->map(fn (Ticket $t) => [
                        'id' => $t->ticket_no,
                        'subject' => $t->subject_name ?? $t->title,
                        'priority' => $t->priority,
                        'service' => $t->service_name ?? '—',
                        'sla' => $t->sla_label,
                        'slaKind' => $t->sla_kind,
                    ])
                    ->values(),
            ];
        })->sortByDesc('load')->values();
    }

    /**
     * Response times are usually minutes, resolution times usually hours —
     * showing "0.3h" for an 18-minute reply is technically correct but
     * unreadable next to the resolution column's "Xh" figures.
     */
    private function formatDuration(int $minutes): string
    {
        return $minutes < 60 ? "{$minutes}m" : round($minutes / 60, 1).'h';
    }

    /**
     * "PIC per Subjek Tiket" — read-only catalog (Administrator-configured) of
     * which support agent owns each service subject. One row per active
     * subject, PIC = its BPO agent if set, otherwise the IT agent.
     */
    private function picRows(): array
    {
        // Restricted to this lead's own team. The old version listed every
        // active subject and resolved its PIC as `supportAgent ?? itAgent`,
        // which preferred the BPO owner — so a Team Lead supervising Support IT
        // saw BPO names here while every other panel showed their IT agents.
        $scoped = $this->scopedAgentQuery()->pluck('id');

        return ServiceCatalogSubject::where('is_active', true)
            ->whereIn('it_agent_id', $scoped)
            ->with(['service:id,name', 'subcategory:id,name', 'itAgent:id,name'])
            ->get()
            ->map(function (ServiceCatalogSubject $s) {
                $pic = $s->itAgent;

                return [
                    'pic' => $pic?->name ?? '—',
                    'initials' => $pic ? $this->initials($pic->name) : '—',
                    'app' => $s->service?->name ?? '—',
                    'subCat' => $s->subcategory?->name ?? '—',
                    'subject' => $s->name,
                ];
            })
            ->sortBy('app')
            ->values()
            ->all();
    }

    /**
     * Tickets eligible for an SLA teguran (released to Support + has a PIC),
     * each showing which channels a teguran has already gone out on — drives
     * the "Notifikasi & Teguran Tiket" table's per-channel send buttons.
     *
     * ACTIVE_STATUSES still contains 'Waiting for Approval', and
     * `assigned_agent_id` is frozen at creation, so filtering on "active +
     * has a PIC" alone would offer a teguran for a ticket still sitting with
     * the Approver — nagging a PIC about work they cannot even open.
     */
    private function teguranTickets(Collection $tickets): array
    {
        $sentByTicket = AuditTrail::where('module', 'team_lead')
            ->where('action', 'remind')
            ->get()
            ->groupBy('target_name')
            ->map(fn (Collection $g) => $g->flatMap(fn (AuditTrail $a) => $a->new_value['channels'] ?? [])->unique()->values()->all());

        return $tickets
            ->whereIn('status', Ticket::ACTIVE_STATUSES)
            ->whereNotIn('status', Ticket::NOT_YET_RELEASED_STATUSES)
            ->filter(fn (Ticket $t) => $t->assignedAgent !== null)
            ->sortBy('sla_minutes_remaining')
            ->map(fn (Ticket $t) => [
                'id' => $t->ticket_no,
                'subject' => $t->subject_name ?? $t->title,
                'pic' => $t->assignedAgent?->name ?? '—',
                'created' => $t->created_at->format('d M · H:i'),
                'channels' => array_values(array_filter($sentByTicket[$t->ticket_no] ?? [], fn ($c) => in_array($c, ['email', 'whatsapp'], true))),
            ])
            ->values()
            ->all();
    }

    /**
     * Option lists for the SLA Monitoring filter dropdowns.
     *
     * Deliberately NOT derived from the rows currently on screen: those are
     * only the active, SLA-bearing tickets, so a status like "In Progress"
     * vanished from the dropdown whenever no ticket happened to be in it, and
     * the list shrank as tickets closed. A filter that returns nothing is
     * informative; a filter option that silently disappears looks broken.
     *
     * Statuses are capped to what this view can actually contain (active, and
     * carrying an SLA clock). Applications/sub-categories/units come from every
     * scoped ticket in the period, so they stay stable and inside the lead's scope.
     */
    private function monitorFilters(Collection $tickets): array
    {
        $distinct = fn (string $key) => $tickets->pluck($key)
            ->filter(fn ($v) => filled($v) && $v !== '—')
            ->unique()->sort()->values()->all();

        return [
            'statuses' => array_values(array_diff(Ticket::ACTIVE_STATUSES, Ticket::NO_SLA_STATUSES)),
            'types' => ['Incident', 'Service Request', 'Access Request'],
            'priorities' => PriorityRegistry::all(),
            'apps' => $distinct('service_name'),
            'subcats' => $distinct('subcategory_name'),
            'units' => $distinct('requester.unit'),
            // Only this lead's own team (TEAM_SCOPE), and every active member of
            // it — a Team Lead supervises Support IT, so BPO agents are not
            // theirs to filter by, and an agent with an empty queue today is
            // still someone they need to be able to look up.
            'pics' => $this->scopedAgentQuery()->orderBy('name')->pluck('name')->unique()->values()->all(),
        ];
    }

    private function presentWarningRow(Ticket $t): array
    {
        $type = match ($t->issue_category) {
            'Access Request' => 'Access Request',
            'Service Request' => 'Service Request',
            default => 'Incident',
        };

        return [
            'id' => $t->ticket_no,
            'service' => $t->service_name ?? '—',
            'subcategory' => $t->subcategory_name ?? '—',
            'subject' => $t->subject_name ?? $t->title,
            'type' => $type,
            'priority' => $t->priority,
            'status' => $t->status,
            'sla' => $t->sla_label,
            'slaKind' => $t->sla_kind,
            'slaMinutes' => $t->sla_minutes_remaining,
            // null rather than a "Belum ada PIC" string: the client picks the
            // wording (and its language), and can filter for unassigned tickets.
            'agent' => $t->assignedAgent?->name,
            'agentId' => $t->assigned_agent_id,
            'unit' => $t->requester?->unit ?? '—',
            'createdAt' => $t->created_at->toIso8601String(),
        ];
    }

    /**
     * "Eskalasi BPO → Tim IT": tickets a Support BPO agent escalated to
     * Support IT (escalated_at set). The originating BPO agent is read back
     * from the shared audit trail's old_value; the current PIC is the IT
     * agent it was handed to. Monitoring-only for the Team Lead.
     */
    private function escalations(): array
    {
        $auditByTicket = AuditTrail::where('module', 'ticket_support')
            ->where('action', 'escalate')
            ->get()
            ->keyBy('target_name');

        return Ticket::whereNotNull('escalated_at')
            ->whereNotIn('status', ['Draft', 'Returned'])
            ->with('assignedAgent')
            ->latest('escalated_at')
            ->get()
            ->map(function (Ticket $t) use ($auditByTicket) {
                $audit = $auditByTicket->get($t->ticket_no);

                return [
                    'id' => $t->ticket_no,
                    'subject' => $t->subject_name ?? $t->title,
                    'service' => $t->service_name ?? '—',
                    'priority' => $t->priority,
                    'status' => $t->status,
                    'note' => $t->escalation_note,
                    'from' => $audit->old_value['assigned_agent'] ?? 'Support BPO',
                    'to' => $t->assignedAgent?->name ?? 'Support IT',
                    'escalatedAt' => $t->escalated_at->format('d M Y · H:i'),
                    'done' => in_array($t->status, Ticket::DONE_STATUSES, true),
                    // Only finished tickets can carry a rating — the requester
                    // gives it when closing. null means "closed without rating",
                    // which the table renders differently from "not closed yet".
                    'rating' => $t->satisfaction_rating,
                    'ratingActive' => (bool) $t->rating_active,
                ];
            })
            ->all();
    }

    private function presentBreachRow(Ticket $t): array
    {
        return [
            'id' => $t->ticket_no,
            'subject' => $t->subject_name ?? $t->title,
            'service' => $t->service_name ?? '—',
            'priority' => $t->priority,
            'overdue' => $t->sla_label,
            'agent' => $t->assignedAgent?->name ?? 'Belum ada PIC',
            'status' => $t->status,
        ];
    }

    private function opStats(Collection $tickets): array
    {
        return [
            ['label' => __('teamlead.columns.total_tickets'), 'value' => $tickets->count(), 'icon' => self::I_TICKET, 'bg' => 'bg-blue-50', 'fg' => 'text-blue-600'],
            ['label' => 'Open', 'value' => $tickets->where('status', 'Open')->count(), 'icon' => self::I_INBOX, 'bg' => 'bg-gray-100', 'fg' => 'text-gray-500'],
            ['label' => 'Assigned', 'value' => $tickets->where('status', 'Assigned')->count(), 'icon' => self::I_USER, 'bg' => 'bg-sky-50', 'fg' => 'text-sky-600'],
            ['label' => 'In Progress', 'value' => $tickets->whereIn('status', ['In Progress', 'Waiting for Response'])->count(), 'icon' => self::I_CLOCK, 'bg' => 'bg-amber-50', 'fg' => 'text-amber-600'],
            ['label' => 'Closed', 'value' => $tickets->whereIn('status', Ticket::DONE_STATUSES)->count(), 'icon' => self::I_CHECK, 'bg' => 'bg-emerald-50', 'fg' => 'text-emerald-600'],
        ];
    }

    /**
     * 6-month created-count trend for the top applications — real per-app,
     * per-month volume feeding the "Tren per Aplikasi" multi-line chart.
     */
    private function appTrend(Collection $tickets): array
    {
        $colors = ['#dc2626', '#2563eb', '#7c3aed', '#d97706', '#059669'];
        $topApps = collect($this->topApps($tickets))->pluck('name');
        // startOfMonth() first — subtracting months from a late-month day can
        // overflow into the wrong target month, repeating one label and
        // silently dropping another from the chart.
        $months = collect(range(5, 0))->map(fn (int $m) => Carbon::now()->startOfMonth()->subMonths($m));

        $pointsFor = fn (string $app) => $months->map(fn (Carbon $month) => $tickets->filter(fn (Ticket $t) => $t->service_name === $app && $t->created_at->isSameMonth($month) && $t->created_at->isSameYear($month))->count())->all();

        $series = $topApps->values()->map(fn (string $app, int $i) => [
            'name' => $app,
            'color' => $colors[$i % count($colors)],
            'total' => $tickets->where('service_name', $app)->count(),
            'points' => $pointsFor($app),
        ])->all();

        // Every application (sorted) so the chart's dropdown can focus any of
        // them — apps outside the top 5 get their line added on selection.
        $all = $tickets
            ->filter(fn (Ticket $t) => $t->service_name)
            ->groupBy('service_name')
            ->map(fn (Collection $g, string $app) => [
                'name' => $app,
                'total' => $g->count(),
                'points' => $pointsFor($app),
            ])
            ->sortKeys()
            ->values()
            ->all();

        return [
            'months' => $months->map(fn (Carbon $m) => $m->translatedFormat('M'))->all(),
            'series' => $series,
            'all' => $all,
        ];
    }

    /**
     * Data-driven escalation suggestions: services whose active queue is
     * trending toward a breach get a recommended one-level priority bump.
     */
    private function escalationRecs(Collection $active): array
    {
        // Menaik: dari paling longgar ke paling mendesak, kebalikan dari urutan
        // registry. Diturunkan dari registry supaya prioritas buatan Admin ikut
        // punya "tingkat di atasnya" — kalau tidak, tiket ber-prioritas baru
        // tidak akan pernah direkomendasikan naik.
        $order = array_reverse(PriorityRegistry::all());

        return $active
            ->filter(fn (Ticket $t) => in_array($t->sla_kind, ['warning', 'breach'], true) && $t->service_name)
            ->groupBy(fn (Ticket $t) => $t->service_name.' · '.($t->subcategory_name ?? $t->issue_category ?? 'Umum'))
            ->map(function (Collection $g, string $name) use ($order) {
                $breaches = $g->filter(fn (Ticket $t) => $t->sla_kind === 'breach')->count();
                $current = $g->groupBy('priority')->sortByDesc(fn ($x) => $x->count())->keys()->first() ?? 'Medium';
                $idx = array_search($current, $order, true);
                $to = $order[min(count($order) - 1, ($idx === false ? 1 : $idx) + 1)];

                return [
                    'name' => $name,
                    'service' => $g->first()->service_name,
                    'subcat' => $g->first()->subcategory_name ?? $g->first()->issue_category ?? 'Umum',
                    'reason' => $breaches > 0 ? "{$breaches} tiket sudah breach SLA" : $g->count().' tiket mendekati batas SLA',
                    'count' => $g->count(),
                    'from' => $current,
                    'to' => $to,
                ];
            })
            ->sortByDesc('count')
            ->take(4)
            ->values()
            ->all();
    }

    /**
     * "Naikkan" on an escalation recommendation: bumps every active ticket in
     * that service/sub-category group to the target priority for real, and
     * logs it to the shared audit trail.
     */
    public function escalateGroup(Request $request): JsonResponse
    {
        $lead = CurrentActor::teamLead();

        $data = $request->validate([
            'service' => 'required|string',
            'subcat' => 'required|string',
            'to' => 'required|in:Critical,High,Medium,Low',
        ]);

        $matched = $this->scopeTickets(Ticket::whereIn('status', Ticket::ACTIVE_STATUSES))
            ->where('service_name', $data['service'])
            ->where(fn ($q) => $q->where('subcategory_name', $data['subcat'])->orWhere('issue_category', $data['subcat']))
            ->get();

        $changed = 0;
        foreach ($matched as $ticket) {
            $old = $ticket->priority;
            if ($old === $data['to']) {
                continue;
            }
            $ticket->update(['priority' => $data['to']]);
            $changed++;

            AuditTrail::record($lead, [
                'module' => 'team_lead',
                'action' => 'raise_priority',
                'target_type' => 'ticket',
                'target_id' => $ticket->id,
                'target_name' => $ticket->ticket_no,
                'old_value' => ['priority' => $old],
                'new_value' => ['priority' => $data['to']],
                'description' => "{$lead->name} menaikkan prioritas tiket \"{$ticket->ticket_no}\" ({$data['service']} · {$data['subcat']}) dari {$old} ke {$data['to']} via rekomendasi eskalasi.",
            ]);
        }

        return response()->json([
            'count' => $changed,
            'message' => $changed > 0
                ? "{$changed} tiket dinaikkan ke prioritas {$data['to']}."
                : "Semua tiket sudah berprioritas {$data['to']}.",
        ]);
    }

    private function categoryTree(Collection $tickets): array
    {
        $colors = ['#dc2626', '#2563eb', '#059669', '#d97706', '#7c3aed'];
        $counted = $tickets->whereNotIn('status', ['Draft', 'Returned']);
        $total = max($counted->count(), 1);

        return $counted
            ->groupBy(fn (Ticket $t) => $t->issue_category ?? $t->category ?? 'Lainnya')
            ->map(fn (Collection $g) => $g)
            ->sortByDesc(fn (Collection $g) => $g->count())
            ->values()
            ->map(function (Collection $g, int $i) use ($colors, $total) {
                $catTotal = $g->count();
                $subs = $g
                    ->groupBy(fn (Ticket $t) => $t->subcategory_name ?? 'Lainnya')
                    ->map(fn (Collection $s, string $name) => ['name' => $name, 'count' => $s->count()])
                    ->sortByDesc('count')
                    ->take(4)
                    ->values();
                $subMax = max($subs->max('count') ?? 1, 1);

                return [
                    'label' => $g->first()->issue_category ?? $g->first()->category ?? 'Lainnya',
                    'total' => $catTotal,
                    'pct' => (int) round($catTotal / $total * 100),
                    'color' => $colors[$i % count($colors)],
                    'subs' => $subs->map(fn (array $s) => [
                        'name' => $s['name'],
                        'count' => $s['count'],
                        'pctW' => (int) round($s['count'] / $subMax * 100),
                    ])->all(),
                ];
            })
            ->all();
    }

    private function ticketTrend(Collection $tickets): array
    {
        // startOfMonth() first — see appTrend() above for why subtracting
        // months from a late-month day can overflow into the wrong month.
        $months = collect(range(5, 0))->map(fn (int $m) => Carbon::now()->startOfMonth()->subMonths($m));

        return $months->map(function (Carbon $month) use ($tickets) {
            $created = $tickets->filter(fn (Ticket $t) => $t->created_at->isSameMonth($month) && $t->created_at->isSameYear($month))->count();
            $resolved = $tickets->filter(fn (Ticket $t) => $t->resolved_at && $t->resolved_at->isSameMonth($month) && $t->resolved_at->isSameYear($month))->count();

            return ['month' => $month->translatedFormat('M'), 'created' => $created, 'resolved' => $resolved];
        })->values()->all();
    }

    private function topApps(Collection $tickets): array
    {
        return $tickets
            ->filter(fn (Ticket $t) => $t->service_name)
            ->groupBy('service_name')
            ->map(fn (Collection $g, string $name) => ['name' => $name, 'count' => $g->count()])
            ->sortByDesc('count')
            ->take(5)
            ->values()
            ->all();
    }

    private function topIssues(Collection $tickets): array
    {
        return $tickets
            ->filter(fn (Ticket $t) => $t->subject_name || $t->title)
            ->groupBy(fn (Ticket $t) => $t->subject_name ?? $t->title)
            ->map(fn (Collection $g, string $name) => [
                'name' => $name,
                'apps' => $g->pluck('service_name')->filter()->unique()->take(2)->implode(' · ') ?: '—',
                'count' => $g->count(),
            ])
            ->sortByDesc('count')
            ->take(5)
            ->values()
            ->all();
    }

    /**
     * "Performa Layanan": per ticket category (Incident / Service / Access),
     * average resolution time, ticket volume, and SLA compliance %. All
     * computed from real tickets.
     */
    private function servicePerformance(Collection $tickets): array
    {
        $breached = fn (Ticket $t) => in_array($t->status, Ticket::DONE_STATUSES, true)
            ? ($t->resolved_at && $t->resolved_at->greaterThan($t->resolution_due_at))
            : (in_array($t->status, Ticket::ACTIVE_STATUSES, true) && $t->sla_kind === 'breach');

        return $tickets
            ->filter(fn (Ticket $t) => ! in_array($t->status, ['Draft', 'Returned'], true))
            ->groupBy(fn (Ticket $t) => $t->issue_category ?? $t->category ?? 'Lainnya')
            ->map(function (Collection $g, string $name) use ($breached) {
                $total = $g->count();
                $compliance = $total > 0 ? (int) round(($total - $g->filter($breached)->count()) / $total * 100) : 100;
                $done = $g->whereIn('status', Ticket::DONE_STATUSES)->filter(fn (Ticket $t) => $t->resolved_at !== null);
                $avgHours = $done->isNotEmpty()
                    ? round($done->avg(fn (Ticket $t) => $t->created_at->diffInMinutes($t->resolved_at)) / 60, 1)
                    : null;

                return [
                    'name' => $name,
                    'meta' => ($avgHours !== null ? "Avg {$avgHours}h · " : '').$total.' tiket',
                    'compliance' => $compliance,
                ];
            })
            ->sortByDesc('compliance')
            ->values()
            ->all();
    }

    private function supportStats(Collection $tickets, Collection $agents): array
    {
        $today = Carbon::today();
        $resolvedToday = $tickets->filter(fn (Ticket $t) => $t->resolved_at && $t->resolved_at->isSameDay($today))->count();
        $teguranToday = TicketNotification::where('type', 'sla_teguran')->whereDate('created_at', $today)->count();

        $done = $tickets->whereIn('status', Ticket::DONE_STATUSES)->filter(fn (Ticket $t) => $t->resolved_at !== null);
        $metSla = $done->filter(fn (Ticket $t) => $t->resolved_at->lessThanOrEqualTo($t->resolution_due_at))->count();
        $productivity = $done->isNotEmpty() ? (int) round($metSla / $done->count() * 100) : 0;

        return [
            'agents' => $agents->count(),
            'teguranToday' => $teguranToday,
            'resolvedToday' => $resolvedToday,
            'productivity' => $productivity,
        ];
    }

    /** Entri riwayat yang ditampilkan sekaligus di feed Riwayat. */
    private const AUDIT_FEED_LIMIT = 60;

    /**
     * Modul jejak audit milik siklus hidup tiket. Aktornya bukan Team Lead,
     * tapi tiketnya ada di bawah pengawasannya.
     */
    private const AUDIT_TICKET_MODULES = ['ticket_support', 'ticket_approval', 'ticket_management'];

    /**
     * Feed Riwayat: tindakan korektif Team Lead DITAMBAH perjalanan tiket yang
     * ada dalam cakupan timnya.
     *
     * SEBELUMNYA hanya baris bermodul `team_lead` yang dibaca, jadi jejak audit
     * ini cuma memantulkan kembali tindakan Team Lead sendiri. Eskalasi dari
     * Support BPO ke Tim IT dan penutupan tiket oleh PIC IT sudah lama tercatat
     * di audit_trails (SupportBpoController::escalate(),
     * SupportController::resolve()), tapi tidak pernah muncul di layar yang
     * justru bertugas melacaknya — dua peristiwa terpenting bagi seorang
     * supervisor hilang dari jejak auditnya.
     *
     * SEKARANG cakupannya ditentukan tiketnya, bukan siapa pelakunya: setiap
     * entri bertarget tiket yang PIC-nya anggota tim ini ikut terbaca. Tiket
     * BPO masuk cakupan tepat saat dieskalasi — eskalasi memindahkan PIC-nya ke
     * agent IT — sehingga baris eskalasi itu sendiri sudah ikut terlihat.
     */
    private function auditRows(): array
    {
        $scopedTicketIds = $this->scopeTickets(Ticket::query())->select('tickets.id');

        return AuditTrail::query()
            ->with('actor:id,name')
            ->where(function (Builder $q) use ($scopedTicketIds) {
                $q->where('module', 'team_lead')
                    ->orWhere(fn (Builder $ticketScope) => $ticketScope
                        ->whereIn('module', self::AUDIT_TICKET_MODULES)
                        ->where('target_type', 'ticket')
                        ->whereIn('target_id', $scopedTicketIds));
            })
            ->latest('id')
            ->take(self::AUDIT_FEED_LIMIT)
            ->get()
            ->map(fn (AuditTrail $a) => [
                'id' => $a->id,
                'action' => $a->action,
                'module' => $a->module,
                'actor' => $a->actor?->name ?? '—',
                'ticket' => $a->target_name,
                'detail' => $a->description,
                'time' => $a->created_at->format('d M Y · H:i'),
            ])
            ->all();
    }

    /** Sentinel for "no unit filter" — never a real unit name, never translated. */
    public const ALL_UNITS = '__all';

    private function reportUnits(): array
    {
        return array_values(array_filter(array_unique(
            User::whereNotNull('unit')->pluck('unit')->all()
        )));
    }

    private function currentUserPayload(User $lead): array
    {
        return [
            'name' => $lead->name,
            'title' => trim(($lead->jabatan ?? 'Team Lead').' · '.($lead->unit ?? '')),
            'initials' => $this->initials($lead->name),
        ];
    }

    private function initials(string $name): string
    {
        $parts = explode(' ', trim($name));

        return strtoupper(substr($parts[0] ?? '', 0, 1).substr($parts[1] ?? '', 0, 1));
    }

    private function notifications(User $lead): array
    {
        return NotificationService::present($lead, 20, 'dashboard.team-lead', 'team-lead.notifications.read');
    }

    /**
     * Supervisor notification feed: every event a Team Lead should react to —
     * BPO→IT escalations, SLA breaches, and SLA warnings — synthesized live
     * from the tickets, each carrying the ticket_no so the bell can open the
     * ticket slide-over. The persona's own persisted notifications are merged
     * in after.
     */
    private function teamLeadNotifications(Collection $tickets, User $lead): array
    {
        $icon = [
            'ticket_escalated' => 'M12 19V5 M5 12l7-7 7 7',
            'sla_breach' => 'M12 9v4M12 17h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z',
            'sla_warning' => 'M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18Z M12 7v5l3 3',
        ];
        $items = [];

        foreach (Ticket::whereNotNull('escalated_at')->latest('escalated_at')->take(10)->get() as $t) {
            $items[] = [
                'sortAt' => $t->escalated_at,
                'id' => 'esc-'.$t->id,
                'type' => 'ticket_escalated',
                'text' => "Tiket {$t->ticket_no} dieskalasi BPO → Tim IT".($t->escalation_note ? ": {$t->escalation_note}" : '.'),
                'time' => $t->escalated_at->diffForHumans(),
                'unread' => true,
                'ticketId' => $t->ticket_no,
                'icon' => $icon['ticket_escalated'],
            ];
        }

        $active = $tickets->whereIn('status', Ticket::ACTIVE_STATUSES)->filter(fn (Ticket $t) => $t->sla_minutes_remaining !== null);

        foreach ($active->filter(fn (Ticket $t) => $t->sla_kind === 'breach')->sortBy('sla_minutes_remaining')->take(8) as $t) {
            $items[] = [
                'sortAt' => $t->resolution_due_at,
                'id' => 'brc-'.$t->id,
                'type' => 'sla_breach',
                'text' => "SLA Breach: Tiket {$t->ticket_no} \"{$t->title}\" sudah melewati batas SLA.",
                'time' => $t->resolution_due_at->diffForHumans(),
                'unread' => true,
                'ticketId' => $t->ticket_no,
                'icon' => $icon['sla_breach'],
            ];
        }

        foreach ($active->filter(fn (Ticket $t) => $t->sla_kind === 'warning')->sortBy('sla_minutes_remaining')->take(6) as $t) {
            $items[] = [
                'sortAt' => $t->warning_at,
                'id' => 'wrn-'.$t->id,
                'type' => 'sla_warning',
                'text' => "SLA Warning: Tiket {$t->ticket_no} \"{$t->title}\" mendekati batas SLA.",
                'time' => $t->warning_at->diffForHumans(),
                'unread' => true,
                'ticketId' => $t->ticket_no,
                'icon' => $icon['sla_warning'],
            ];
        }

        $synthesized = collect($items)
            ->sortByDesc('sortAt')
            ->map(fn (array $i) => Arr::except($i, ['sortAt']))
            ->values()
            ->all();

        return array_merge($synthesized, $this->notifications($lead));
    }
}
