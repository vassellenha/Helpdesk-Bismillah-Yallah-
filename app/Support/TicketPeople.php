<?php

namespace App\Support;

use App\Models\AuditTrail;
use App\Models\SupportAgent;
use App\Models\Ticket;

/**
 * Builds the "People" panel's list of support agents involved with a ticket,
 * so every role's ticket-detail screen surfaces everyone who actually touched
 * the ticket — not just the one agent currently configured on the catalog
 * subject. Sources, deduped by agent name:
 *
 *  - the catalog Subject's configured BPO/IT agents (the default routing),
 *  - the ticket's current PIC (assigned_agent_id), and
 *  - every agent named in the escalation / reassignment audit history
 *    (BPO→IT escalations and Team Lead reassignments).
 *
 * Shared by the Requester / Support / Support BPO detail controllers so the
 * panel reads identically wherever the ticket is opened.
 */
class TicketPeople
{
    /**
     * @return array<int,array{name:string,role:string,email:?string}>
     */
    public static function supportAgents(Ticket $ticket): array
    {
        // Agents we already hold as models (catalog routing + current PIC).
        $direct = collect([
            $ticket->catalogSubject?->supportAgent,
            $ticket->catalogSubject?->itAgent,
            $ticket->assignedAgent,
        ])->filter();

        // Agent names that only appear in the routing history.
        $journeyNames = AuditTrail::where('target_type', 'ticket')
            ->where('target_id', $ticket->id)
            ->whereIn('action', ['escalate', 'reassign'])
            ->get()
            ->flatMap(fn (AuditTrail $a) => [
                $a->old_value['agent'] ?? null,
                $a->new_value['agent'] ?? null,
                $a->old_value['assigned_agent'] ?? null,
                $a->new_value['assigned_agent'] ?? null,
            ])
            ->filter()
            ->unique()
            ->reject(fn (string $name) => $direct->contains('name', $name));

        // Resolve those names to real agent rows for their type + email.
        $fromHistory = $journeyNames->isEmpty()
            ? collect()
            : SupportAgent::whereIn('name', $journeyNames->values()->all())->get();

        return $direct
            ->concat($fromHistory)
            ->unique('name')
            ->map(fn (SupportAgent $a) => [
                'name' => $a->name,
                'role' => 'Support · '.strtoupper($a->type),
                'email' => $a->email,
            ])
            ->values()
            ->all();
    }
}
