<?php

namespace App\Http\Controllers;

use App\Models\AuditTrail;
use App\Models\Ticket;
use App\Models\TicketApproval;
use App\Models\TicketAttachment;
use App\Models\TicketComment;
use App\Support\CurrentActor;
use App\Support\NotificationService;
use App\Support\TicketAudit;
use App\Support\TicketDiscussion;
use App\Support\TicketFlow;
use App\Support\TicketPeople;
use App\Support\TicketTimeline;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class TicketDetailController extends Controller
{
    public function show(Ticket $ticket): View
    {
        $requester = CurrentActor::requester();
        abort_unless($ticket->requester_id === $requester->id, 403);

        $ticket->load(['requester', 'approver', 'assignedAgent', 'escalatedByAgent', 'catalogSubject.supportAgent', 'catalogSubject.itAgent', 'comments', 'attachments']);

        return view('requester.ticket-detail', [
            'role' => 'requester',
            // Identitas pembaca — dipakai layar untuk memutuskan gelembung mana
            // miliknya sendiri di Forum Diskusi. Dipisah dari payload header
            // yang tidak membawa id. Lihat lib/discussion.js.
            'viewer' => ['id' => $requester->id, 'name' => $requester->name],
            'currentUser' => ['name' => $requester->name, 'title' => $requester->jabatan.' · '.$requester->unit, 'initials' => $this->initials($requester->name)],
            'notifications' => NotificationService::present($requester, 'requester'),
            'ticket' => $this->presentTicket($ticket),
            'comments' => $ticket->comments->map(fn (TicketComment $c) => TicketDiscussion::present($c))->values(),
            'timeline' => TicketTimeline::steps($ticket),
            'flow' => TicketFlow::stages($ticket),
            'dataUrl' => route('requester.tickets.data', $ticket),
            'commentsUrl' => route('requester.tickets.comments.store', $ticket),
            'reopenUrl' => route('requester.tickets.reopen', $ticket),
            'closeUrl' => route('requester.tickets.close', $ticket),
            'attachmentUrl' => route('requester.tickets.attachment', $ticket),
            'ticketsUrl' => route('requester.tickets'),
            'editUrl' => route('requester.tickets.update', $ticket),
            'catalogUrl' => route('catalog.tree'),
            'approversUrl' => route('approvers.index'),
        ]);
    }

    /**
     * JSON re-fetch of this same ticket — lets the detail page pull fresh
     * status/timeline/comments in place after reopen/close instead of a full
     * `window.location.reload()`.
     */
    public function data(Ticket $ticket): JsonResponse
    {
        $requester = CurrentActor::requester();
        abort_unless($ticket->requester_id === $requester->id, 403);

        $ticket->load(['requester', 'approver', 'assignedAgent', 'escalatedByAgent', 'catalogSubject.supportAgent', 'catalogSubject.itAgent', 'comments', 'attachments']);

        return response()->json([
            'ticket' => $this->presentTicket($ticket),
            'comments' => $ticket->comments->map(fn (TicketComment $c) => TicketDiscussion::present($c))->values(),
            'timeline' => TicketTimeline::steps($ticket),
            'flow' => TicketFlow::stages($ticket),
        ]);
    }

    public function uploadAttachment(Request $request, Ticket $ticket): JsonResponse
    {
        $requester = CurrentActor::requester();
        abort_unless($ticket->requester_id === $requester->id, 403);

        $request->validate([
            'file' => 'required|file|mimes:png,jpg,jpeg,pdf,mp4,mov,webm|max:30720',
        ]);

        if ($ticket->attachments()->count() >= TicketAttachment::MAX_PER_TICKET) {
            return response()->json([
                'message' => 'Maksimal '.TicketAttachment::MAX_PER_TICKET.' file lampiran per tiket.',
            ], 422);
        }

        $path = $request->file('file')->store('ticket-attachments', 'local');

        /*
         | store() memulangkan FALSE kalau penulisan gagal — disk penuh, izin
         | folder salah, berkas sementara hilang — dan TIDAK melempar apa pun.
         |
         | Tanpa pemeriksaan ini, barisnya tetap dibuat dengan path kosong, dan
         | lampirannya muncul di layar sebagai "berkas hilang" tanpa seorang pun
         | tahu kapan atau kenapa. Lebih jujur menolak unggahannya sekarang,
         | selagi penggunanya masih di depan layar dan bisa mencoba lagi.
        */
        if ($path === false) {
            Log::error('Lampiran tiket gagal disimpan ke disk.', [
                'ticket' => $ticket->ticket_no,
                'name' => $request->file('file')->getClientOriginalName(),
            ]);

            return response()->json([
                'message' => 'Berkas gagal disimpan di server. Coba unggah ulang; bila berulang, hubungi administrator.',
            ], 500);
        }

        $attachment = $ticket->attachments()->create([
            'name' => $request->file('file')->getClientOriginalName(),
            'path' => $path,
        ]);

        // Same shape as Ticket::attachmentsPayload() so the viewer never has to
        // care whether a list came from a page load or from an upload response.
        $attachments = $ticket->load('attachments')->attachmentsPayload();

        return response()->json([
            'attachment' => collect($attachments)->firstWhere('id', $attachment->id),
            'attachments' => $attachments,
        ], 201);
    }

    public function destroyAttachment(Ticket $ticket, TicketAttachment $attachment): JsonResponse
    {
        $requester = CurrentActor::requester();
        abort_unless($ticket->requester_id === $requester->id, 403);
        abort_unless($attachment->ticket_id === $ticket->id, 404);

        // Nama berkasnya dicatat SEBELUM baris lampirannya hilang: sesudah
        // delete() tidak ada lagi yang bisa menyebutkan apa yang dicabut.
        $namaBerkas = $attachment->name;

        Storage::disk('local')->delete($attachment->path);
        $attachment->delete();

        TicketAudit::action($requester, 'ticket_requester', 'delete', $ticket,
            "{$requester->name} menghapus lampiran \"{$namaBerkas}\" dari tiket \"{$ticket->ticket_no}\".",
            ['lampiran' => $namaBerkas]);

        return response()->json(['deleted' => true]);
    }

    public function addComment(Request $request, Ticket $ticket): JsonResponse
    {
        $requester = CurrentActor::requester();
        abort_unless($ticket->requester_id === $requester->id, 403);
        abort_if(in_array($ticket->status, ['Closed', 'Rejected'], true), 422, 'Diskusi tiket ini sudah ditutup.');

        $data = $request->validate(TicketDiscussion::rules());

        $comment = TicketDiscussion::store($ticket, $requester, 'Requester', 'Requester', $data, $request->file('file'));

        return response()->json(TicketDiscussion::present($comment), 201);
    }

    /**
     * "Belum" step: the requester says the issue isn't actually fixed, so
     * the ticket goes back into the Support queue instead of closing. The
     * note is surfaced as a banner on both the requester's and Support's
     * ticket-detail pages (see reopen_note/reopen_at), not posted into the
     * Discussion thread — it's a status change, not a chat message.
     */
    public function reopen(Request $request, Ticket $ticket): JsonResponse
    {
        $requester = CurrentActor::requester();
        abort_unless($ticket->requester_id === $requester->id, 403);
        abort_unless($ticket->status === 'Resolved', 422, 'Only resolved tickets can be reopened.');

        $data = $request->validate(['note' => 'required|string|max:3000']);

        $ticket->update([
            'status' => 'In Progress',
            'resolved_at' => null,
            'reopen_note' => $data['note'],
            'reopen_at' => Carbon::now(),
        ]);

        // Surface the reopen reason in the discussion thread as a chat message
        // too (not just the reopen banner), so the conversation shows why the
        // requester sent the ticket back to Support.
        TicketComment::create([
            'ticket_id' => $ticket->id,
            'author_name' => $requester->name,
            'author_role' => 'Requester',
            'message' => $data['note'],
        ]);

        NotificationService::notifyAssignedAgent(
            $ticket,
            'ticket_reopened',
            'Tiket Dibuka Kembali',
            "Tiket {$ticket->ticket_no} dibuka kembali oleh {$requester->name}: {$data['note']}"
        );

        // Membuka kembali membatalkan penyelesaian yang sudah dicatat Support
        // dan menghidupkan lagi jam SLA — alasannya wajib ikut tersimpan.
        TicketAudit::action($requester, 'ticket_requester', 'reopen', $ticket,
            "{$requester->name} membuka kembali tiket \"{$ticket->ticket_no}\": {$data['note']}",
            ['alasan' => $data['note']]);

        return response()->json(['status' => $ticket->status]);
    }

    /**
     * "Ya, Sudah" step: confirms resolution, records a satisfaction rating,
     * and closes the ticket.
     */
    public function close(Request $request, Ticket $ticket): JsonResponse
    {
        $requester = CurrentActor::requester();
        abort_unless($ticket->requester_id === $requester->id, 403);
        abort_unless($ticket->status === 'Resolved', 422, 'Only resolved tickets can be confirmed and closed.');

        $data = $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'note' => 'nullable|string|max:3000',
        ]);

        $ticket->update([
            'status' => 'Closed',
            'satisfaction_rating' => $data['rating'],
            'feedback_note' => $data['note'] ?? null,
        ]);

        // Also surface the closing note in the discussion thread as a chat
        // message (not just the feedback banner), so the conversation shows
        // the requester's final note and the assigned agent gets pinged.
        if (! empty($data['note'])) {
            TicketComment::create([
                'ticket_id' => $ticket->id,
                'author_name' => $requester->name,
                'author_role' => 'Requester',
                'message' => $data['note'],
            ]);

            NotificationService::notifyDiscussionParticipants($ticket, $requester, 'Requester', $data['note']);
        }

        NotificationService::notify(
            $requester,
            'requester',
            $ticket,
            'ticket_closed',
            'Tiket Ditutup',
            "Tiket {$ticket->ticket_no} berhasil ditutup. Terima kasih atas penilaian Anda ({$data['rating']}/5)."
        );

        // Penutupan membawa rating yang ikut menghitung kinerja PIC-nya, jadi
        // nilainya disimpan utuh di new_value — bukan cuma disebut di kalimat.
        TicketAudit::action($requester, 'ticket_requester', 'close', $ticket,
            "{$requester->name} menutup tiket \"{$ticket->ticket_no}\" dengan penilaian {$data['rating']}/5.",
            ['rating' => $data['rating'], 'catatan' => $data['note'] ?? null]);

        return response()->json(['status' => $ticket->status]);
    }

    private function presentTicket(Ticket $t): array
    {
        $lastApproval = in_array($t->status, ['Draft', 'Returned', 'Open', 'Rejected'], true)
            ? TicketApproval::where('ticket_id', $t->id)->latest('created_at')->first()
            : null;

        $supportReturnAudit = $t->status === 'Returned' ? $this->latestSupportReturnAudit($t) : null;

        // "Returned" is shared by two unrelated flows (Approver's
        // revision-request decision, and Support's own return action) — only
        // the most recent one gets to explain the current Returned state, or
        // an older revision-request round could wrongly relabel a fresh
        // Support return (or vice versa).
        if ($t->status === 'Returned' && $lastApproval && $supportReturnAudit) {
            if ($supportReturnAudit->created_at->greaterThan($lastApproval->created_at)) {
                $lastApproval = null;
            } else {
                $supportReturnAudit = null;
            }
        }

        return [
            'id' => $t->ticket_no,
            'title' => $t->title,
            'status' => $t->status,
            'priority' => $t->priority,
            'category' => $t->issue_category ?? $t->category ?? '—',
            'service' => trim(($t->service_name ?? '').($t->subcategory_name ? ' · '.$t->subcategory_name : '')),
            'subject' => $t->subject_name,
            'description' => $t->description,
            'attachments' => $t->attachmentsPayload(),
            'createdAt' => $t->created_at->translatedFormat('j M Y · H:i'),
            'satisfactionRating' => $t->satisfaction_rating,
            'feedbackNote' => $t->feedback_note,
            'approvalNote' => $lastApproval ? [
                'decision' => $lastApproval->decision,
                'note' => $lastApproval->note,
                'approverName' => $t->approver?->name,
                'at' => $lastApproval->created_at->translatedFormat('j M Y · H:i'),
            ] : null,
            'reopenNote' => $t->reopen_note ? [
                'note' => $t->reopen_note,
                'at' => $t->reopen_at->translatedFormat('j M Y · H:i'),
            ] : null,
            'resolutionNote' => $this->latestResolutionNote($t),
            'supportReturnNote' => $supportReturnAudit ? [
                'note' => $supportReturnAudit->new_value['catatan'] ?? '',
                'agentName' => $supportReturnAudit->actor?->name ?? 'Tim Support',
                'at' => $supportReturnAudit->created_at->translatedFormat('j M Y · H:i'),
            ] : null,
            'sla' => $t->slaPayload(),
            'ratingActive' => (bool) $t->rating_active,
            'people' => [
                'requester' => $t->requester ? ['name' => $t->requester->name, 'role' => 'Requester', 'email' => $t->requester->email] : null,
                'approver' => $t->approver ? ['name' => $t->approver->name, 'role' => 'Approver · '.$t->approver->jabatan, 'email' => $t->approver->email] : null,
                // The agent who actually holds/resolved the ticket right now —
                // NOT necessarily `support[0]` below, which leads with the
                // catalog Subject's configured routing agent instead. Used for
                // "who resolved this" attribution (see ResolvedAnnouncementModal).
                'pic' => $t->assignedAgent
                    ? ['name' => $t->assignedAgent->name, 'role' => 'Support '.strtoupper($t->assignedAgent->type), 'email' => $t->assignedAgent->email]
                    : null,
                // Everyone who actually touched the ticket — catalog routing,
                // the current PIC, and every agent from the escalation /
                // reassignment history — so the panel detects all involved
                // parties, not just the configured agent (see TicketPeople).
                'support' => TicketPeople::supportAgents($t),
            ],
            'canConfirmClose' => $t->status === 'Resolved',
            'autoClose' => $t->autoClosePayload(),
            // Raw (non-combined) fields, only needed to prefill the Edit
            // Draft form — the display fields above stay as they were.
            'serviceName' => $t->service_name,
            'subcategoryName' => $t->subcategory_name,
            'subjectName' => $t->subject_name,
            'issueCategory' => $t->issue_category,
            'requiresApproval' => (bool) $t->approver_id,
            'approverId' => $t->approver_id,
            'approverName' => $t->approver?->name,
        ];
    }

    /**
     * "Diselesaikan oleh <agent>" banner data — sourced from the shared audit
     * trail (Support/Support BPO's `resolve()` action) since resolution notes
     * aren't stored on the ticket itself, only latest one, mirroring how
     * reopenNote/approvalNote read the latest state for their own source.
     */
    private function latestResolutionNote(Ticket $t): ?array
    {
        if (! in_array($t->status, ['Resolved', 'Closed'], true)) {
            return null;
        }

        $audit = AuditTrail::where('module', 'ticket_support')
            ->where('action', 'resolve')
            ->where('target_name', $t->ticket_no)
            ->with('actor')
            ->latest('created_at')
            ->first();

        if (! $audit) {
            return null;
        }

        return [
            'note' => $audit->new_value['catatan'] ?? '',
            'agentName' => $audit->actor?->name ?? 'Tim Support',
            'at' => $audit->created_at->translatedFormat('j M Y · H:i'),
        ];
    }

    /**
     * The audit row behind "Dikembalikan oleh <agent>" — Support's return()
     * action (SupportController/SupportBpoController), not stored on the
     * ticket itself, only the latest one, mirroring latestResolutionNote().
     */
    private function latestSupportReturnAudit(Ticket $t): ?AuditTrail
    {
        return AuditTrail::where('module', 'ticket_support')
            ->where('action', 'return')
            ->where('target_name', $t->ticket_no)
            ->with('actor')
            ->latest('created_at')
            ->first();
    }

    private function initials(string $name): string
    {
        $parts = explode(' ', trim($name));

        return strtoupper(substr($parts[0] ?? '', 0, 1).substr($parts[1] ?? '', 0, 1));
    }
}
