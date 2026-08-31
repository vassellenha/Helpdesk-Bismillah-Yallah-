<?php

namespace App\Support;

use App\Models\AuditTrail;
use App\Models\Ticket;
use Illuminate\Support\Collection;

/**
 * The ticket's journey as stages — Requester → Approver → Support → Selesai —
 * with each stage resolved to where the ticket actually stands.
 *
 * Companion to TicketTimeline, not a replacement: that one lists every event in
 * order (useful, but you have to read it to work out where the ticket is), this
 * one answers "which desk holds it right now" at a glance. Both are derived from
 * the same ticket, so they cannot disagree.
 *
 * Status is the authority. Approval records and audit rows only enrich a stage
 * with who acted and when — seeded and imported tickets often have neither, and
 * a Closed ticket is finished either way.
 *
 * SUPPORT IS TWO DESKS, SO IT IS TWO STAGES. A ticket that BPO escalated has
 * passed through two separate desks, and folding them into one circle used to
 * force the chain "Denny (BPO) → Agung (IT)" into a single cell — which in turn
 * forced each person's desk to be GUESSED from their name
 * (SupportAgent::where('name', …)). People holding both roles have two
 * SupportAgent rows under one name, so that guess could pick the wrong row and
 * label the escalating BPO agent "(IT)".
 *
 * Now `support_bpo` and `support_it` are separate stages, each reading its
 * holder from the column that actually stores it — escalated_by_agent_id for
 * the BPO desk, assigned_agent_id for whoever holds it now. Nothing is guessed,
 * and no name suffix is needed because the stage itself names the desk.
 *
 * The stage list is therefore 4 or 5 entries long, never a fixed 4:
 * `support_it` appears if and only if `escalated_at` is set. Level 1 (BPO-only)
 * subjects need no special case — they can never be escalated, so the stage
 * simply never appears for them.
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

        $supportStages = self::supportStages($ticket, compact('withSupport', 'finished', 'bySupport'));

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
            ...$supportStages,
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
                $withSupport => __('flow.note.with_support', [
                    'desk' => self::currentDeskName($ticket),
                    'pic' => $ticket->assignedAgent?->name ?? __('flow.no_pic'),
                ]),
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
     * The Support circles — one desk or two, depending on whether the ticket
     * was ever escalated.
     *
     * Three shapes, and the middle one is not hypothetical:
     * TicketController::resolveAssignedAgentId() falls back to
     * `$subject?->support_agent_id ?? $subject?->it_agent_id`, so a Subject with
     * no BPO PIC routes its tickets straight to an IT agent with no escalation
     * at all. Naming that circle "Support BPO" would be a plain lie.
     *
     * @param  array<string,bool>  $f
     * @return list<array<string,mixed>>
     */
    private static function supportStages(Ticket $ticket, array $f): array
    {
        $state = match (true) {
            $f['withSupport'] => 'current',
            $f['finished'] => 'done',
            $f['bySupport'] => 'returned',
            default => 'pending',
        };

        // Who held it is only worth showing once Support has actually had it —
        // a ticket still awaiting approval has no PIC to name yet.
        $reveal = $f['withSupport'] || $f['finished'] || $f['bySupport'];

        $rows = self::handoverRows($ticket);
        $reassigns = $rows->where('action', 'reassign');

        if ($ticket->escalated_at === null) {
            return [self::supportStage(
                key: $ticket->assignedAgent?->type === 'it' ? 'support_it' : 'support_bpo',
                reassigns: $reassigns,
                seed: null,
                tail: $ticket->assignedAgent?->name,
                state: $state,
                reveal: $reveal,
                at: null,
            )];
        }

        [$beforeEscalation, $afterEscalation] = $reassigns
            ->partition(fn (AuditTrail $row) => $row->created_at->lt($ticket->escalated_at));

        return [
            self::supportStage(
                key: 'support_bpo',
                reassigns: $beforeEscalation,
                // Tickets escalated before escalated_by_agent_id existed still
                // name their BPO agent in the escalate row's old_value; without
                // this seed their desk would read "belum ada PIC" forever.
                seed: self::handoverName($rows->firstWhere('action', 'escalate')?->old_value),
                tail: $ticket->escalatedByAgent?->name,
                // The BPO desk's turn ended the moment it escalated, whatever
                // the ticket's status is now.
                state: 'done',
                reveal: $reveal,
                at: optional($ticket->escalated_at)->format('d M Y · H:i'),
            ),
            self::supportStage(
                key: 'support_it',
                reassigns: $afterEscalation,
                // Deliberately NOT seeded from the escalate row's new_value: a
                // broadcast escalation writes the literal "Broadcast PIC IT"
                // there (TicketBroadcast::escalateBroadcast()) and never
                // rewrites it when someone finally claims the ticket. The live
                // assigned_agent_id below is the honest answer, and "no PIC yet"
                // is the honest answer while it is still null.
                seed: null,
                tail: $ticket->assignedAgent?->name,
                state: $state,
                reveal: $reveal,
                at: optional($ticket->it_first_response_at)->format('d M Y · H:i'),
            ),
        ];
    }

    /**
     * @param  Collection<int,AuditTrail>  $reassigns
     * @return array<string,mixed>
     */
    private static function supportStage(
        string $key,
        Collection $reassigns,
        ?string $seed,
        ?string $tail,
        string $state,
        bool $reveal,
        ?string $at,
    ): array {
        $chain = self::deskChain($reassigns, $seed, $tail);

        return [
            'key' => $key,
            'name' => __('flow.stage.'.$key),
            'sub' => self::handoverSub($reassigns->count()),
            'state' => $state,
            'by' => $reveal ? (implode(' → ', $chain) ?: __('flow.no_pic')) : null,
            'at' => $at,
        ];
    }

    /**
     * Everyone who has held the ticket AT ONE DESK, oldest first.
     *
     * Only `reassign` rows build the chain. The `escalate` row is the hand-off
     * BETWEEN desks, and the stepper already draws that as the connector from
     * one circle to the next — replaying it inside a circle would make each
     * desk claim the other's PIC.
     *
     * The tail is overwritten with the desk's live holder rather than trusted
     * from the audit trail: audit rows record names as they were written at the
     * time, and a `claim` on a broadcast ticket never rewrites the earlier row.
     *
     * @param  Collection<int,AuditTrail>  $reassigns
     * @return list<string>
     */
    private static function deskChain(Collection $reassigns, ?string $seed, ?string $tail): array
    {
        $names = [];

        foreach ($reassigns as $row) {
            $old = self::handoverName($row->old_value);
            $new = self::handoverName($row->new_value);

            if ($old && $names === []) {
                $names[] = $old;
            }
            if ($new) {
                $names[] = $new;
            }
        }

        if ($names === [] && $seed !== null) {
            $names[] = $seed;
        }

        if ($tail !== null) {
            if ($names === []) {
                $names[] = $tail;
            } else {
                $names[array_key_last($names)] = $tail;
            }
        }

        return $names;
    }

    /**
     * Escalation writes the PIC under `assigned_agent`; Team Lead reassignment
     * writes it under `agent` (TeamLeadController::reassign()). Both shapes are
     * in the table, so both are read.
     *
     * @param  array<string,mixed>|null  $value
     */
    private static function handoverName(?array $value): ?string
    {
        return $value['assigned_agent'] ?? $value['agent'] ?? null;
    }

    /** @return Collection<int,AuditTrail> */
    private static function handoverRows(Ticket $ticket): Collection
    {
        return AuditTrail::where('target_type', 'ticket')
            ->where('target_id', $ticket->id)
            ->whereIn('action', ['escalate', 'reassign'])
            ->orderBy('created_at')
            ->get(['action', 'created_at', 'old_value', 'new_value']);
    }

    /** Which desk the note's "Sedang ditangani …" line should name. */
    private static function currentDeskName(Ticket $ticket): string
    {
        $isIt = $ticket->escalated_at !== null || $ticket->assignedAgent?->type === 'it';

        return $isIt ? __('flow.stage.support_it') : __('flow.stage.support_bpo');
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

    /** Number of PIC handovers WITHIN one desk (Team Lead reassignment). */
    private static function handoverSub(int $handovers): string
    {
        return $handovers > 0
            ? __('flow.sub.handovers', ['count' => $handovers])
            : __('flow.sub.handling');
    }
}
