<?php

namespace App\Http\Controllers;

use App\Models\AuditTrail;
use App\Models\SupportAgent;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\TicketNotification;
use App\Models\User;
use App\Support\CurrentActor;
use App\Support\NotificationService;
use App\Support\TicketTimeline;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * "Support BPO" — the first-line workspace (Denny Firmansyah). Structurally
 * identical to SupportController (the IT workspace), except BPO also has an
 * "Eskalasi IT" action that hands the ticket off to the Support IT queue —
 * see escalate() below.
 */
class SupportBpoController extends Controller
{
    private const PRIORITY_COLOR = ['Critical' => '#dc2626', 'High' => '#d97706', 'Medium' => '#2563eb', 'Low' => '#9ca3af'];

    private const CATEGORY_COLOR = ['Incident' => '#2563eb', 'Service Request' => '#d97706', 'Access Request' => '#10b981'];

    public function dashboard(): View
    {
        $bpoUser = CurrentActor::supportBpo();
        $agent = $this->agentFor($bpoUser);

        $myTickets = Ticket::where('assigned_agent_id', $agent->id)->with('requester')->get();

        $active = $myTickets->whereIn('status', Ticket::ACTIVE_STATUSES);
        $inProgress = $myTickets->whereIn('status', ['Assigned', 'In Progress', 'Waiting for Response']);
        $slaAtRisk = $active->filter(fn (Ticket $t) => in_array($t->sla_kind, ['warning', 'breach'], true));
        $resolvedThisMonth = $myTickets->whereIn('status', Ticket::DONE_STATUSES)
            ->filter(fn (Ticket $t) => $t->resolved_at && $t->resolved_at->isSameMonth(Carbon::now()) && $t->resolved_at->isSameYear(Carbon::now()));

        $queue = $myTickets->reject(fn (Ticket $t) => in_array($t->status, ['Resolved', 'Completed', 'Closed', 'Rejected', 'Draft', 'Waiting for Approval'], true));

        $periods = collect(['week' => Carbon::now()->startOfWeek(), 'month' => Carbon::now()->startOfMonth(), 'year' => Carbon::now()->startOfYear()])
            ->map(function (Carbon $cutoff) use ($myTickets) {
                $windowed = $myTickets->filter(fn (Ticket $t) => $t->created_at->greaterThanOrEqualTo($cutoff));

                return [
                    'priority' => $this->priorityBreakdown($windowed),
                    'category' => $this->categoryDonut($windowed),
                    'sla' => $this->slaDonut($windowed),
                ];
            });

        return view('support-bpo.dashboard', [
            'role' => 'support-bpo',
            'currentUser' => $this->currentUserPayload($bpoUser),
            'notifications' => $this->notifications($bpoUser),
            'stats' => [
                'assignedToMe' => $active->where('status', 'Open')->count(),
                'inProgress' => $inProgress->count(),
                'slaAtRisk' => $slaAtRisk->count(),
                'resolvedThisMonth' => $resolvedThisMonth->count(),
            ],
            'periods' => $periods,
            'queue' => $queue->map(fn (Ticket $t) => $this->presentQueueRow($t))->values(),
        ]);
    }

    public function myTickets(): View
    {
        $bpoUser = CurrentActor::supportBpo();
        $agent = $this->agentFor($bpoUser);

        $tickets = Ticket::where('assigned_agent_id', $agent->id)->with('requester')->latest('created_at')->get();

        $counts = [
            'Total' => $tickets->count(),
            'Open' => $tickets->where('status', 'Open')->count(),
            'In Progress' => $tickets->whereIn('status', ['Assigned', 'In Progress', 'Waiting for Response'])->count(),
            'Resolved' => $tickets->where('status', 'Resolved')->count(),
            'Closed' => $tickets->whereIn('status', ['Closed', 'Completed'])->count(),
        ];

        return view('support-bpo.tickets', [
            'role' => 'support-bpo',
            'currentUser' => $this->currentUserPayload($bpoUser),
            'notifications' => $this->notifications($bpoUser),
            'counts' => $counts,
            'rows' => $tickets->map(fn (Ticket $t) => $this->presentHistoryRow($t))->values(),
        ]);
    }

