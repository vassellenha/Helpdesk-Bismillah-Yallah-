<?php

namespace App\Support;

use App\Models\AuditTrail;
use App\Models\Ticket;

/**
 * The ticket's journey as four fixed stages — Requester → Approver → Support →
 * Selesai — with each stage resolved to where the ticket actually stands.
 *
 * Companion to TicketTimeline, not a replacement: that one lists every event in
 * order (useful, but you have to read it to work out where the ticket is), this
 * one answers "which desk holds it right now" at a glance. Both are derived from
 * the same ticket, so they cannot disagree.
 *
 * Status is the authority. Approval records and audit rows only enrich a stage
 * with who acted and when — seeded and imported tickets often have neither, and
 * a Closed ticket is finished either way.
 */
class TicketFlow
{
    /** Statuses where Support is actively holding the ticket. */
    private const WITH_SUPPORT = ['Open', 'Assigned', 'In Progress', 'Waiting for Response'];

    /** @return array<string,mixed> */
    public static function stages(Ticket $ticket): array
    {
        $status = $ticket->status;
        $draft = $status === 'Draft';
        $waiting = $status === 'Waiting for Approval';
        $rejected = $status === 'Rejected';
        $returned = $status === 'Returned';
        $withSupport = in_array($status, self::WITH_SUPPORT, true);
        $resolved = $status === 'Resolved';
        $finished = in_array($status, Ticket::DONE_STATUSES, true);
        $closed = in_array($status, ['Completed', 'Closed'], true);

        $bySupport = $returned && self::returnedBySupport($ticket);
        $byApprover = $returned && ! $bySupport;

        // Support having held it at all means every approval step passed.
        $pastApproval = $withSupport || $finished || $bySupport;

        $decision = $ticket->approvals->last();
        $handovers = self::handovers($ticket);

        $stages = [
            [
                'key' => 'requester',
                'name' => __('flow.stage.requester'),
                'sub' => __('flow.sub.submitted'),
                // A Returned ticket is back on the requester's desk, so this
                // stage becomes active again rather than staying done.
                'state' => $draft ? 'current' : ($returned ? 'current' : 'done'),
                'by' => $ticket->requester?->name,
                'at' => $draft ? null : optional($ticket->created_at)->format('d M Y · H:i'),
            ],
            self::approverStage($ticket, $decision, compact('draft', 'waiting', 'rejected', 'byApprover', 'pastApproval')),
            [
                'key' => 'support',
                'name' => __('flow.stage.support'),
                'sub' => self::handoverSub($handovers),
                'state' => match (true) {
                    $withSupport => 'current',
                    $finished => 'done',
                    $bySupport => 'returned',
                    default => 'pending',
                },
                'by' => ($withSupport || $finished || $bySupport) ? self::picLabel($ticket) : null,
                'at' => null,
            ],
            [
                'key' => 'done',
                'name' => __('flow.stage.done'),
                'sub' => $resolved ? __('flow.sub.awaiting_confirmation') : __('flow.sub.closed'),
                'state' => match (true) {
                    $closed => 'done',
                    $resolved => 'current',
                    default => 'pending',
                },
                'by' => null,
                'at' => match (true) {
                    $closed => optional($ticket->updated_at)->format('d M Y · H:i'),
                    $resolved => optional($ticket->resolved_at)->format('d M Y · H:i'),
                    default => null,
                },
            ],
        ];

        return [
            'stages' => $stages,
            'note' => match (true) {
                $closed => __('flow.note.closed'),
                $resolved => __('flow.note.resolved'),
                $withSupport => __('flow.note.with_support', ['pic' => self::picLabel($ticket) ?? __('flow.no_pic')]),
                $rejected => __('flow.note.rejected'),
                $bySupport => __('flow.note.returned_support'),
                $byApprover => __('flow.note.returned_approver'),
                $waiting => __('flow.note.waiting', ['approver' => $ticket->approver?->name ?? __('flow.stage.approver')]),
                default => __('flow.note.draft'),
            },
            'noteState' => match (true) {
                $closed, $resolved => 'done',
                $rejected => 'rejected',
                $returned => 'returned',
                $withSupport, $waiting => 'current',
                default => 'pending',
            },
        ];
    }

    /**
     * @param  array<string,bool>  $f
     * @return array<string,mixed>
     */
    private static function approverStage(Ticket $ticket, $decision, array $f): array
    {
        if (! $ticket->approver_id) {
            return [
                'key' => 'approver',
                'name' => __('flow.stage.no_approval'),
                'sub' => $ticket->issue_category ?? __('flow.sub.direct'),
                'state' => $f['draft'] ? 'pending' : 'done',
                'by' => null,
                'at' => null,
            ];
        }

        return [
            'key' => 'approver',
            'name' => __('flow.stage.approver'),
            'sub' => __('flow.sub.approval'),
            'state' => match (true) {
                $f['draft'] => 'pending',
                $f['waiting'] => 'current',
                $f['rejected'] => 'rejected',
                $f['byApprover'] => 'returned',
                $f['pastApproval'] => 'done',
                default => 'pending',
            },
            'by' => $ticket->approver?->name,
            'at' => $f['waiting'] || $f['draft'] ? null : optional($decision?->created_at)->format('d M Y · H:i'),
        ];
    }

    /**
     * Whether the ticket's current "Returned" state came from Support sending it
     * back, rather than the Approver asking for a revision.
     *
     * Nothing on the ticket records which one it was, and the two mean opposite
     * things for the flow: an approver revision never reached Support, while a
     * Support return already passed approval. When both have happened (returned,
     * fixed, approved, then returned again by Support) the later one wins.
     */
    private static function returnedBySupport(Ticket $ticket): bool
    {
        $support = AuditTrail::where('target_type', 'ticket')
            ->where('target_id', $ticket->id)
            ->where('module', 'ticket_support')
            ->where('action', 'return')
            ->latest('created_at')
            ->first();

        if (! $support) {
            return false;
        }

        $approver = $ticket->approvals
            ->where('decision', 'revision_requested')
            ->sortBy('created_at')
            ->last();

        return ! $approver || $support->created_at->greaterThanOrEqualTo($approver->created_at);
    }

    /** PIC as "Name (IT)" / "Name (BPO)", or null when unassigned. */
    private static function picLabel(Ticket $ticket): ?string
    {
        $agent = $ticket->assignedAgent;

        return $agent ? $agent->name.' ('.strtoupper($agent->type).')' : null;
    }

    /** Number of PIC handovers (BPO→IT escalation, Team Lead reassignment). */
    private static function handovers(Ticket $ticket): int
    {
        return AuditTrail::where('target_type', 'ticket')
            ->where('target_id', $ticket->id)
            ->whereIn('action', ['escalate', 'reassign'])
            ->count();
    }

    private static function handoverSub(int $handovers): string
    {
        return $handovers > 0
            ? __('flow.sub.handovers', ['count' => $handovers])
            : __('flow.sub.handling');
    }
}
