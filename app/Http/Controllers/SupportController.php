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
use App\Support\PriorityRegistry;
use App\Support\SupportGreeting;
use App\Support\TicketBroadcast;
use App\Support\TicketDiscussion;
use App\Support\TicketFlow;
use App\Support\TicketPeople;
use App\Support\TicketTimeline;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SupportController extends Controller
{
    // Warna pindah ke PriorityRegistry: prioritas buatan Admin tidak ada di peta
    // tetap mana pun, dan mengambil kuncinya langsung akan melempar
    // "Undefined array key" begitu satu tiket "Urgent" masuk ke donat SLA.

    private const CATEGORY_COLOR = ['Incident' => '#2563eb', 'Service Request' => '#d97706', 'Access Request' => '#10b981'];

    /**
     * Support Dashboard — my active queue, this period's ticket mix, and
     * SLA compliance across whichever Minggu/Bulan/Tahun window is selected.
     */
    public function dashboard(): View
    {
        $supportUser = CurrentActor::support();
        $agent = $this->agentFor($supportUser);

        $myTickets = $this->visibleTicketsQuery($agent)->with('requester')->get();

        $active = $myTickets->whereIn('status', Ticket::ACTIVE_STATUSES);
        $inProgress = $myTickets->whereIn('status', ['Assigned', 'In Progress', 'Waiting for Response']);
        $slaAtRisk = $active->filter(fn (Ticket $t) => in_array($t->sla_kind, ['warning', 'breach'], true));
        $resolvedThisMonth = $myTickets->whereIn('status', Ticket::DONE_STATUSES)
            ->filter(fn (Ticket $t) => $t->resolved_at && $t->resolved_at->isSameMonth(Carbon::now()) && $t->resolved_at->isSameYear(Carbon::now()));

        // Tickets still "Waiting for Approval" already carry my assigned_agent_id
        // (frozen at creation, before an Approver has signed off — see
        // TicketController::store()), so they must be excluded here too, or
        // Support would see and be able to act on a ticket the Approver
        // hasn't released yet.
        $queue = $myTickets->reject(fn (Ticket $t) => in_array($t->status, ['Resolved', 'Completed', 'Closed', ...Ticket::NOT_YET_RELEASED_STATUSES], true));

        $periods = collect(['week' => Carbon::now()->startOfWeek(), 'month' => Carbon::now()->startOfMonth(), 'year' => Carbon::now()->startOfYear()])
            ->map(function (Carbon $cutoff) use ($myTickets) {
                $windowed = $myTickets->filter(fn (Ticket $t) => $t->created_at->greaterThanOrEqualTo($cutoff));

                return [
                    'priority' => $this->priorityBreakdown($windowed),
                    'category' => $this->categoryDonut($windowed),
                    'sla' => $this->slaDonut($windowed),
                ];
            });

        return view('support.dashboard', [
            'role' => 'support',
            'currentUser' => $this->currentUserPayload($supportUser),
            'notifications' => $this->notifications($supportUser),
            'stats' => [
                'assignedToMe' => $active->where('status', 'Open')->count(),
                'inProgress' => $inProgress->count(),
                'slaAtRisk' => $slaAtRisk->count(),
                'resolvedThisMonth' => $resolvedThisMonth->count(),
            ],
            'periods' => $periods,
            'queue' => $queue->map(fn (Ticket $t) => $this->presentQueueRow($t))->values(),
            'myRating' => $this->myRating($myTickets),
        ]);
    }

    /**
     * "My Tickets" — every ticket ever actually released to me, bucketed by
     * current status, with search/service/period filters handled
     * client-side. Tickets still sitting with an Approver (or never
     * submitted at all) are excluded — same reasoning as dashboard()'s
     * $queue — so a ticket only shows up here once it has genuinely landed
     * in Support's hands.
     */
    public function myTickets(): View
    {
        $supportUser = CurrentActor::support();
        $agent = $this->agentFor($supportUser);

        $tickets = $this->visibleTicketsQuery($agent)
            ->whereNotIn('status', Ticket::NOT_YET_RELEASED_STATUSES)
            ->with('requester')->latest('created_at')->get();

        $counts = [
            'Total' => $tickets->count(),
            'Open' => $tickets->where('status', 'Open')->count(),
            'In Progress' => $tickets->whereIn('status', ['Assigned', 'In Progress', 'Waiting for Response'])->count(),
            'Resolved' => $tickets->where('status', 'Resolved')->count(),
            'Closed' => $tickets->whereIn('status', ['Closed', 'Completed'])->count(),
        ];

        return view('support.tickets', [
            'role' => 'support',
            'currentUser' => $this->currentUserPayload($supportUser),
            'notifications' => $this->notifications($supportUser),
            'counts' => $counts,
            'rows' => $tickets->map(fn (Ticket $t) => $this->presentHistoryRow($t))->values(),
        ]);
    }

    public function show(Ticket $ticket): View
    {
        $supportUser = CurrentActor::support();
        $agent = $this->agentFor($supportUser);
        abort_unless(TicketBroadcast::canAct($ticket, $agent), 403);
        abort_if(in_array($ticket->status, Ticket::NOT_YET_RELEASED_STATUSES, true), 403, 'Ticket belum diteruskan ke Support.');

        $ticket->load(['requester', 'approver', 'catalogSubject.supportAgent', 'catalogSubject.itAgent', 'comments', 'attachments']);

        return view('support.ticket-detail', [
            'role' => 'support',
            'currentUser' => $this->currentUserPayload($supportUser),
            'notifications' => $this->notifications($supportUser),
            'ticket' => $this->presentTicket($ticket),
            'comments' => $ticket->comments->map(fn (TicketComment $c) => TicketDiscussion::present($c))->values(),
            'timeline' => TicketTimeline::steps($ticket),
            'flow' => TicketFlow::stages($ticket),
            'dataUrl' => route('support.tickets.data', $ticket),
            'commentsUrl' => route('support.tickets.comments.store', $ticket),
            'startUrl' => route('support.tickets.start', $ticket),
            'resolveUrl' => route('support.tickets.resolve', $ticket),
            'returnUrl' => route('support.tickets.return', $ticket),
            'ticketsUrl' => route('support.tickets'),
        ]);
    }

    /**
     * JSON re-fetch of this same ticket — lets the detail page pull a fresh
     * status/timeline in place after "Service Closed" instead of a full
     * `window.location.reload()`.
     */
    public function data(Ticket $ticket): JsonResponse
    {
        $supportUser = CurrentActor::support();
        $agent = $this->agentFor($supportUser);
        abort_unless(TicketBroadcast::canAct($ticket, $agent), 403);
        abort_if(in_array($ticket->status, Ticket::NOT_YET_RELEASED_STATUSES, true), 403, 'Ticket belum diteruskan ke Support.');

        $ticket->load(['requester', 'approver', 'catalogSubject.supportAgent', 'catalogSubject.itAgent', 'comments', 'attachments']);

        return response()->json([
            'ticket' => $this->presentTicket($ticket),
            'comments' => $ticket->comments->map(fn (TicketComment $c) => TicketDiscussion::present($c))->values(),
            'timeline' => TicketTimeline::steps($ticket),
            'flow' => TicketFlow::stages($ticket),
        ]);
    }

    public function addComment(Request $request, Ticket $ticket): JsonResponse
    {
        $supportUser = CurrentActor::support();
        $agent = $this->agentFor($supportUser);
        abort_unless(TicketBroadcast::canAct($ticket, $agent), 403);
        abort_if(in_array($ticket->status, Ticket::NOT_YET_RELEASED_STATUSES, true), 403, 'Ticket belum diteruskan ke Support.');
        abort_if(in_array($ticket->status, ['Closed', 'Rejected'], true), 422, 'Diskusi tiket ini sudah ditutup.');

        TicketBroadcast::claimIfUnclaimed($ticket, $supportUser, $agent);

        $data = $request->validate(TicketDiscussion::rules());

        $comment = TicketDiscussion::store($ticket, $supportUser, 'Support', 'Support IT', $data, $request->file('file'));

        // First reply from Support is what stops the response clock.
        $ticket->markFirstResponse($comment->created_at);

        return response()->json(TicketDiscussion::present($comment), 201);
    }

    /**
     * "Kerjakan Sekarang" — the agent's explicit acknowledgement that they're
     * starting on an Open ticket, from the popup shown when they land on its
     * detail page. Only ever fires from Open: once work has started there's
     * no "start" left to record, and a ticket that isn't Open yet has no
     * agent action to take at all.
     */
    public function start(Ticket $ticket): JsonResponse
    {
        $supportUser = CurrentActor::support();
        $agent = $this->agentFor($supportUser);
        abort_unless(TicketBroadcast::canAct($ticket, $agent), 403);
        abort_unless($ticket->status === 'Open', 422, 'Tiket ini tidak bisa dimulai dari status saat ini.');

        TicketBroadcast::claimIfUnclaimed($ticket, $supportUser, $agent);

        /*
         | Satu transaksi untuk tiga tulisan: status, sapaan otomatis, dan jejak
         | audit. Sebelumnya statusnya ditulis di luar transaksi, jadi kalau
         | penulisan audit gagal tiketnya sudah terlanjur "In Progress" tanpa
         | catatan apa pun — itu yang membuat SR-2026-0035 dan INC-2026-0016
         | berpindah status padahal layarnya menampilkan error. Dengan komentar
         | otomatis ikut ditulis di sini, celah itu jadi lebih mahal untuk
         | dibiarkan: separuh gagal berarti requester melihat sapaan untuk
         | pekerjaan yang tidak pernah dimulai.
         */
        DB::transaction(function () use ($ticket, $supportUser) {
            // Starting work is the agent picking the ticket up — same reasoning
            // resolve()/returnTicket() use: it answers the SLA response clock
            // even though nothing was posted in the discussion thread.
            $ticket->markFirstResponse();

            $ticket->update(['status' => 'In Progress']);

            // Sapaan otomatis di forum diskusi + lonceng untuk requester, supaya
            // ia tahu tiketnya sudah dipegang orang tanpa harus membuka halaman
            // dan membaca perubahan status.
            SupportGreeting::post($ticket, $supportUser);

            AuditTrail::record($supportUser, [
                'module' => 'ticket_support',
                'action' => 'start',
                'target_type' => 'ticket',
                'target_id' => $ticket->id,
                'target_name' => $ticket->ticket_no,
                'old_value' => ['status' => 'Open'],
                'new_value' => ['status' => 'In Progress'],
                'description' => "{$supportUser->name} mulai mengerjakan tiket \"{$ticket->ticket_no}\".",
            ]);
        });

        return response()->json(['status' => $ticket->fresh()->status]);
    }

    /**
     * "Service Closed" — note is mandatory (re-validated server-side, not
     * just disabled in the UI), moves the ticket to Resolved, and notifies
     * the requester the same way an Approver decision does.
     */
    public function resolve(Request $request, Ticket $ticket): JsonResponse
    {
        $supportUser = CurrentActor::support();
        $agent = $this->agentFor($supportUser);
        abort_unless(TicketBroadcast::canAct($ticket, $agent), 403);
        abort_if(in_array($ticket->status, Ticket::NOT_YET_RELEASED_STATUSES, true), 422, 'Ticket belum diteruskan ke Support.');

        TicketBroadcast::claimIfUnclaimed($ticket, $supportUser, $agent);

        $data = $request->validate(['note' => 'required|string|max:3000']);
        $oldStatus = $ticket->status;

        // Any action Support takes on the ticket is a response, not just a
        // comment — a support agent who resolves/returns without ever
        // replying in the discussion thread still answered within the SLA
        // window, so the response clock must stop here too.
        $ticket->markFirstResponse();

        // Clears any "Belum" banner from a previous round — this resolution
        // is Support's answer to it, and a fresh reopen would set new note.
        $ticket->update(['status' => 'Resolved', 'resolved_at' => Carbon::now(), 'reopen_note' => null, 'reopen_at' => null]);

        AuditTrail::record($supportUser, [
            'module' => 'ticket_support',
            'action' => 'resolve',
            'target_type' => 'ticket',
            'target_id' => $ticket->id,
            'target_name' => $ticket->ticket_no,
            'old_value' => ['status' => $oldStatus],
            'new_value' => ['status' => 'Resolved', 'catatan' => $data['note']],
            'description' => "{$supportUser->name} menutup layanan tiket \"{$ticket->ticket_no}\": {$data['note']}",
        ]);

        if ($ticket->requester) {
            NotificationService::notify(
                $ticket->requester,
                'requester',
                $ticket,
                'ticket_resolved',
                'Tiket Diselesaikan',
                "Tiket {$ticket->ticket_no} telah diselesaikan oleh Tim Support: {$data['note']}"
            );
        }

        return response()->json(['status' => $ticket->fresh()->status]);
    }

    /**
     * Sends a ticket back to the Requester for clarification/revision
     * instead of resolving it — same "Returned" status the Approver's
     * revision-request decision already uses, so the requester's Edit &
     * Resubmit flow (NewTicketModal/TicketController::update()) picks it
     * up unchanged. Unlike resolve(), the ticket leaves this queue (see
     * Ticket::NOT_YET_RELEASED_STATUSES), so the caller must navigate away
     * rather than re-fetch dataUrl — same reasoning as escalate().
     */
    public function returnTicket(Request $request, Ticket $ticket): JsonResponse
    {
        $supportUser = CurrentActor::support();
        $agent = $this->agentFor($supportUser);
        abort_unless(TicketBroadcast::canAct($ticket, $agent), 403);
        abort_if(in_array($ticket->status, Ticket::NOT_YET_RELEASED_STATUSES, true), 422, 'Ticket belum diteruskan ke Support.');

        TicketBroadcast::claimIfUnclaimed($ticket, $supportUser, $agent);

        $data = $request->validate(['note' => 'required|string|max:3000']);
        $oldStatus = $ticket->status;

        // Same reasoning as resolve(): returning the ticket is a response too.
        $ticket->markFirstResponse();

        $ticket->update(['status' => 'Returned']);

        AuditTrail::record($supportUser, [
            'module' => 'ticket_support',
            'action' => 'return',
            'target_type' => 'ticket',
            'target_id' => $ticket->id,
            'target_name' => $ticket->ticket_no,
            'old_value' => ['status' => $oldStatus],
            'new_value' => ['status' => 'Returned', 'catatan' => $data['note']],
            'description' => "{$supportUser->name} mengembalikan tiket \"{$ticket->ticket_no}\" ke requester: {$data['note']}",
        ]);

        if ($ticket->requester) {
            NotificationService::notify(
                $ticket->requester,
                'requester',
                $ticket,
                'ticket_reopened',
                'Tiket Dikembalikan',
                "Tiket {$ticket->ticket_no} dikembalikan oleh Tim Support untuk direvisi: {$data['note']}"
            );
        }

        return response()->json(['status' => $ticket->fresh()->status]);
    }

    public function markNotificationRead(TicketNotification $notification): JsonResponse
    {
        $supportUser = CurrentActor::support();
        abort_unless($notification->user_id === $supportUser->id, 403);

        if ($notification->read_at === null) {
            $notification->update(['read_at' => Carbon::now()]);
        }

        return response()->json(['read' => true]);
    }

    public function markAllNotificationsRead(): JsonResponse
    {
        $supportUser = CurrentActor::support();

        TicketNotification::where('user_id', $supportUser->id)->where('role', 'support')->whereNull('read_at')->update(['read_at' => Carbon::now()]);

        return response()->json(['read' => true]);
    }

    /**
     * Sama seperti SupportBpoController::agentFor() — orang yang dobel
     * peran (BPO & IT) punya dua baris SupportAgent untuk user_id yang
     * sama, jadi harus difilter type='it' di sini supaya tidak kebetulan
     * mengambil baris BPO-nya.
     */
    private function agentFor(User $supportUser): SupportAgent
    {
        return SupportAgent::where('user_id', $supportUser->id)->where('type', 'it')->firstOrFail();
    }

    /**
     * Tiket yang sudah jadi milikku, DITAMBAH tiket "Lainnya" hasil eskalasi
     * BPO yang belum diklaim PIC IT manapun tapi aku salah satu PIC IT
     * Layanannya (lihat TicketBroadcast) — supaya tiket itu kelihatan di
     * dashboard/My Tickets sebelum ada yang membalasnya, bukan cuma setelah
     * diklaim. escalated_at wajib terisi: tiket "Lainnya" yang BELUM
     * dieskalasi masih giliran BPO, bukan IT.
     */
    private function visibleTicketsQuery(SupportAgent $agent)
    {
        $broadcastServiceIds = $agent->itServiceIds();

        return Ticket::where(function ($q) use ($agent, $broadcastServiceIds) {
            $q->where('assigned_agent_id', $agent->id);

            if ($broadcastServiceIds->isNotEmpty()) {
                $q->orWhere(function ($q2) use ($broadcastServiceIds) {
                    $q2->whereNull('assigned_agent_id')
                        ->whereNull('catalog_subject_id')
                        ->whereNotNull('escalated_at')
                        ->whereIn('service_catalog_service_id', $broadcastServiceIds);
                });
            }
        });
    }

    /**
     * My own star rating — averaged from Ticket::satisfaction_rating across
     * every ticket ever assigned to me, the same value the Requester's
     * post-close feedback writes (see TicketDetailController::close()).
     * Excludes any rating Admin has switched off (rating_active = false —
     * see Admin\TicketManagementController::toggleRating()) so a disputed
     * or mistaken rating doesn't drag this average down while it's disabled.
     */
    private function myRating(Collection $myTickets): array
    {
        $rated = $myTickets->whereNotNull('satisfaction_rating')->where('rating_active', true);

        return [
            'average' => $rated->isNotEmpty() ? round($rated->avg('satisfaction_rating'), 1) : null,
            'count' => $rated->count(),
        ];
    }

    private function priorityBreakdown(Collection $tickets): array
    {
        $total = $tickets->count();

        return collect(PriorityRegistry::all())
            ->map(function (string $priority) use ($tickets, $total) {
                $count = $tickets->where('priority', $priority)->count();

                return [
                    'priority' => $priority,
                    'count' => $count,
                    'pct' => $total > 0 ? (int) round($count / $total * 100) : 0,
                    'color' => PriorityRegistry::colorFor($priority),
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
            'created' => $t->created_at->translatedFormat('j M Y'),
            'createdAt' => $t->created_at->toIso8601String(),
            'href' => route('support.tickets.show', $t),
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
            'at' => $t->created_at->translatedFormat('j M Y · H:i'),
            'href' => route('support.tickets.show', $t),
        ];
    }

    /**
     * Tidak menerima agent yang sedang melihat — lihat alasan lengkapnya di
     * SupportBpoController::presentTicket(): 'pic' harus pemilik tiketnya,
     * bukan orang yang kebetulan membuka halamannya.
     */
    private function presentTicket(Ticket $t): array
    {
        $isDone = in_array($t->status, Ticket::DONE_STATUSES, true);

        return [
            'id' => $t->ticket_no,
            'title' => $t->title,
            'status' => $t->status,
            'priority' => $t->priority,
            'category' => $t->issue_category ?? $t->category ?? '—',
            'service' => trim(($t->service_name ?? '').($t->subcategory_name ? ' · '.$t->subcategory_name : '')) ?: '—',
            'description' => $t->description,
            'attachments' => $t->attachmentsPayload(),
            'createdAt' => $t->created_at->translatedFormat('j M Y · H:i'),
            'satisfactionRating' => $t->satisfaction_rating,
            'feedbackNote' => $t->feedback_note,
            'ratingActive' => (bool) $t->rating_active,
            'reopenNote' => $t->reopen_note ? ['note' => $t->reopen_note, 'at' => $t->reopen_at->translatedFormat('j M Y · H:i')] : null,
            'requester' => $t->requester ? [
                'name' => $t->requester->name,
                'unit' => $t->requester->unit,
                'email' => $t->requester->email,
            ] : null,
            'sla' => [
                ...$t->slaPayload(),
                'label' => $isDone && $t->sla_kind !== 'breach' ? 'Selesai dalam SLA' : $t->sla_label,
            ],
            'people' => [
                'requester' => $t->requester ? ['name' => $t->requester->name, 'role' => 'Requester', 'email' => $t->requester->email] : null,
                'approver' => $t->approver ? ['name' => $t->approver->name, 'role' => 'Approver · '.$t->approver->jabatan, 'email' => $t->approver->email] : null,
                'pic' => $t->assignedAgent
                    ? ['name' => $t->assignedAgent->name, 'role' => 'Support '.strtoupper($t->assignedAgent->type), 'email' => $t->assignedAgent->email]
                    : null,
                'support' => TicketPeople::supportAgents($t),
            ],
            'canAct' => ! in_array($t->status, ['Resolved', 'Completed', 'Closed', 'Rejected', 'Waiting for Approval'], true),
            'escalated' => $t->escalated_at !== null,
            'escalatedAt' => $t->escalated_at?->translatedFormat('j M Y · H:i'),
            'escalationNote' => $t->escalation_note,
        ];
    }

    private function currentUserPayload(User $supportUser): array
    {
        return [
            'name' => $supportUser->name,
            'title' => trim(($supportUser->jabatan ?? 'Support IT').' · '.($supportUser->unit ?? '')),
            'initials' => $this->initials($supportUser->name),
        ];
    }

    private function initials(string $name): string
    {
        $parts = explode(' ', trim($name));

        return strtoupper(substr($parts[0] ?? '', 0, 1).substr($parts[1] ?? '', 0, 1));
    }

    private function notifications(User $supportUser): array
    {
        return NotificationService::present($supportUser, 'support', 20, 'support.tickets.show', 'support.notifications.read');
    }
}