    public function show(Ticket $ticket): View
    {
        $bpoUser = CurrentActor::supportBpo();
        $agent = $this->agentFor($bpoUser);
        abort_unless($ticket->assigned_agent_id === $agent->id, 403);

        $ticket->load(['requester', 'approver', 'catalogSubject.supportAgent', 'catalogSubject.itAgent', 'comments']);

        return view('support-bpo.ticket-detail', [
            'role' => 'support-bpo',
            'currentUser' => $this->currentUserPayload($bpoUser),
            'notifications' => $this->notifications($bpoUser),
            'ticket' => $this->presentTicket($ticket, $agent),
            'comments' => $ticket->comments->map(fn (TicketComment $c) => $this->presentComment($c))->values(),
            'timeline' => TicketTimeline::steps($ticket),
            'commentsUrl' => route('support-bpo.tickets.comments.store', $ticket),
            'resolveUrl' => route('support-bpo.tickets.resolve', $ticket),
            'escalateUrl' => route('support-bpo.tickets.escalate', $ticket),
            'ticketsUrl' => route('support-bpo.tickets'),
        ]);
    }

    public function addComment(Request $request, Ticket $ticket): JsonResponse
    {
        $bpoUser = CurrentActor::supportBpo();
        $agent = $this->agentFor($bpoUser);
        abort_unless($ticket->assigned_agent_id === $agent->id, 403);

        $data = $request->validate(['message' => 'required|string|max:3000']);

        $comment = TicketComment::create([
            'ticket_id' => $ticket->id,
            'author_name' => $bpoUser->name,
            'author_role' => 'Support',
            'message' => $data['message'],
        ]);

        return response()->json($this->presentComment($comment), 201);
    }

    public function resolve(Request $request, Ticket $ticket): JsonResponse
    {
        $bpoUser = CurrentActor::supportBpo();
        $agent = $this->agentFor($bpoUser);
        abort_unless($ticket->assigned_agent_id === $agent->id, 403);
        abort_unless($ticket->status !== 'Waiting for Approval', 422, 'Ticket is still awaiting approval.');

        $data = $request->validate(['note' => 'required|string|max:3000']);
        $oldStatus = $ticket->status;

        $ticket->update(['status' => 'Resolved', 'resolved_at' => Carbon::now()]);

        AuditTrail::record($bpoUser, [
            'module' => 'ticket_support',
            'action' => 'resolve',
            'target_type' => 'ticket',
            'target_id' => $ticket->id,
            'target_name' => $ticket->ticket_no,
            'old_value' => ['status' => $oldStatus],
            'new_value' => ['status' => 'Resolved', 'catatan' => $data['note']],
            'description' => "{$bpoUser->name} menutup layanan tiket \"{$ticket->ticket_no}\": {$data['note']}",
        ]);

        if ($ticket->requester) {
            NotificationService::notify(
                $ticket->requester,
                $ticket,
                'ticket_resolved',
                'Tiket Diselesaikan',
                "Tiket {$ticket->ticket_no} telah diselesaikan oleh Tim Support: {$data['note']}"
            );
        }

        return response()->json(['status' => $ticket->fresh()->status]);
    }

