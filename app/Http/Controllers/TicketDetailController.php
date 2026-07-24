<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\TicketApproval;
use App\Models\TicketAttachment;
use App\Models\TicketComment;
use App\Support\CurrentActor;
use App\Support\NotificationService;
use App\Support\TicketTimeline;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class TicketDetailController extends Controller
{
    public function show(Ticket $ticket): View
    {
        $requester = CurrentActor::requester();
        abort_unless($ticket->requester_id === $requester->id, 403);

        $ticket->load(['requester', 'approver', 'catalogSubject.supportAgent', 'catalogSubject.itAgent', 'comments', 'attachments']);

        return view('requester.ticket-detail', [
            'role' => 'requester',
            'currentUser' => ['name' => $requester->name, 'title' => $requester->jabatan.' · '.$requester->unit, 'initials' => $this->initials($requester->name)],
            'notifications' => NotificationService::present($requester),
            'ticket' => $this->presentTicket($ticket),
            'comments' => $ticket->comments->map(fn (TicketComment $c) => $this->presentComment($c))->values(),
            'timeline' => TicketTimeline::steps($ticket),
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

    public function uploadAttachment(Request $request, Ticket $ticket): JsonResponse
    {
        $requester = CurrentActor::requester();
        abort_unless($ticket->requester_id === $requester->id, 403);

        $request->validate([
            'file' => 'required|file|mimes:png,jpg,jpeg,pdf|max:5120',
        ]);

        if ($ticket->attachments()->count() >= TicketAttachment::MAX_PER_TICKET) {
            return response()->json([
                'message' => 'Maksimal '.TicketAttachment::MAX_PER_TICKET.' file lampiran per tiket.',
            ], 422);
        }

        $path = $request->file('file')->store('ticket-attachments', 'public');

        $attachment = $ticket->attachments()->create([
            'name' => $request->file('file')->getClientOriginalName(),
            'path' => $path,
        ]);

        return response()->json([
            'attachment' => [
                'id' => $attachment->id,
                'name' => $attachment->name,
                'url' => Storage::disk('public')->url($attachment->path),
            ],
            'attachments' => $ticket->attachments()->get()
                ->map(fn (TicketAttachment $a) => ['id' => $a->id, 'name' => $a->name, 'url' => Storage::disk('public')->url($a->path)])
                ->values(),
        ], 201);
    }

    public function destroyAttachment(Ticket $ticket, TicketAttachment $attachment): JsonResponse
    {
        $requester = CurrentActor::requester();
        abort_unless($ticket->requester_id === $requester->id, 403);
        abort_unless($attachment->ticket_id === $ticket->id, 404);

        Storage::disk('public')->delete($attachment->path);
        $attachment->delete();

        return response()->json(['deleted' => true]);
    }

    public function addComment(Request $request, Ticket $ticket): JsonResponse
    {
        $requester = CurrentActor::requester();
        abort_unless($ticket->requester_id === $requester->id, 403);
        abort_if(in_array($ticket->status, ['Closed', 'Rejected'], true), 422, 'Diskusi tiket ini sudah ditutup.');

        $data = $request->validate(['message' => 'required|string|max:3000']);

        $comment = TicketComment::create([
            'ticket_id' => $ticket->id,
            'author_name' => $requester->name,
            'author_role' => 'Requester',
            'message' => $data['message'],
        ]);

        NotificationService::notifyDiscussionParticipants($ticket, $requester, 'Requester', $data['message']);

        return response()->json($this->presentComment($comment), 201);
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
            $ticket,
            'ticket_closed',
            'Tiket Ditutup',
            "Tiket {$ticket->ticket_no} berhasil ditutup. Terima kasih atas penilaian Anda ({$data['rating']}/5)."
        );

        return response()->json(['status' => $ticket->status]);
    }

    private function presentTicket(Ticket $t): array
    {
        $lastApproval = in_array($t->status, ['Draft', 'Open', 'Rejected'], true)
            ? TicketApproval::where('ticket_id', $t->id)->latest('created_at')->first()
            : null;

        $elapsedPct = 0;
        if ($t->sla_minutes_remaining !== null && $t->resolution_time_minutes > 0) {
            $elapsedPct = (int) round((1 - max($t->sla_minutes_remaining, 0) / $t->resolution_time_minutes) * 100);
            $elapsedPct = max(0, min(100, $t->sla_kind === 'breach' ? 100 : $elapsedPct));
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
            'createdAt' => $t->created_at->format('M j, Y · H:i'),
            'satisfactionRating' => $t->satisfaction_rating,
            'feedbackNote' => $t->feedback_note,
            'approvalNote' => $lastApproval ? [
                'decision' => $lastApproval->decision,
                'note' => $lastApproval->note,
                'approverName' => $t->approver?->name,
                'at' => $lastApproval->created_at->format('M j, Y · H:i'),
            ] : null,
            'reopenNote' => $t->reopen_note ? [
                'note' => $t->reopen_note,
                'at' => $t->reopen_at->format('M j, Y · H:i'),
            ] : null,
            'sla' => [
                'label' => $t->sla_label,
                'kind' => $t->sla_kind,
                'pct' => $elapsedPct,
                'responseTarget' => $this->formatMinutes($t->response_time_minutes),
                'resolutionTarget' => $this->formatMinutes($t->resolution_time_minutes),
            ],
            'people' => [
                'requester' => $t->requester ? ['name' => $t->requester->name, 'role' => 'Requester', 'email' => $t->requester->email] : null,
                'approver' => $t->approver ? ['name' => $t->approver->name, 'role' => 'Approver · '.$t->approver->jabatan, 'email' => $t->approver->email] : null,
                // Computed live from the catalog Subject's current support
                // assignment (via catalog_subject_id), not a value frozen on
                // the ticket — a Level 2 Subject (both teams) surfaces as
                // two entries here.
                'support' => collect([$t->catalogSubject?->supportAgent, $t->catalogSubject?->itAgent])
                    ->filter()
                    ->map(fn ($a) => ['name' => $a->name, 'role' => 'Support · '.strtoupper($a->type), 'email' => $a->email])
                    ->values()
                    ->all(),
            ],
            'canConfirmClose' => $t->status === 'Resolved',
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

    private function formatMinutes(int $minutes): string
    {
        if ($minutes % 1440 === 0) {
            return ($minutes / 1440).' day'.($minutes / 1440 > 1 ? 's' : '');
        }
        if ($minutes % 60 === 0) {
            return ($minutes / 60).' hour'.($minutes / 60 > 1 ? 's' : '');
        }

        return "{$minutes} minutes";
    }

    private function initials(string $name): string
    {
        $parts = explode(' ', trim($name));

        return strtoupper(substr($parts[0] ?? '', 0, 1).substr($parts[1] ?? '', 0, 1));
    }
}
