<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditTrail;
use App\Models\SupportAgent;
use App\Models\Ticket;
use App\Support\CurrentActor;
use App\Support\DummyData;
use App\Support\PriorityRegistry;
use App\Support\TicketFlow;
use App\Support\TicketTimeline;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Mostly read-only admin console over the real `tickets` table. Administrator
 * can monitor every ticket (created by Requester via TicketController@store)
 * but cannot assign/reassign a PIC or change status here — that stays a
 * Team Lead/Support action for a later phase. The one exception is
 * toggleRating() — excluding a disputed/mistaken satisfaction rating from
 * every average-rating calculation without deleting it (see rating_active
 * on the tickets table).
 *
 * Scoped to `whereNotNull('requester_id')` so this list always matches what
 * the Requester side (My Tickets) actually has — a couple of orphaned rows
 * with no requester link exist from pre-Requester-flow testing and must not
 * surface here.
 */
class TicketManagementController extends Controller
{
    public function index(): View
    {
        $tickets = Ticket::with(['requester', 'approver', 'attachments', 'assignedAgent', 'catalogSubject.supportAgent', 'catalogSubject.itAgent'])
            ->whereNotNull('requester_id')
            ->latest('created_at')
            ->get();

        $rows = $tickets->map(fn (Ticket $t) => $this->presentRow($t))->values();

        $today = Carbon::today();

        $stats = [
            'total' => $tickets->count(),
            'open' => $tickets->where('status', 'Open')->count(),
            'waitingApproval' => $tickets->where('status', 'Waiting for Approval')->count(),
            'inProgress' => $tickets->where('status', 'In Progress')->count(),
            'resolvedToday' => $tickets->where('status', 'Resolved')
                ->filter(fn (Ticket $t) => $t->resolved_at && $t->resolved_at->isSameDay($today))
                ->count(),
            'overSla' => $tickets
                ->filter(fn (Ticket $t) => in_array($t->status, Ticket::ACTIVE_STATUSES, true) && $t->sla_kind === 'breach')
                ->count(),
        ];

        return view('admin.ticket-management', [
            'role' => 'admin',
            'notifications' => DummyData::notifications(),
            'tickets' => $rows,
            'stats' => $stats,
            'filterOptions' => [
                'statuses' => $tickets->pluck('status')->unique()->sort()->values(),
                'priorities' => PriorityRegistry::all(),
                'issueCategories' => $tickets->pluck('issue_category')->filter()->unique()->sort()->values(),
                'requesters' => $tickets->pluck('requester_name')->filter()->unique()->sort()->values(),
                // Every active support agent, BPO and IT alike — not just the ones
                // who happen to hold a ticket right now, which made an agent vanish
                // from the filter the moment their queue emptied.
                'pics' => SupportAgent::where('is_active', true)->orderBy('name')->pluck('name')->unique()->values(),
            ],
            'exportUrl' => route('admin.ticket-management.export'),
        ]);
    }

