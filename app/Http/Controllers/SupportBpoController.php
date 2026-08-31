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

/**
 * "Support BPO" — the first-line workspace (Denny Firmansyah). Structurally
 * identical to SupportController (the IT workspace), except BPO also has an
 * "Eskalasi IT" action that hands the ticket off to the Support IT queue —
 * see escalate() below.
 */
class SupportBpoController extends Controller
{
    // Lihat SupportController: warna prioritas kini dari PriorityRegistry,
    // supaya prioritas buatan Admin tidak menabrak "Undefined array key".

    private const CATEGORY_COLOR = ['Incident' => '#2563eb', 'Service Request' => '#d97706', 'Access Request' => '#10b981'];

    public function dashboard(): View
    {
        $bpoUser = CurrentActor::supportBpo();
        $agent = $this->agentFor($bpoUser);

        $myTickets = $this->visibleTicketsQuery($agent)->with('requester')->get();

        $active = $myTickets->whereIn('status', Ticket::ACTIVE_STATUSES);
        $inProgress = $myTickets->whereIn('status', ['Assigned', 'In Progress', 'Waiting for Response']);
        $slaAtRisk = $active->filter(fn (Ticket $t) => in_array($t->sla_kind, ['warning', 'breach'], true));
        $resolvedThisMonth = $myTickets->whereIn('status', Ticket::DONE_STATUSES)
            ->filter(fn (Ticket $t) => $t->resolved_at && $t->resolved_at->isSameMonth(Carbon::now()) && $t->resolved_at->isSameYear(Carbon::now()));

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
            'myRating' => $this->myRating($myTickets),
        ]);
    }

    /**
     * "My Tickets" — every ticket ever actually released to me. Tickets
     * still sitting with an Approver (or never submitted at all) are
     * excluded — same reasoning as dashboard()'s $queue — so a ticket only
     * shows up here once it has genuinely landed in Support's hands.
     */
    public function myTickets(): View
    {
        $bpoUser = CurrentActor::supportBpo();
        $agent = $this->agentFor($bpoUser);

        $tickets = $this->visibleTicketsQuery($agent, includeEscalatedAway: true)
            ->whereNotIn('status', Ticket::NOT_YET_RELEASED_STATUSES)
            ->with('requester')->latest('created_at')->get();

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
        abort_unless($this->canView($ticket, $agent), 403);
        abort_if(in_array($ticket->status, Ticket::NOT_YET_RELEASED_STATUSES, true), 403, 'Ticket belum diteruskan ke Support.');

        $ticket->load(['requester', 'approver', 'catalogSubject.supportAgent', 'catalogSubject.itAgent', 'comments', 'attachments']);

        return view('support-bpo.ticket-detail', [
            'role' => 'support-bpo',
            'currentUser' => $this->currentUserPayload($bpoUser),
            // Lihat SupportController: identitas pembaca untuk perataan
            // gelembung Forum Diskusi, terpisah dari payload header.
            'viewer' => ['id' => $bpoUser->id, 'name' => $bpoUser->name],
            'notifications' => $this->notifications($bpoUser),
            'ticket' => $this->presentTicket($ticket, $agent),
            'comments' => $ticket->comments->map(fn (TicketComment $c) => TicketDiscussion::present($c))->values(),
            'timeline' => TicketTimeline::steps($ticket),
            'flow' => TicketFlow::stages($ticket),
            'dataUrl' => route('support-bpo.tickets.data', $ticket),
            'commentsUrl' => route('support-bpo.tickets.comments.store', $ticket),
            'startUrl' => route('support-bpo.tickets.start', $ticket),
            'resolveUrl' => route('support-bpo.tickets.resolve', $ticket),
            'escalateUrl' => route('support-bpo.tickets.escalate', $ticket),
            'returnUrl' => route('support-bpo.tickets.return', $ticket),
            'ticketsUrl' => route('support-bpo.tickets'),
        ]);
    }

    /**
     * JSON re-fetch of this same ticket — lets the detail page pull a fresh
     * status/timeline in place after "Service Closed" instead of a full
     * `window.location.reload()`.
     */
    public function data(Ticket $ticket): JsonResponse
    {
        $bpoUser = CurrentActor::supportBpo();
        $agent = $this->agentFor($bpoUser);
        abort_unless($this->canView($ticket, $agent), 403);
        abort_if(in_array($ticket->status, Ticket::NOT_YET_RELEASED_STATUSES, true), 403, 'Ticket belum diteruskan ke Support.');

        $ticket->load(['requester', 'approver', 'catalogSubject.supportAgent', 'catalogSubject.itAgent', 'comments', 'attachments']);

        return response()->json([
            'ticket' => $this->presentTicket($ticket, $agent),
            'comments' => $ticket->comments->map(fn (TicketComment $c) => TicketDiscussion::present($c))->values(),
            'timeline' => TicketTimeline::steps($ticket),
            'flow' => TicketFlow::stages($ticket),
        ]);
    }

    /**
     * A BPO agent who escalated this ticket to IT can still open it and
     * chat in the discussion thread — but not start/resolve/escalate/return
     * it, so those actions stay gated on the stricter canManage() below.
     * Mirrors the includeEscalatedAway option on visibleTicketsQuery(),
     * which is what keeps the ticket listed for them in the first place.
     */
    private function canView(Ticket $ticket, SupportAgent $agent): bool
    {
        return $this->canManage($ticket, $agent) || $ticket->escalated_by_agent_id === $agent->id;
    }

    /**
     * Wewenang MENGERJAKAN tiket ini di portal BPO — bukan sekadar melihatnya.
     *
     * `escalated_at` yang memutuskan lebih dulu, dan itu tidak bisa diserahkan
     * ke TicketBroadcast::canAct() sendirian: canAct() sadar-tahap, dan sesudah
     * eskalasi tahapnya IT — eligiblePics()-nya berpindah ke daftar PIC *IT*.
     * Untuk tiket "Lainnya" yang dieskalasi secara broadcast (assigned_agent_id
     * kembali null), canAct() lalu menimbang agent BPO ini terhadap daftar PIC
     * IT. Orang dobel peran (BPO & IT di Layanan yang sama — pola nyata, lihat
     * TicketEscalateDualRoleTest) ada di daftar itu, jadi layar BPO-nya kembali
     * menganggap tiket yang baru saja ia serahkan sebagai miliknya: popup
     * "Mulai kerjakan tiket ini?" muncul lagi, dan tombolnya benar-benar
     * menarik tiket itu balik dari IT.
     *
     * Daftar PIC IT tidak boleh jadi sumber wewenang di portal BPO. Giliran BPO
     * selesai begitu tiketnya dieskalasi — syarat yang sama persis sudah
     * dipegang visibleTicketsQuery() lewat whereNull('escalated_at'), dan
     * `escalated_at` tidak pernah dikosongkan lagi setelah terisi.
     */
    private function canManage(Ticket $ticket, SupportAgent $agent): bool
    {
        if ($ticket->escalated_at !== null) {
            return false;
        }

        return TicketBroadcast::canAct($ticket, $agent);
    }

    public function addComment(Request $request, Ticket $ticket): JsonResponse
    {
        $bpoUser = CurrentActor::supportBpo();
        $agent = $this->agentFor($bpoUser);
        abort_unless($this->canView($ticket, $agent), 403);
        abort_if(in_array($ticket->status, Ticket::NOT_YET_RELEASED_STATUSES, true), 403, 'Ticket belum diteruskan ke Support.');
        abort_if(in_array($ticket->status, ['Closed', 'Rejected'], true), 422, 'Diskusi tiket ini sudah ditutup.');

        // Klaimnya ikut syarat canManage(), bukan cuma canView(): sesudah
        // eskalasi, tiket broadcast kembali `assigned_agent_id = null` dan
        // claimIfUnclaimed() menimbang agent ini terhadap daftar PIC IT. Untuk
        // orang dobel peran, sekadar menambahkan komentar akan menuliskan baris
        // BPO-nya ke assigned_agent_id — tiket yang sudah diserahkan tertarik
        // balik ke portal BPO dan lenyap dari antrean IT, tanpa siapa pun
        // menekan tombol apa pun.
        if ($this->canManage($ticket, $agent)) {
            TicketBroadcast::claimIfUnclaimed($ticket, $bpoUser, $agent);
        }

        $data = $request->validate(TicketDiscussion::rules());

        $comment = TicketDiscussion::store($ticket, $bpoUser, 'Support', 'Support BPO', $data, $request->file('file'));

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
        $bpoUser = CurrentActor::supportBpo();
        $agent = $this->agentFor($bpoUser);
        abort_unless($this->canManage($ticket, $agent), 403);
        abort_unless($ticket->status === 'Open', 422, 'Tiket ini tidak bisa dimulai dari status saat ini.');

        TicketBroadcast::claimIfUnclaimed($ticket, $bpoUser, $agent);

        // Sama persis dengan SupportController::start() — satu transaksi untuk
        // status, sapaan otomatis, dan jejak audit. Requester tidak boleh
        // menerima sambutan yang berbeda hanya karena tiketnya kebetulan
        // dipegang BPO, bukan IT.
        DB::transaction(function () use ($ticket, $bpoUser) {
            // Starting work is the agent picking the ticket up — same reasoning
            // resolve()/escalate()/returnTicket() use: it answers the SLA
            // response clock even though nothing was posted in the discussion.
            $ticket->markFirstResponse();

            $ticket->update(['status' => 'In Progress']);

            SupportGreeting::post($ticket, $bpoUser);

            AuditTrail::record($bpoUser, [
                'module' => 'ticket_support',
                'action' => 'start',
                'target_type' => 'ticket',
                'target_id' => $ticket->id,
                'target_name' => $ticket->ticket_no,
                'old_value' => ['status' => 'Open'],
                'new_value' => ['status' => 'In Progress'],
                'description' => "{$bpoUser->name} mulai mengerjakan tiket \"{$ticket->ticket_no}\".",
            ]);
        });

        return response()->json(['status' => $ticket->fresh()->status]);
    }

    public function resolve(Request $request, Ticket $ticket): JsonResponse
    {
        $bpoUser = CurrentActor::supportBpo();
        $agent = $this->agentFor($bpoUser);
        abort_unless($this->canManage($ticket, $agent), 403);
        abort_if(in_array($ticket->status, Ticket::NOT_YET_RELEASED_STATUSES, true), 422, 'Ticket belum diteruskan ke Support.');

        TicketBroadcast::claimIfUnclaimed($ticket, $bpoUser, $agent);

        $data = $request->validate(['note' => 'required|string|max:3000']);
        $oldStatus = $ticket->status;

        // Any action Support takes on the ticket is a response, not just a
        // comment — resolving/escalating/returning without ever replying in
        // the discussion thread still answered within the SLA window.
        $ticket->markFirstResponse();

        // Clears any "Belum" banner from a previous round — this resolution
        // is Support's answer to it, and a fresh reopen would set new note.
        $ticket->update(['status' => 'Resolved', 'resolved_at' => Carbon::now(), 'reopen_note' => null, 'reopen_at' => null]);

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
     * "Eskalasi IT" — the real BPO → IT hand-off.
     *
     * Tiket katalog biasa (Subject-nya jelas) tetap ke SATU it_agent_id
     * spesifik, sama seperti sebelumnya. Tiket "Lainnya" (catalog_subject_id
     * null — tidak ada Subject yang menentukan satu it_agent_id) TIDAK lagi
     * menebak satu agent IT — dia broadcast ke SEMUA PIC IT Layanan ini,
     * pola yang sama persis dengan broadcast BPO di awal tiket ini dibuat
     * (lihat TicketBroadcast::escalateBroadcast()). Siapa pun IT yang
     * pertama bertindak otomatis mengklaimnya; PIC IT lain diberi tahu.
     */
    public function escalate(Request $request, Ticket $ticket): JsonResponse
    {
        $bpoUser = CurrentActor::supportBpo();
        $agent = $this->agentFor($bpoUser);
        abort_unless($this->canManage($ticket, $agent), 403);
        abort_if(in_array($ticket->status, Ticket::NOT_YET_RELEASED_STATUSES, true), 422, 'Ticket belum diteruskan ke Support.');

        // Level ditentukan oleh Subjek-nya di service catalog: Level 2 berarti
        // BPO dan IT menangani berurutan (BPO boleh lempar ke IT), Level 1
        // berarti BPO-only — tidak ada IT yang seharusnya menerimanya, jadi
        // eskalasi ditolak di sini alih-alih jatuh ke "agent IT aktif mana
        // pun" seperti sebelumnya. Tiket "Lainnya" (catalog_subject_id null,
        // tidak ada Level) tidak kena aturan ini — jalur broadcast di bawah
        // tetap berlaku untuknya.
        abort_if(
            $ticket->catalogSubject && (int) $ticket->catalogSubject->support_level === 1,
            422,
            'Subjek tiket ini Level 1 (ditangani Support BPO saja) dan tidak bisa dieskalasikan ke Support IT.'
        );

        TicketBroadcast::claimIfUnclaimed($ticket, $bpoUser, $agent);

        $data = $request->validate(['note' => 'required|string|max:3000']);

        // Same reasoning as resolve(): deciding this needs IT is a response too.
        $ticket->markFirstResponse();

        $subjectItAgent = SupportAgent::find($ticket->catalogSubject?->it_agent_id);

        // Layanan yang belum punya PIC IT sama sekali TIDAK boleh masuk jalur
        // broadcast: tiketnya akan berakhir tanpa pemilik dan tanpa satu pun
        // orang yang bisa melihatnya. Biarkan jatuh ke jalur tunggal di bawah
        // supaya tetap ada yang menerima.
        if (! $subjectItAgent && $ticket->catalog_subject_id === null && TicketBroadcast::itPics($ticket)->isNotEmpty()) {
            TicketBroadcast::escalateBroadcast($ticket, $bpoUser, $agent, $data['note']);

            // Support IT mulai dari nol, jadi jam SLA diperpanjang sama
            // seperti jalur single-target di bawah — tim IT tidak boleh
            // mewarisi waktu yang sudah habis dipakai BPO.
            $ticket->grantEscalationExtension();

            if ($ticket->requester) {
                NotificationService::notify(
                    $ticket->requester,
                    'requester',
                    $ticket,
                    'ticket_escalated',
                    'Tiket Dieskalasi',
                    "Tiket {$ticket->ticket_no} telah dieskalasi ke Tim IT Lanjutan."
                );
            }

            $fresh = $ticket->fresh();

            return response()->json([
                'escalated' => true,
                'escalatedAt' => $fresh->escalated_at?->translatedFormat('j M Y · H:i'),
                'escalationNote' => $fresh->escalation_note,
            ]);
        }

        /*
         | Kalau sampai sini, Subject-nya Level 2 (Level 1 sudah ditolak di
         | atas) tapi it_agent_id-nya kosong — data katalog lama yang belum
         | lengkap PIC IT-nya (assertPicAssigned() mencegah ini untuk data
         | baru, tapi baris lama bisa saja belum dibereskan). Jatuh ke agent
         | IT aktif mana pun, diurutkan oleh id supaya deterministik, alih-
         | alih tiket macet tanpa penerima.
         */
        $itAgent = $subjectItAgent
            ?? SupportAgent::where('type', 'it')->where('is_active', true)->orderBy('id')->first();

        abort_if($itAgent === null, 422, 'Tidak ada agent IT aktif untuk menerima eskalasi ini.');

        // status kembali "Open" dan jam respons IT mulai dari nol — alasan
        // lengkapnya di TicketBroadcast::escalateBroadcast(). Berlaku sama di
        // sini: yang berpindah bukan cuma pemiliknya, tapi tahapnya.
        $ticket->update([
            'assigned_agent_id' => $itAgent->id,
            'status' => 'Open',
            'escalated_at' => Carbon::now(),
            'escalation_note' => $data['note'],
            'escalated_by_agent_id' => $agent->id,
        ]);

        $ticket->startItResponseClock();

        // Support IT starts this case from scratch, so the resolution clock is
        // extended rather than left to breach on time they never had.
        $grantedMinutes = $ticket->grantEscalationExtension();
        $extensionNote = $grantedMinutes > 0
            ? " Batas SLA diperpanjang {$grantedMinutes} menit."
            : '';

        AuditTrail::record($bpoUser, [
            'module' => 'ticket_support',
            'action' => 'escalate',
            'target_type' => 'ticket',
            'target_id' => $ticket->id,
            'target_name' => $ticket->ticket_no,
            'old_value' => ['assigned_agent' => $agent->name],
            'new_value' => [
                'assigned_agent' => $itAgent->name,
                'catatan' => $data['note'],
                'sla_extension_minutes' => $grantedMinutes,
            ],
            'description' => "{$bpoUser->name} mengeskalasi tiket \"{$ticket->ticket_no}\" ke Support IT ({$itAgent->name}): {$data['note']}{$extensionNote}",
        ]);

        if ($ticket->requester) {
            NotificationService::notify(
                $ticket->requester,
                'requester',
                $ticket,
                'ticket_escalated',
                'Tiket Dieskalasi',
                "Tiket {$ticket->ticket_no} telah dieskalasi ke Tim IT Lanjutan."
            );
        }

        if ($itAgent->user_id) {
            NotificationService::notify(
                User::find($itAgent->user_id),
                NotificationService::roleForAgent($itAgent),
                $ticket,
                'ticket_incoming_escalation',
                'Tiket Eskalasi Masuk',
                "Tiket {$ticket->ticket_no} dieskalasi dari Support BPO ({$bpoUser->name}): {$data['note']}"
            );
        }

        $fresh = $ticket->fresh();

        return response()->json([
            'escalated' => true,
            'escalatedAt' => $fresh->escalated_at?->translatedFormat('j M Y · H:i'),
            'escalationNote' => $fresh->escalation_note,
        ]);
    }

    /**
     * Sends a ticket back to the Requester for clarification/revision
     * instead of resolving or escalating it — see
     * SupportController::returnTicket() for the full reasoning (same
     * "Returned" status the Approver's revision-request decision uses, and
     * the ticket leaves this queue so the caller must navigate away rather
     * than re-fetch dataUrl, same as escalate()).
     */
    public function returnTicket(Request $request, Ticket $ticket): JsonResponse
    {
        $bpoUser = CurrentActor::supportBpo();
        $agent = $this->agentFor($bpoUser);
        abort_unless($this->canManage($ticket, $agent), 403);
        abort_if(in_array($ticket->status, Ticket::NOT_YET_RELEASED_STATUSES, true), 422, 'Ticket belum diteruskan ke Support.');

        TicketBroadcast::claimIfUnclaimed($ticket, $bpoUser, $agent);

        $data = $request->validate(['note' => 'required|string|max:3000']);
        $oldStatus = $ticket->status;

        // Same reasoning as resolve(): returning the ticket is a response too.
        $ticket->markFirstResponse();

        $ticket->update(['status' => 'Returned']);

        AuditTrail::record($bpoUser, [
            'module' => 'ticket_support',
            'action' => 'return',
            'target_type' => 'ticket',
            'target_id' => $ticket->id,
            'target_name' => $ticket->ticket_no,
            'old_value' => ['status' => $oldStatus],
            'new_value' => ['status' => 'Returned', 'catatan' => $data['note']],
            'description' => "{$bpoUser->name} mengembalikan tiket \"{$ticket->ticket_no}\" ke requester: {$data['note']}",
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

        TicketNotification::where('user_id', $bpoUser->id)->where('role', 'support-bpo')->whereNull('read_at')->update(['read_at' => Carbon::now()]);

        return response()->json(['read' => true]);
    }

    /**
     * Sebagian orang (mis. Arief Kurniawan) punya DUA baris SupportAgent
     * tertaut ke user_id yang sama — satu type=bpo, satu type=it — karena
     * dia dobel peran. Tanpa filter type di sini, firstOrFail() bisa
     * mengambil baris IT-nya secara acak (tergantung urutan baris), dan
     * tiket yang sudah dieskalasi ke IT ikut kelihatan di portal BPO cuma
     * karena assigned_agent_id-nya kebetulan cocok dengan baris yang salah
     * ini kembalikan.
     */
    private function agentFor(User $bpoUser): SupportAgent
    {
        return SupportAgent::where('user_id', $bpoUser->id)->where('type', 'bpo')->firstOrFail();
    }

    /**
     * Tiket yang sudah jadi milikku, DITAMBAH tiket broadcast "Lainnya"
     * yang belum diklaim siapa pun tapi aku salah satu PIC BPO Layanannya
     * (lihat TicketBroadcast) — supaya tiket itu kelihatan di dashboard/My
     * Tickets sebelum ada yang membalasnya, bukan cuma setelah diklaim.
     *
     * escalated_at HARUS kosong di cabang broadcast — cerminan syarat
     * sebaliknya di SupportController::visibleTicketsQuery(). Tiket
     * "Lainnya" yang sudah dieskalasi juga `assigned_agent_id = null`
     * (sekarang artinya "belum diklaim IT manapun"), jadi tanpa syarat ini
     * dia balik lagi ke daftar semua PIC BPO Layanan itu seolah masih
     * giliran mereka — padahal giliran BPO sudah selesai, dan tiket yang
     * sama muncul di dua portal sekaligus.
     */
    /**
     * `$includeEscalatedAway` is off by default: dashboard()'s stats/queue
     * (assignedToMe, slaAtRisk, myRating, ...) mean "work that's still mine
     * right now" — a ticket handed off to IT isn't that anymore (broadcast
     * escalations even reset to status 'Open' with no assigned_agent_id,
     * which would otherwise wrongly count as "assigned to me"/at-risk-for-me
     * again). myTickets() (the History list) opts in, since that page is a
     * record of everything this agent has touched, not an action queue.
     */
    private function visibleTicketsQuery(SupportAgent $agent, bool $includeEscalatedAway = false)
    {
        $broadcastServiceIds = $agent->bpoServiceIds();

        return Ticket::where(function ($q) use ($agent, $broadcastServiceIds, $includeEscalatedAway) {
            $q->where('assigned_agent_id', $agent->id);

            if ($includeEscalatedAway) {
                $q->orWhere('escalated_by_agent_id', $agent->id);
            }

            if ($broadcastServiceIds->isNotEmpty()) {
                $q->orWhere(function ($q2) use ($broadcastServiceIds) {
                    $q2->whereNull('assigned_agent_id')
                        ->whereNull('catalog_subject_id')
                        ->whereNull('escalated_at')
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
            'slaKind' => $t->sla_kind,
            'priority' => $t->priority,
            'requester' => $t->requester?->name ?? '—',
            'createdAt' => $t->created_at->toIso8601String(),
            'at' => $t->created_at->translatedFormat('j M Y · H:i'),
            'href' => route('support-bpo.tickets.show', $t),
        ];
    }

    /**
     * SENGAJA tidak menerima agent yang sedang melihat: 'pic' di bawah dulu
     * diisi dari situ, jadi panel PIC selalu menampilkan NAMA DIRI SENDIRI —
     * siapa pun yang membuka tiket melihat namanya tercantum sebagai PIC,
     * termasuk untuk tiket broadcast yang belum diklaim siapa pun. Di layar
     * yang sama TicketFlow menulis "belum ada PIC", jadi satu halaman
     * menyatakan dua hal yang bertentangan, dan PIC BPO mengira tiket yang
     * masih bebas sudah menjadi miliknya.
     */
    private function presentTicket(Ticket $t, SupportAgent $agent): array
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
            // Distinct from `canAct` above, which is purely about ticket
            // status: this is about the VIEWER — a BPO agent who escalated
            // this ticket can open it and comment (canView() in show()/
            // data()/addComment() allows that), but no longer owns it, so
            // the frontend hides start/resolve/escalate/return here.
            'canManage' => $this->canManage($t, $agent),
            // Subject Level 1 = BPO-only, tidak ada IT yang boleh menerima —
            // tombol Eskalasi disembunyikan di frontend. Tiket "Lainnya" (tanpa
            // catalogSubject) tidak kena aturan Level ini, tetap boleh eskalasi.
            'canEscalate' => ! ($t->catalogSubject && (int) $t->catalogSubject->support_level === 1),
            'escalated' => $t->escalated_at !== null,
            'escalatedAt' => $t->escalated_at?->translatedFormat('j M Y · H:i'),
            'escalationNote' => $t->escalation_note,
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
        return NotificationService::present($bpoUser, 'support-bpo', 20, 'support-bpo.tickets.show', 'support-bpo.notifications.read');
    }
}