    /**
     * "Eskalasi IT" — the real BPO → IT hand-off: reassigns the ticket's
     * assigned_agent_id to the catalog subject's it_agent_id (the same live
     * Level-2 routing ApprovalController/TicketDetailController already read),
     * falling back to the default Support IT agent when the subject has none.
     * The ticket disappears from this BPO queue and appears in Support IT's.
     */
    public function escalate(Request $request, Ticket $ticket): JsonResponse
    {
        $bpoUser = CurrentActor::supportBpo();
        $agent = $this->agentFor($bpoUser);
        abort_unless($ticket->assigned_agent_id === $agent->id, 403);
        abort_unless($ticket->status !== 'Waiting for Approval', 422, 'Ticket is still awaiting approval.');

        $data = $request->validate(['note' => 'required|string|max:3000']);

        $itAgent = SupportAgent::find($ticket->catalogSubject?->it_agent_id)
            ?? $this->agentFor(CurrentActor::support());

        $ticket->update([
            'assigned_agent_id' => $itAgent->id,
            'escalated_at' => Carbon::now(),
            'escalation_note' => $data['note'],
        ]);

        AuditTrail::record($bpoUser, [
            'module' => 'ticket_support',
            'action' => 'escalate',
            'target_type' => 'ticket',
            'target_id' => $ticket->id,
            'target_name' => $ticket->ticket_no,
            'old_value' => ['assigned_agent' => $agent->name],
            'new_value' => ['assigned_agent' => $itAgent->name, 'catatan' => $data['note']],
            'description' => "{$bpoUser->name} mengeskalasi tiket \"{$ticket->ticket_no}\" ke Support IT ({$itAgent->name}): {$data['note']}",
        ]);

        if ($ticket->requester) {
            NotificationService::notify(
                $ticket->requester,
                $ticket,
                'ticket_escalated',
                'Tiket Dieskalasi',
                "Tiket {$ticket->ticket_no} telah dieskalasi ke Tim IT Lanjutan."
            );
        }

        if ($itAgent->user_id) {
            NotificationService::notify(
                User::find($itAgent->user_id),
                $ticket,
                'ticket_incoming_escalation',
                'Tiket Eskalasi Masuk',
                "Tiket {$ticket->ticket_no} dieskalasi dari Support BPO ({$bpoUser->name}): {$data['note']}"
            );
        }

        $fresh = $ticket->fresh();

        return response()->json([
            'escalated' => true,
            'escalatedAt' => $fresh->escalated_at?->format('M j, Y · H:i'),
            'escalationNote' => $fresh->escalation_note,
        ]);
    }

    public function markNotificationRead(TicketNotification $notification): JsonResponse
    {
        $bpoUser = CurrentActor::supportBpo();
        abort_unless($notification->user_id === $bpoUser->id, 403);

        if ($notification->read_at === null) {
            $notification->update(['read_at' => Carbon::now()]);
        }

        return response()->json(['read' => true]);
    }

    public function markAllNotificationsRead(): JsonResponse
    {
        $bpoUser = CurrentActor::supportBpo();

        TicketNotification::where('user_id', $bpoUser->id)->whereNull('read_at')->update(['read_at' => Carbon::now()]);

        return response()->json(['read' => true]);
    }

    private function agentFor(User $bpoUser): SupportAgent
    {
        return SupportAgent::where('user_id', $bpoUser->id)->firstOrFail();
    }

    private function priorityBreakdown(Collection $tickets): array
    {
        $total = $tickets->count();

        return collect(['Critical', 'High', 'Medium', 'Low'])
            ->map(function (string $priority) use ($tickets, $total) {
                $count = $tickets->where('priority', $priority)->count();

                return [
                    'priority' => $priority,
                    'count' => $count,
                    'pct' => $total > 0 ? (int) round($count / $total * 100) : 0,
                    'color' => self::PRIORITY_COLOR[$priority],
                ];
            })
            ->values()
            ->all();
    }

    private function categoryDonut(Collection $tickets): array
    {
        $total = $tickets->count();

        return collect(['Incident', 'Service Request', 'Access Request'])
            ->map(function (string $category) use ($tickets, $total) {
                $count = $tickets->filter(fn (Ticket $t) => ($t->issue_category ?? $t->category) === $category)->count();

                return [
                    'label' => $category,
                    'value' => $total > 0 ? (int) round($count / $total * 100) : 0,
                    'color' => self::CATEGORY_COLOR[$category],
                ];
            })
            ->filter(fn (array $row) => $row['value'] > 0)
            ->values()
            ->all();
    }

    private function slaDonut(Collection $tickets): array
    {
        $activeForDonut = $tickets->whereIn('status', Ticket::ACTIVE_STATUSES)->filter(fn (Ticket $t) => $t->sla_minutes_remaining !== null);
        $onTrack = $activeForDonut->filter(fn (Ticket $t) => $t->sla_kind === 'ontrack')->count();
        $warning = $activeForDonut->filter(fn (Ticket $t) => $t->sla_kind === 'warning')->count();
        $breach = $activeForDonut->filter(fn (Ticket $t) => $t->sla_kind === 'breach')->count();
        $slaTotal = max($onTrack + $warning + $breach, 1);

        return [
            'total' => $onTrack + $warning + $breach,
            'withinSla' => $onTrack + $warning,
            'breach' => $breach,
            'pctWithinSla' => (int) round(($onTrack + $warning) / $slaTotal * 100),
        ];
    }

