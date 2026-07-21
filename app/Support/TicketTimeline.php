<?php

namespace App\Support;

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
        if ($ticket->is_draft || $ticket->status === 'Draft') {
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

            $steps[] = self::step('Manager approval — Approved', $approverName, null, 'done');
        }

        return array_merge($steps, self::supportSteps($ticket));
    }

    private static function supportSteps(Ticket $ticket): array
    {
        $steps = [];

        if ($ticket->status === 'Open') {
            $steps[] = self::step('Routed to Support — Open', null, null, 'current');
            $steps[] = self::step('Resolved', null, null, 'pending');
            $steps[] = self::step('Closed', null, null, 'pending');

            return $steps;
        }

        if (in_array($ticket->status, ['Assigned', 'In Progress', 'Waiting for Response'], true)) {
            $steps[] = self::step("Support Team — {$ticket->status}", null, null, 'current');
            $steps[] = self::step('Resolved', null, null, 'pending');
            $steps[] = self::step('Closed', null, null, 'pending');

            return $steps;
        }

        // Resolved / Completed / Closed all passed through Support already.
        $steps[] = self::step('Support Team', null, null, 'done');

        if ($ticket->status === 'Resolved') {
            $steps[] = self::step('Resolved — awaiting your confirmation', null, $ticket->resolved_at, 'current');
            $steps[] = self::step('Closed', null, null, 'pending');

            return $steps;
        }

        $steps[] = self::step('Resolved', null, $ticket->resolved_at, 'done');
        $steps[] = self::step('Closed', null, $ticket->updated_at, 'done');

        return $steps;
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
