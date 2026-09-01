<?php

namespace App\Support;

use App\Models\AuditTrail;
use App\Models\SupportAgent;
use App\Models\Ticket;
use Illuminate\Support\Collection;

/**
 * Builds the "People" panel's list of support agents involved with a ticket,
 * so every role's ticket-detail screen surfaces everyone who actually touched
 * the ticket — not just the one agent currently configured on the catalog
 * subject. Sources, deduped by agent name:
 *
 *  - the catalog Subject's configured BPO/IT agents (the default routing),
 *  - the BPO agent who escalated it (escalated_by_agent_id),
 *  - the ticket's current PIC (assigned_agent_id), and
 *  - every agent named in the escalation / reassignment audit history
 *    (BPO→IT escalations and Team Lead reassignments).
 *
 * ROWS ARE PREFERRED OVER NAMES. The audit trail records people by name, and a
 * name is not enough to identify anyone here: someone holding both roles has
 * TWO SupportAgent rows under one name (one 'bpo', one 'it'), so resolving a
 * name returns both and picking one is a coin flip. That coin flip is what
 * labelled the escalating BPO agent "Support · IT" while Riwayat Status on the
 * same page correctly called them BPO — one screen, two answers about one
 * person. escalated_by_agent_id and assigned_agent_id name the exact rows, so
 * the name lookup is now only a fallback for people no column points at (Team
 * Lead reassignments, which the audit trail records by name alone).
 *
 * Shared by the Requester / Support / Support BPO detail controllers so the
 * panel reads identically wherever the ticket is opened.
 */
class TicketPeople
{
    /** Urutan tetap supaya "BPO, IT" tidak kadang tertulis "IT, BPO". */
    private const TYPE_ORDER = ['bpo' => 0, 'it' => 1];

    /**
     * @return array<int,array{name:string,role:string,email:?string}>
     */
    public static function supportAgents(Ticket $ticket): array
    {
        // Agents we already hold as models — catalog routing, the BPO who
        // escalated, and the current PIC. Every one of these is a specific row,
        // so its type is fact rather than inference.
        $direct = collect([
            $ticket->catalogSubject?->supportAgent,
            $ticket->catalogSubject?->itAgent,
            $ticket->escalatedByAgent,
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
            ->groupBy('name')
            ->map(fn (Collection $rows, string $name) => [
                'name' => $name,
                'role' => 'Support · '.self::deskLabel($rows),
                'email' => $rows->pluck('email')->filter()->first(),
            ])
            ->values()
            ->all();
    }

    /**
     * Satu orang tetap satu baris, tapi deskinya disebut apa adanya: orang yang
     * benar-benar memegang tiket di kedua desk (mengeskalasi sebagai BPO lalu
     * mengklaimnya sendiri sebagai IT) tertulis "BPO, IT" — bukan salah satunya
     * dipilih secara acak, yang persis bug sebelumnya.
     *
     * @param  Collection<int,SupportAgent>  $rows
     */
    private static function deskLabel(Collection $rows): string
    {
        return $rows
            ->pluck('type')
            ->filter()
            ->unique()
            ->sortBy(fn (string $type) => self::TYPE_ORDER[$type] ?? PHP_INT_MAX)
            ->map(fn (string $type) => strtoupper($type))
            ->join(', ');
    }
}