    private function presentQueueRow(Ticket $t): array
    {
        return [
            'id' => $t->ticket_no,
            'subject' => $t->subject_name ?? $t->title,
            'category' => $t->issue_category ?? $t->category ?? '—',
            'priority' => $t->priority,
            'status' => $t->status,
            'sla' => $t->sla_label,
            'slaKind' => $t->sla_kind,
            'requester' => $t->requester?->name ?? '—',
            'created' => $t->created_at->format('M j, Y'),
            'createdAt' => $t->created_at->toIso8601String(),
            'href' => route('support-bpo.tickets.show', $t),
        ];
    }

    private function presentHistoryRow(Ticket $t): array
    {
        return [
            'id' => $t->ticket_no,
            'title' => $t->title,
            'service' => $t->service_name ?? '—',
            'layanan' => $t->service_name ?? '—',
            'status' => $t->status,
            'priority' => $t->priority,
            'requester' => $t->requester?->name ?? '—',
            'createdAt' => $t->created_at->toIso8601String(),
            'at' => $t->created_at->format('M j, Y · H:i'),
            'href' => route('support-bpo.tickets.show', $t),
        ];
    }

    private function presentTicket(Ticket $t, SupportAgent $agent): array
    {
        $elapsedPct = 0;
        if ($t->sla_minutes_remaining !== null && $t->resolution_time_minutes > 0) {
            $elapsedPct = (int) round((1 - max($t->sla_minutes_remaining, 0) / $t->resolution_time_minutes) * 100);
            $elapsedPct = max(0, min(100, $t->sla_kind === 'breach' ? 100 : $elapsedPct));
        }

        $isDone = in_array($t->status, Ticket::DONE_STATUSES, true);

        return [
            'id' => $t->ticket_no,
            'title' => $t->title,
            'status' => $t->status,
            'priority' => $t->priority,
            'category' => $t->issue_category ?? $t->category ?? '—',
            'service' => trim(($t->service_name ?? '').($t->subcategory_name ? ' · '.$t->subcategory_name : '')) ?: '—',
            'description' => $t->description,
            'attachmentName' => $t->attachment_name,
            'attachmentDownloadUrl' => $t->attachment_path ? Storage::disk('public')->url($t->attachment_path) : null,
            'createdAt' => $t->created_at->format('M j, Y · H:i'),
            'satisfactionRating' => $t->satisfaction_rating,
            'feedbackNote' => $t->feedback_note,
            'requester' => $t->requester ? [
                'name' => $t->requester->name,
                'unit' => $t->requester->unit,
                'email' => $t->requester->email,
            ] : null,
            'sla' => [
                'label' => $isDone ? 'Selesai dalam SLA' : $t->sla_label,
                'kind' => $t->sla_kind,
                'pct' => $elapsedPct,
            ],
            'people' => [
                'requester' => $t->requester ? ['name' => $t->requester->name, 'role' => 'Requester', 'email' => $t->requester->email] : null,
                'pic' => ['name' => $agent->name, 'role' => 'Support '.strtoupper($agent->type), 'email' => $agent->email],
            ],
            'canAct' => ! in_array($t->status, ['Resolved', 'Completed', 'Closed', 'Rejected', 'Waiting for Approval'], true),
            'escalated' => $t->escalated_at !== null,
            'escalatedAt' => $t->escalated_at?->format('M j, Y · H:i'),
            'escalationNote' => $t->escalation_note,
        ];
    }

    private function presentComment(TicketComment $c): array
    {
        return [
            'id' => $c->id,
            'authorName' => $c->author_name,
            'authorRole' => $c->author_role,
            'message' => $c->message,
            'at' => $c->created_at->format('M j · H:i'),
        ];
    }

    private function currentUserPayload(User $bpoUser): array
    {
        return [
            'name' => $bpoUser->name,
            'title' => trim(($bpoUser->jabatan ?? 'Support BPO').' · '.($bpoUser->unit ?? '')),
            'initials' => $this->initials($bpoUser->name),
        ];
    }

    private function initials(string $name): string
    {
        $parts = explode(' ', trim($name));

        return strtoupper(substr($parts[0] ?? '', 0, 1).substr($parts[1] ?? '', 0, 1));
    }

    private function notifications(User $bpoUser): array
    {
        return NotificationService::present($bpoUser, 20, 'support-bpo.tickets.show', 'support-bpo.notifications.read');
    }
}
