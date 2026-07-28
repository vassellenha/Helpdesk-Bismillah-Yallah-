<?php

namespace App\Support;

use App\Models\AuditTrail;
use App\Models\Ticket;

/**
 * Derives a read-only status timeline purely from the ticket's current
 * fields (status, approver_id, resolved_at) — there is no separate
 * workflow/state-machine table because the Approver and Support role
 * screens are still dummy interfaces (see TicketController@store). Two
 * fixed paths only:
 *
 *  - Case A (requires_approval): submit -> stops at "Waiting for Approval".
 *    It never auto-escalates to Support on its own.
 *  - Case B (no approval): submit -> routes straight to Support as "Open".
 *
 * Historical/seeded tickets that already sit at a later status (Resolved,
 * Closed, Rejected, ...) render the steps that must have happened to reach
 * that status, so the timeline still reads correctly for demo data.
 */
class TicketTimeline
{
    public static function steps(Ticket $ticket): array
    {
        if ($ticket->status === 'Draft') {
            return [
                self::step('Draft saved', $ticket->requester?->name, $ticket->created_at, 'current'),
            ];
        }

        $steps = [
            self::step('Ticket submitted', $ticket->requester?->name, $ticket->created_at, 'done'),
        ];

        if ($ticket->approver_id) {
            $approverName = $ticket->approver?->name;

            if ($ticket->status === 'Waiting for Approval') {
                $steps[] = self::step('Waiting for manager approval', $approverName, null, 'current');
                $steps[] = self::step('Routed to Support', null, null, 'pending');

                return $steps;
            }

            if ($ticket->status === 'Rejected') {
                $steps[] = self::step('Approval rejected', $approverName, $ticket->updated_at, 'rejected');

                return $steps;
            }

            // A Returned ticket already reached the approver once — unlike a
            // never-submitted Draft, its history must keep showing that trip
            // through approval, with the revision request as the current step
            // (not collapsed away), so the requester sees why it bounced back.
            if ($ticket->status === 'Returned') {
                $steps[] = self::step('Waiting for manager approval', $approverName, null, 'done');
                $steps[] = self::step('Revision requested', $approverName, $ticket->updated_at, 'current');

                return $steps;
            }

            $steps[] = self::step('Manager approval — Approved', $approverName, null, 'done');
        }

        return array_merge($steps, self::supportSteps($ticket));
    }

    private static function supportSteps(Ticket $ticket): array
    {
        $pic = self::picLabel($ticket);
        // Corrective actions that happened while Support held the ticket
        // (BPO→IT escalation, Team Lead reassignments) — read back from the
        // audit trail so the journey shows every PIC change, not just the
        // final one. Placed right after the ticket lands in Support and
        // before its current/terminal status, matching when they occurred.
        $events = self::journeyEvents($ticket);
        // When there are handover events, they already carry the PIC names, so
        // the arrival step stays anonymous to avoid implying the final PIC
        // handled it from the start; only label arrival with the PIC when the
        // ticket never changed hands.
        $arrivalPic = $events === [] ? $pic : null;

        if ($ticket->status === 'Open') {
            $arrivalState = $events === [] ? 'current' : 'done';
            $steps = array_merge(
                [self::step('Routed to Support — Open', $arrivalPic, null, $arrivalState)],
                $events,
            );
            $steps[] = self::step('Resolved', null, null, 'pending');
            $steps[] = self::step('Closed', null, null, 'pending');

            return $steps;
        }

        if (in_array($ticket->status, ['Assigned', 'In Progress', 'Waiting for Response'], true)) {
            $steps = array_merge(
                [self::step('Routed to Support', $arrivalPic, null, 'done')],
                $events,
                [self::step("Support Team — {$ticket->status}", $pic, null, 'current')],
            );
            $steps[] = self::step('Resolved', null, null, 'pending');
            $steps[] = self::step('Closed', null, null, 'pending');

            return $steps;
        }

        // Resolved / Completed / Closed all passed through Support already.
        $steps = array_merge(
            [self::step('Support Team', $arrivalPic, null, 'done')],
            $events,
        );

        if ($ticket->status === 'Resolved') {
            $steps[] = self::step('Resolved — awaiting your confirmation', null, $ticket->resolved_at, 'current');
            $steps[] = self::step('Closed', null, null, 'pending');

            return $steps;
        }

        $steps[] = self::step('Resolved', null, $ticket->resolved_at, 'done');
        $steps[] = self::step('Closed', null, $ticket->updated_at, 'done');

        return $steps;
    }

    /** Current PIC as "Name (IT|BPO)", or null when the ticket has no PIC. */
    private static function picLabel(Ticket $ticket): ?string
    {
        $agent = $ticket->assignedAgent;

        return $agent ? $agent->name.' ('.strtoupper($agent->type).')' : null;
    }

    /**
     * Escalation and reassignment steps for this ticket, in the order they
     * happened, derived from the audit trail. Every PIC handover — BPO→IT
     * escalation and each Team Lead reassignment — becomes one 'done' step so
     * the ticket's routing history is visible on every role's detail screen.
     *
     * @return array<int,array<string,mixed>>
     */
    private static function journeyEvents(Ticket $ticket): array
    {
        return AuditTrail::where('target_type', 'ticket')
            ->where('target_id', $ticket->id)
            ->whereIn('action', ['escalate', 'reassign'])
            ->orderBy('created_at')
            ->get()
            ->map(function (AuditTrail $a) {
                if ($a->action === 'escalate') {
                    $to = $a->new_value['assigned_agent'] ?? null;
                    $from = $a->old_value['assigned_agent'] ?? null;

                    return self::step(
                        'Eskalasi ke Support IT'.($to ? " — {$to}" : ''),
                        $from ? "dari BPO ({$from})" : null,
                        $a->created_at,
                        'done',
                    );
                }

                $from = $a->old_value['agent'] ?? 'tanpa PIC';
                $to = $a->new_value['agent'] ?? '—';

                return self::step("Dialihkan — {$from} → {$to}", null, $a->created_at, 'done');
            })
            ->all();
    }

    private static function step(string $label, ?string $who, $at, string $state): array
    {
        return [
            'label' => $label,
            'who' => $who,
            'at' => $at?->format('M j, Y · H:i'),
            'state' => $state,
        ];
    }
}
