<?php

namespace App\Http\Controllers;

use App\Models\AuditTrail;
use App\Models\Ticket;
use App\Models\TicketApproval;
use App\Models\TicketComment;
use App\Models\TicketNotification;
use App\Models\User;
use App\Support\CurrentActor;
use App\Support\DashboardPeriod;
use App\Support\NotificationService;
use App\Support\PriorityRegistry;
use App\Support\TicketDiscussion;
use App\Support\TicketFlow;
use App\Support\TicketPeople;
use App\Support\TicketTimeline;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class ApprovalController extends Controller
{
    private const DECISION_LABELS = [
        'approved' => 'Disetujui',
        'revision_requested' => 'Minta Perbaikan',
        'rejected' => 'Ditolak',
    ];

    /**
     * "Approval Inbox" — the approver's dashboard: queue metrics, this
     * month's priority mix of the pending queue, a 6-week decision trend,
     * and the sortable/filterable table of tickets still waiting on them.
     */
    public function inbox(): View
    {
        $approver = CurrentActor::approver();

        $pending = Ticket::where('approver_id', $approver->id)
            ->where('status', 'Waiting for Approval')
            ->with('requester')
            ->get();

        $decisions = TicketApproval::where('approver_id', $approver->id)->get();
        $now = Carbon::now();
        $thisMonth = $decisions->filter(fn (TicketApproval $d) => $d->created_at->isSameMonth($now) && $d->created_at->isSameYear($now));

        $oldestPending = $pending->min('created_at');
        $waitingLongest = $oldestPending ? $this->formatWaitDuration((int) $oldestPending->diffInMinutes($now)) : '—';

        $priorityDistribution = collect(PriorityRegistry::all())
            ->map(function (string $priority) use ($pending) {
                $count = $pending->where('priority', $priority)->count();

                return [
                    'priority' => $priority,
                    'count' => $count,
                    'pct' => $pending->count() > 0 ? (int) round($count / $pending->count() * 100) : 0,
                ];
            })
            ->values();

        $dominant = $priorityDistribution->sortByDesc('pct')->first();

        return view('approver.inbox', [
            'role' => 'approver',
            'currentUser' => $this->currentUserPayload($approver),
            'notifications' => $this->notifications($approver),
            'metrics' => [
                'waitingApproval' => $pending->count(),
                'waitingLongest' => $waitingLongest,
                'approvedThisMonth' => $thisMonth->where('decision', 'approved')->count(),
                'rejectedThisMonth' => $thisMonth->where('decision', 'rejected')->count(),
            ],
            'priorityDistribution' => $priorityDistribution,
            'priorityTotal' => $pending->count(),
            'priorityHighlight' => $dominant && $dominant['count'] > 0
                ? "{$dominant['priority']} mendominasi antrean bulan ini ({$dominant['pct']}%) — prioritaskan tiket kategori ini saat meninjau inbox."
                : 'Belum ada tiket menunggu bulan ini.',
            'decisionTrend' => $this->decisionTrendByWeek($approver),
            /*
             | Ringkasan per periode — Minggu / Bulan / Tahun, mengikuti
             | dashboard Support, Support BPO, dan Requester.
             |
             | Antrean dibatasi menurut created_at tiket, sedangkan tren
             | dibatasi menurut kapan KEPUTUSANNYA diambil. Dua sumbu waktu yang
             | berbeda, dan memang harus berbeda: "berapa yang masuk minggu ini"
             | dan "berapa yang saya putuskan minggu ini" adalah dua pertanyaan
             | terpisah, dan menyamakannya akan membuat keputusan atas tiket
             | lama hilang dari grafik.
             */
            'periods' => $this->periodSummaries($approver, $pending),
            'pending' => $pending->map(fn (Ticket $t) => $this->presentPendingRow($t))->values(),
        ]);
    }

    /**
     * "My Tickets" — every ticket ever routed to this approver, decided or
     * not. Tickets they've already acted on are shown with that decision;
     * a ticket that just landed and is still "Waiting for Approval" (never
     * decided yet) would otherwise vanish from this page entirely — it only
     * has TicketApproval rows once a decision exists — so those are merged
     * in separately as "Menunggu Keputusan" rows. Without this, a brand new
     * pending ticket only ever showed up in the Approval Inbox notification,
     * never here, which reads as the ticket having gone missing.
     */
    public function history(): View
    {
        $approver = CurrentActor::approver();

        $decisionsByTicket = TicketApproval::where('approver_id', $approver->id)
            ->orderBy('created_at')
            ->get()
            ->groupBy('ticket_id')
            ->map(fn (Collection $g) => $g->last());

        $decidedTickets = Ticket::whereIn('id', $decisionsByTicket->keys())->get()->keyBy('id');

        $decidedRows = $decisionsByTicket->map(function (TicketApproval $d) use ($decidedTickets) {
            $t = $decidedTickets[$d->ticket_id];

            return [
                'id' => $t->ticket_no,
                'title' => $t->title,
                'service' => trim(($t->service_name ?? '').($t->subcategory_name ? ' · '.$t->subcategory_name : '')) ?: '—',
                'layanan' => $t->service_name ?? '—',
                'subCategory' => $t->subcategory_name ?? '—',
                'issueCategory' => $t->issue_category ?? $t->category ?? '—',
                'priority' => $t->priority,
                'status' => $t->status,
                'decision' => $d->decision,
                'decisionLabel' => self::DECISION_LABELS[$d->decision] ?? $d->decision,
                'note' => $d->note,
                'forwardedTo' => $d->forwarded_to ?? '—',
                'at' => $d->created_at->translatedFormat('j M Y · H:i'),
                'createdAt' => $d->created_at->toIso8601String(),
                'href' => route('approver.tickets.show', $t),
            ];
        });

        $pendingTickets = Ticket::where('approver_id', $approver->id)
            ->where('status', 'Waiting for Approval')
            ->whereNotIn('id', $decisionsByTicket->keys())
            ->get();

        $pendingRows = $pendingTickets->map(fn (Ticket $t) => [
            'id' => $t->ticket_no,
            'title' => $t->title,
            'service' => trim(($t->service_name ?? '').($t->subcategory_name ? ' · '.$t->subcategory_name : '')) ?: '—',
            'layanan' => $t->service_name ?? '—',
            'subCategory' => $t->subcategory_name ?? '—',
            'issueCategory' => $t->issue_category ?? $t->category ?? '—',
            'priority' => $t->priority,
            'status' => $t->status,
            'decision' => 'pending',
            'decisionLabel' => 'Menunggu Keputusan',
            'note' => '—',
            'forwardedTo' => '—',
            'at' => $t->created_at->translatedFormat('j M Y · H:i'),
            'createdAt' => $t->created_at->toIso8601String(),
            'href' => route('approver.tickets.show', $t),
        ]);

        $rows = $decidedRows->concat($pendingRows)->sortByDesc('createdAt')->values();

        $tickets = $decidedTickets->union($pendingTickets->keyBy('id'));

        $counts = [
            'Total' => $tickets->count(),
            'Open' => $tickets->where('status', 'Open')->count(),
            'In Progress' => $tickets->whereIn('status', ['Assigned', 'In Progress', 'Waiting for Response'])->count(),
            'Resolved' => $tickets->where('status', 'Resolved')->count(),
            'Closed' => $tickets->whereIn('status', ['Closed', 'Completed'])->count(),
            'Rejected' => $tickets->where('status', 'Rejected')->count(),
            'Waiting for Approval' => $tickets->where('status', 'Waiting for Approval')->count(),
        ];

        return view('approver.history', [
            'role' => 'approver',
            'currentUser' => $this->currentUserPayload($approver),
            'notifications' => $this->notifications($approver),
            'counts' => $counts,
            'rows' => $rows,
        ]);
    }

    public function show(Ticket $ticket): View
    {
        $approver = CurrentActor::approver();
        abort_unless($ticket->approver_id === $approver->id, 403);

        $ticket->load(['requester', 'approver', 'assignedAgent', 'escalatedByAgent', 'catalogSubject.supportAgent', 'catalogSubject.itAgent', 'comments', 'attachments']);
        $lastDecision = TicketApproval::where('ticket_id', $ticket->id)->where('approver_id', $approver->id)->latest('created_at')->first();

        return view('approver.ticket-detail', [
            'role' => 'approver',
            'currentUser' => $this->currentUserPayload($approver),
            // Identitas pembaca — dipakai layar untuk memutuskan gelembung mana
            // miliknya sendiri di Forum Diskusi. Dipisah dari payload header
            // yang tidak membawa id. Lihat lib/discussion.js.
            'viewer' => ['id' => $approver->id, 'name' => $approver->name],
            'notifications' => $this->notifications($approver),
            'ticket' => $this->presentTicket($ticket, $lastDecision),
            'comments' => $ticket->comments->map(fn (TicketComment $c) => TicketDiscussion::present($c))->values(),
            'timeline' => TicketTimeline::steps($ticket),
            'flow' => TicketFlow::stages($ticket),
            'dataUrl' => route('approver.tickets.data', $ticket),
            'commentsUrl' => route('approver.tickets.comments.store', $ticket),
            'decideUrl' => route('approver.tickets.decide', $ticket),
            'ticketsUrl' => route('approver.tickets'),
        ]);
    }

    /**
     * JSON re-fetch of this same ticket — lets the detail page pull a fresh
     * decision/status/timeline in place after approve/reject instead of a
     * full `window.location.reload()`.
     */
    public function data(Ticket $ticket): JsonResponse
    {
        $approver = CurrentActor::approver();
        abort_unless($ticket->approver_id === $approver->id, 403);

        $ticket->load(['requester', 'approver', 'assignedAgent', 'escalatedByAgent', 'catalogSubject.supportAgent', 'catalogSubject.itAgent', 'comments', 'attachments']);
        $lastDecision = TicketApproval::where('ticket_id', $ticket->id)->where('approver_id', $approver->id)->latest('created_at')->first();

        return response()->json([
            'ticket' => $this->presentTicket($ticket, $lastDecision),
            'comments' => $ticket->comments->map(fn (TicketComment $c) => TicketDiscussion::present($c))->values(),
            'timeline' => TicketTimeline::steps($ticket),
            'flow' => TicketFlow::stages($ticket),
        ]);
    }

    public function addComment(Request $request, Ticket $ticket): JsonResponse
    {
        $approver = CurrentActor::approver();
        abort_unless($ticket->approver_id === $approver->id, 403);
        abort_if(in_array($ticket->status, ['Closed', 'Rejected'], true), 422, 'Diskusi tiket ini sudah ditutup.');

        $data = $request->validate(TicketDiscussion::rules());

        $comment = TicketDiscussion::store($ticket, $approver, 'Approver', 'Approver', $data, $request->file('file'));

        return response()->json(TicketDiscussion::present($comment), 201);
    }

    /**
     * Records the approver's decision (mandatory note enforced server-side
     * too, not just in the UI) and routes the ticket accordingly: approved
     * tickets go straight to whichever Support agent the catalog subject
     * already assigns, revisions go back to the requester as an editable
     * Draft, rejections close the ticket outright.
     */
    public function decide(Request $request, Ticket $ticket): JsonResponse
    {
        $approver = CurrentActor::approver();
        abort_unless($ticket->approver_id === $approver->id, 403);
        abort_unless($ticket->status === 'Waiting for Approval', 422, 'Only tickets waiting for approval can be decided.');

        $data = $request->validate([
            'decision' => 'required|in:approved,revision_requested,rejected',
            'note' => 'required|string|max:3000',
        ]);

        $forwardedTo = $data['decision'] === 'approved'
            ? $this->forwardedToLabel($ticket)
            : 'Kirim ke Requester';

        $ticket->update([
            'status' => match ($data['decision']) {
                'approved' => 'Open',
                'revision_requested' => 'Returned',
                'rejected' => 'Rejected',
            },
            // Returned tickets are editable like a Draft (see TicketController::update()),
            // but is_draft stays false — they were already submitted once, so
            // TicketTimeline must keep their submission/approval history instead
            // of collapsing to a bare "Draft saved" step.
            'is_draft' => false,
        ]);

        TicketApproval::create([
            'ticket_id' => $ticket->id,
            'approver_id' => $approver->id,
            'decision' => $data['decision'],
            'note' => $data['note'],
            'forwarded_to' => $forwardedTo,
        ]);

        $this->recordAudit($approver, $ticket, $data['decision'], $data['note'], $forwardedTo);
        $this->notifyRequester($ticket, $data['decision'], $data['note']);

        if ($data['decision'] === 'approved') {
            NotificationService::notifyAssignedAgent(
                $ticket->fresh(),
                'ticket_created',
                'Tiket Baru Ditugaskan',
                "Tiket {$ticket->ticket_no} \"{$ticket->title}\" disetujui {$approver->name} dan diteruskan ke Anda."
            );
        }

        return response()->json(['status' => $ticket->fresh()->status]);
    }

    public function markNotificationRead(TicketNotification $notification): JsonResponse
    {
        $approver = CurrentActor::approver();
        abort_unless($notification->user_id === $approver->id, 403);

        if ($notification->read_at === null) {
            $notification->update(['read_at' => Carbon::now()]);
        }

        return response()->json(['read' => true]);
    }

    public function markAllNotificationsRead(): JsonResponse
    {
        $approver = CurrentActor::approver();

        // Hanya peran ini. Tanpa penyaring role, "tandai semua terbaca" di satu
        // layar ikut membersihkan lonceng peran lain milik orang yang sama.
        TicketNotification::where('user_id', $approver->id)->where('role', 'approver')->whereNull('read_at')->update(['read_at' => Carbon::now()]);

        return response()->json(['read' => true]);
    }

    private function notifyRequester(Ticket $ticket, string $decision, string $note): void
    {
        if (! $ticket->requester) {
            return;
        }

        [$type, $title, $message] = match ($decision) {
            'approved' => ['ticket_approved', 'Tiket Disetujui', "Tiket {$ticket->ticket_no} disetujui dan diteruskan ke Tim Support."],
            'revision_requested' => ['ticket_reopened', 'Perlu Diperbaiki', "Tiket {$ticket->ticket_no} dikembalikan untuk diperbaiki: {$note}"],
            'rejected' => ['ticket_rejected', 'Tiket Ditolak', "Tiket {$ticket->ticket_no} ditolak: {$note}"],
        };

        NotificationService::notify($ticket->requester, 'requester', $ticket, $type, $title, $message);
    }

    /**
     * Every approve/request-revision/reject decision lands in the same
     * Audit Trail the Admin console already reads (service_catalog,
     * sla_configuration, user_role_management) — "ticket_approval" is a
     * fourth module on that shared log, not a separate table.
     */
    private function recordAudit(User $approver, Ticket $ticket, string $decision, string $note, string $forwardedTo): void
    {
        $action = match ($decision) {
            'approved' => 'approve',
            'revision_requested' => 'request_revision',
            'rejected' => 'reject',
        };

        $description = match ($decision) {
            'approved' => "{$approver->name} menyetujui tiket \"{$ticket->ticket_no}\" dan meneruskannya ke {$forwardedTo}.",
            'revision_requested' => "{$approver->name} meminta perbaikan pada tiket \"{$ticket->ticket_no}\": {$note}",
            'rejected' => "{$approver->name} menolak tiket \"{$ticket->ticket_no}\": {$note}",
        };

        AuditTrail::record($approver, [
            'module' => 'ticket_approval',
            'action' => $action,
            'target_type' => 'ticket',
            'target_id' => $ticket->id,
            'target_name' => $ticket->ticket_no,
            'old_value' => ['status' => 'Waiting for Approval'],
            'new_value' => ['status' => $ticket->status, 'catatan' => $note],
            'description' => $description,
        ]);
    }

    /**
     * Distribusi prioritas antrean dan tren keputusan untuk tiap rentang.
     *
     * @param  Collection<int,Ticket>  $pending
     * @return array<string,array<string,mixed>>
     */
    private function periodSummaries(User $approver, Collection $pending): array
    {
        $decisions = TicketApproval::where('approver_id', $approver->id)
            ->where('created_at', '>=', DashboardPeriod::cutoff('year'))
            ->get();

        return collect(DashboardPeriod::KEYS)
            ->mapWithKeys(function (string $period) use ($pending, $decisions) {
                $windowed = $pending->filter(
                    fn (Ticket $t) => $t->created_at->greaterThanOrEqualTo(DashboardPeriod::cutoff($period)),
                );

                $distribution = $this->priorityDistributionFor($windowed);
                $dominant = $distribution->sortByDesc('pct')->first();

                return [$period => [
                    'priorityDistribution' => $distribution->values()->all(),
                    'priorityTotal' => $windowed->count(),
                    'priorityHighlight' => $dominant && $dominant['count'] > 0
                        ? "{$dominant['priority']} mendominasi antrean periode ini ({$dominant['pct']}%) — prioritaskan tiket kategori ini saat meninjau inbox."
                        : 'Belum ada tiket menunggu pada periode ini.',
                    'decisionTrend' => $this->decisionTrendFor($decisions, $period),
                ]];
            })
            ->all();
    }

    /**
     * @param  Collection<int,Ticket>  $tickets
     * @return Collection<int,array{priority:string,count:int,pct:int}>
     */
    private function priorityDistributionFor(Collection $tickets): Collection
    {
        return collect(PriorityRegistry::all())->map(function (string $priority) use ($tickets) {
            $count = $tickets->where('priority', $priority)->count();

            return [
                'priority' => $priority,
                'count' => $count,
                'pct' => $tickets->count() > 0 ? (int) round($count / $tickets->count() * 100) : 0,
            ];
        });
    }

    /**
     * @param  Collection<int,TicketApproval>  $decisions
     * @return array<int,array{label:string,approved:int,rejected:int}>
     */
    private function decisionTrendFor(Collection $decisions, string $period): array
    {
        return DashboardPeriod::buckets($period)->map(function (array $bucket) use ($decisions) {
            $inBucket = $decisions->filter(
                fn (TicketApproval $d) => $d->created_at->between($bucket['start'], $bucket['end']),
            );

            return [
                'label' => $bucket['label'],
                'approved' => $inBucket->where('decision', 'approved')->count(),
                'rejected' => $inBucket->whereIn('decision', ['rejected', 'revision_requested'])->count(),
            ];
        })->values()->all();
    }

    private function decisionTrendByWeek(User $approver): array
    {
        $weeks = collect(range(5, 0))->map(fn (int $w) => Carbon::now()->startOfWeek()->subWeeks($w));

        $decisions = TicketApproval::where('approver_id', $approver->id)
            ->where('created_at', '>=', Carbon::now()->startOfWeek()->subWeeks(5))
            ->get();

        return $weeks->map(function (Carbon $weekStart, int $i) use ($decisions) {
            $weekEnd = $weekStart->clone()->endOfWeek();
            $inWeek = $decisions->filter(fn (TicketApproval $d) => $d->created_at->between($weekStart, $weekEnd));

            return [
                'label' => 'Minggu '.($i + 1),
                'approved' => $inWeek->where('decision', 'approved')->count(),
                'rejected' => $inWeek->whereIn('decision', ['rejected', 'revision_requested'])->count(),
            ];
        })->values()->all();
    }

    private function presentPendingRow(Ticket $t): array
    {
        return [
            'id' => $t->ticket_no,
            'layanan' => $t->service_name ?? '—',
            'subCategory' => $t->subcategory_name ?? '—',
            'subject' => $t->subject_name ?? $t->title,
            'category' => $t->issue_category ?? $t->category ?? '—',
            'priority' => $t->priority,
            'requester' => $t->requester?->name ?? '—',
            'created' => $t->created_at->translatedFormat('j M Y'),
            'createdAt' => $t->created_at->toIso8601String(),
            'href' => route('approver.tickets.show', $t),
        ];
    }

    private function presentTicket(Ticket $t, ?TicketApproval $lastDecision): array
    {
        return [
            'id' => $t->ticket_no,
            'title' => $t->title,
            'status' => $t->status,
            'priority' => $t->priority,
            'category' => $t->issue_category ?? $t->category ?? '—',
            'layananKatalog' => trim(($t->service_name ?? '—').($t->subject_name ? ' · '.$t->subject_name : '')),
            'description' => $t->description,
            'attachments' => $t->attachmentsPayload(),
            'createdAt' => $t->created_at->translatedFormat('j M Y · H:i'),
            'satisfactionRating' => $t->satisfaction_rating,
            'feedbackNote' => $t->feedback_note,
            'ratingActive' => (bool) $t->rating_active,
            'sla' => $t->slaPayload(),
            'requester' => $t->requester ? [
                'name' => $t->requester->name,
                'unit' => $t->requester->unit,
                'email' => $t->requester->email,
            ] : null,
            // Same shape the Requester and Support detail screens use, so the
            // People panel reads identically wherever the ticket is opened.
            'people' => [
                'requester' => $t->requester ? ['name' => $t->requester->name, 'role' => 'Requester', 'email' => $t->requester->email] : null,
                'approver' => $t->approver ? ['name' => $t->approver->name, 'role' => 'Approver · '.$t->approver->jabatan, 'email' => $t->approver->email] : null,
                'support' => TicketPeople::supportAgents($t),
            ],
            'canDecide' => $t->status === 'Waiting for Approval',
            'lastDecision' => $lastDecision ? [
                'decision' => $lastDecision->decision,
                'decisionLabel' => self::DECISION_LABELS[$lastDecision->decision] ?? $lastDecision->decision,
                'note' => $lastDecision->note,
                'forwardedTo' => $lastDecision->forwarded_to,
                'at' => $lastDecision->created_at->translatedFormat('j M Y · H:i'),
            ] : null,
        ];
    }

    private function currentUserPayload(User $approver): array
    {
        return [
            'name' => $approver->name,
            'title' => trim(($approver->jabatan ?? 'Approver').' · '.($approver->unit ?? '')),
            'initials' => $this->initials($approver->name),
        ];
    }

    private function initials(string $name): string
    {
        $parts = explode(' ', trim($name));

        return strtoupper(substr($parts[0] ?? '', 0, 1).substr($parts[1] ?? '', 0, 1));
    }

    private function notifications(User $approver): array
    {
        return NotificationService::present($approver, 'approver', 20, 'approver.tickets.show', 'approver.notifications.read');
    }

    private function formatWaitDuration(int $minutes): string
    {
        $days = intdiv($minutes, 1440);
        $hours = intdiv($minutes % 1440, 60);

        return "{$days}h {$hours}j";
    }

    /**
     * Computed live from the catalog Subject's current support assignment
     * (same source TicketDetailController's People panel already reads),
     * not the ticket's frozen assigned_agent_id — a Level 2 Subject (both
     * BPO and IT teams) lists both agents here instead of only one.
     */
    private function forwardedToLabel(Ticket $ticket): string
    {
        $agents = collect([$ticket->catalogSubject?->supportAgent, $ticket->catalogSubject?->itAgent])
            ->filter()
            ->map(fn ($a) => 'Support '.strtoupper($a->type).' - '.($ticket->service_name ?? $a->name))
            ->unique()
            ->values();

        return $agents->isNotEmpty() ? $agents->implode(' & ') : 'Tim Support';
    }
}