    /**
     * Exports whatever ticket_no set the frontend currently has filtered
     * (passed as a comma-separated `ids` query param), never the whole
     * table unconditionally — so the file always matches what the admin
     * was looking at.
     */
    public function export(Request $request): StreamedResponse
    {
        $data = $request->validate([
            'ids' => 'nullable|string',
        ]);

        $query = Ticket::with(['requester', 'approver', 'assignedAgent', 'catalogSubject.supportAgent', 'catalogSubject.itAgent'])->whereNotNull('requester_id');

        if (! empty($data['ids'])) {
            $ids = array_values(array_filter(explode(',', $data['ids'])));
            $query->whereIn('ticket_no', $ids);
        }

        $tickets = $query->latest('created_at')->get();
        $filename = 'ticket-management-'.Carbon::now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($tickets) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'Ticket ID', 'Issue Category', 'Layanan', 'Sub Category', 'Subject', 'Requester',
                'Priority', 'PIC', 'Status', 'SLA Status', 'Created At', 'Response Due At', 'Resolution Due At',
            ]);

            foreach ($tickets as $t) {
                $sla = $this->slaPresent($t);
                fputcsv($handle, [
                    $t->ticket_no,
                    $t->issue_category ?? '-',
                    $t->service_name ?? '-',
                    $t->subcategory_name ?? '-',
                    $t->subject_name ?? '-',
                    $t->requester_name ?? $t->requester?->name ?? '-',
                    $t->priority,
                    $this->picLabel($t) ?? 'Menunggu Assignment',
                    $t->status,
                    $sla['label'],
                    $t->created_at->format('Y-m-d H:i'),
                    optional($t->response_due_at)->format('Y-m-d H:i'),
                    optional($t->resolution_due_at)->format('Y-m-d H:i'),
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * Flips whether this ticket's satisfaction_rating counts toward every
     * average-rating calculation (Support IT/BPO's own displayed score,
     * Team Lead's agent performance view, the "kirim teguran rating"
     * threshold check — see Ticket::NO_SLA_STATUSES-style usages of
     * rating_active across those controllers) — the rating and the
     * Requester's feedback note stay exactly as given, this only excludes
     * it from the math while switched off. Reversible at any time.
     */
    public function toggleRating(Ticket $ticket): JsonResponse
    {
        abort_if($ticket->satisfaction_rating === null, 422, 'Tiket ini belum memiliki rating dari Requester.');

        $admin = CurrentActor::admin();
        $wasActive = $ticket->rating_active;
        $ticket->update(['rating_active' => ! $wasActive]);

        AuditTrail::record($admin, [
            'module' => 'ticket_management',
            'action' => $wasActive ? 'deactivate' : 'activate',
            'target_type' => 'ticket',
            'target_id' => $ticket->id,
            'target_name' => $ticket->ticket_no,
            'old_value' => ['rating_active' => $wasActive],
            'new_value' => ['rating_active' => ! $wasActive],
            'description' => $wasActive
                ? "{$admin->name} menonaktifkan rating tiket \"{$ticket->ticket_no}\" ({$ticket->satisfaction_rating}/5) dari perhitungan rata-rata."
                : "{$admin->name} mengaktifkan kembali rating tiket \"{$ticket->ticket_no}\" ({$ticket->satisfaction_rating}/5) ke perhitungan rata-rata.",
        ]);

        return response()->json([
            'rating' => $ticket->satisfaction_rating,
            'ratingActive' => (bool) $ticket->fresh()->rating_active,
        ]);
    }

    private function presentRow(Ticket $t): array
    {
        return [
            'ticketNo' => $t->ticket_no,
            'issueCategory' => $t->issue_category ?? '-',
            'service' => $t->service_name ?? '-',
            'subcategory' => $t->subcategory_name ?? '-',
            'subject' => $t->subject_name ?? '-',
            'requesterName' => $t->requester_name ?? $t->requester?->name ?? '-',
            'priority' => $t->priority,
            'pic' => $this->picLabel($t),
            // The label can read "BPO name & IT name", so the filter matches
            // against the individual names instead of that combined string —
            // otherwise picking one agent silently skips every shared subject.
            'picNames' => $this->picNames($t),
            'status' => $t->status,
            'sla' => $this->slaPresent($t),
            'rating' => $t->satisfaction_rating,
            'ratingActive' => (bool) $t->rating_active,
            'createdAt' => $t->created_at->format('d M Y, H:i'),
            'createdAtIso' => $t->created_at->toIso8601String(),
            'description' => $t->description,
            // Attachments live in their own table; this used to read
            // $t->attachment_name / $t->attachment_path, columns that do not
            // exist on tickets, so Admin silently never showed any attachment.
            'attachments' => $t->attachmentsPayload(),
            'approvalInfo' => $this->approvalInfo($t),
            'timeline' => TicketTimeline::steps($t),
            'flow' => TicketFlow::stages($t),
            'requester' => $t->requester ? [
                'name' => $t->requester->name,
                'nik' => $t->requester->nip,
                'email' => $t->requester->email,
                'jabatan' => $t->requester->jabatan,
                'unit' => $t->requester->unit,
            ] : null,
        ];
    }

    /**
     * Admin-facing SLA label in Indonesian, computed live against server
     * time from the frozen snapshot fields — kept separate from
     * Ticket::getSlaLabelAttribute() (English, used by Requester screens)
     * so this page's wording doesn't leak into/break those.
     */
    /**
     * The shared SLA panel data, with this console's own Indonesian label and
     * its extra "met-late" distinction layered on top — Admin is the only screen
     * that separates "selesai tepat waktu" from "selesai melewati SLA".
     */
    private function slaPresent(Ticket $t): array
    {
        return [...$t->slaPayload(), ...$this->slaVerdict($t)];
    }

    /** @return array{label:string,kind:string} */
    private function slaVerdict(Ticket $t): array
    {
        if (in_array($t->status, Ticket::NO_SLA_STATUSES, true)) {
            return ['label' => '—', 'kind' => 'none'];
        }

        if (in_array($t->status, Ticket::DONE_STATUSES, true)) {
            $onTime = ! $t->resolved_at || $t->resolved_at->lessThanOrEqualTo($t->resolution_due_at);

            return [
                'label' => $onTime ? 'Selesai tepat waktu' : 'Selesai melewati SLA',
                'kind' => $onTime ? 'met' : 'met-late',
            ];
        }

        $minutes = $t->sla_minutes_remaining ?? 0;

        if ($minutes <= 0) {
            return ['label' => 'Breach '.$this->formatDurationId(abs($minutes)), 'kind' => 'breach'];
        }

        return ['label' => $this->formatDurationId($minutes).' tersisa', 'kind' => $t->sla_kind];
    }

    /**
     * Same floor-based day/hour/minute breakdown as Ticket::formatDuration()
     * (used by the Requester side) — kept in sync so the two screens never
     * disagree on how much time is left/overdue for the same ticket.
     */
    private function formatDurationId(int $minutes): string
    {
        $minutes = max(1, $minutes);
        $days = intdiv($minutes, 1440);
        $hours = intdiv($minutes % 1440, 60);
        $mins = $minutes % 60;

        if ($days > 0) {
            return $hours > 0 ? "{$days} hari {$hours} jam" : "{$days} hari";
        }

        if ($hours > 0) {
            return $mins > 0 ? "{$hours} jam {$mins} menit" : "{$hours} jam";
        }

        return "{$mins} menit";
    }

    /**
     * PIC is computed live from the catalog Subject's *current* support
     * assignment (via catalog_subject_id) rather than any value frozen on
     * the ticket — a Subject requiring both teams (Level 2) shows both
     * names together, and either name updates immediately if Admin changes
     * Support in the Service Catalog, with no per-ticket sync needed.
     */
    /**
     * The PICs of a ticket as separate names.
     *
     * A catalog subject can name both a BPO and an IT agent, and picLabel()
     * joins those into one "A & B" string for display. Filtering has to work
     * per agent, so it needs the parts rather than the joined label.
     *
     * @return array<int,string>
     */
    private function picNames(Ticket $t): array
    {
        $subject = $t->catalogSubject;

        $names = collect([$subject?->supportAgent?->name, $subject?->itAgent?->name])
            ->filter()->unique()->values();

        // Tiket boleh dibuat tanpa subjek katalog — lihat layanan "Lainnya
        // (belum ada di katalog)". Tanpa cadangan ini tiket seperti itu selalu
        // terbaca "Menunggu Assignment" walau petugasnya sudah menutup tiket.
        if ($names->isEmpty() && $t->assignedAgent?->name) {
            $names = collect([$t->assignedAgent->name]);
        }

        return $names->all();
    }

    private function picLabel(Ticket $t): ?string
    {
        $names = collect($this->picNames($t));

        return $names->isEmpty() ? null : $names->implode(' & ');
    }

    private function approvalInfo(Ticket $t): string
    {
        if (! $t->approver_id) {
            return 'Tiket ini tidak memerlukan approval.';
        }

        return match ($t->status) {
            'Draft', 'Waiting for Approval' => 'Menunggu approval dari '.($t->approver?->name ?? '—').'.',
            'Returned' => 'Dikembalikan untuk revisi oleh '.($t->approver?->name ?? '—').'.',
            'Rejected' => 'Ditolak oleh '.($t->approver?->name ?? '—').'.',
            default => 'Disetujui oleh '.($t->approver?->name ?? '—').'.',
        };
    }
}
