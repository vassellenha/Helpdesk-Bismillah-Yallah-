<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Support\DummyData;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Read-only admin console over the real `tickets` table. Administrator can
 * monitor every ticket (created by Requester via TicketController@store) but
 * cannot assign/reassign a PIC or change status here — that stays a
 * Team Lead/Support action for a later phase, so no mutating endpoints
 * exist on this controller.
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
        $tickets = Ticket::with(['requester', 'approver', 'catalogSubject.supportAgent', 'catalogSubject.itAgent', 'attachments'])
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
            'currentUser' => DummyData::currentAdmin(),
            'tickets' => $rows,
            'stats' => $stats,
            'filterOptions' => [
                'statuses' => $tickets->pluck('status')->unique()->sort()->values(),
                'priorities' => ['Critical', 'High', 'Medium', 'Low'],
                'issueCategories' => $tickets->pluck('issue_category')->filter()->unique()->sort()->values(),
                'requesters' => $tickets->pluck('requester_name')->filter()->unique()->sort()->values(),
                'pics' => $tickets->map(fn (Ticket $t) => $this->picLabel($t))->filter()->unique()->sort()->values(),
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

        $query = Ticket::with(['requester', 'approver', 'catalogSubject.supportAgent', 'catalogSubject.itAgent', 'attachments'])->whereNotNull('requester_id');

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
            'status' => $t->status,
            'sla' => $this->slaPresent($t),
            'createdAt' => $t->created_at->format('d M Y, H:i'),
            'createdAtIso' => $t->created_at->toIso8601String(),
            'description' => $t->description,
            'attachments' => $t->attachmentsPayload(),
            'approvalInfo' => $this->approvalInfo($t),
            'timeline' => $this->timelineSteps($t),
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
    private function slaPresent(Ticket $t): array
    {
        if (in_array($t->status, ['Draft', 'Waiting for Approval', 'Rejected'], true)) {
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
    private function picLabel(Ticket $t): ?string
    {
        $subject = $t->catalogSubject;
        if (! $subject) {
            return null;
        }

        $names = collect([$subject->supportAgent?->name, $subject->itAgent?->name])->filter()->values();

        return $names->isEmpty() ? null : $names->implode(' & ');
    }

    private function approvalInfo(Ticket $t): string
    {
        if (! $t->approver_id) {
            return 'Tiket ini tidak memerlukan approval.';
        }

        return match ($t->status) {
            'Waiting for Approval' => 'Menunggu approval dari '.($t->approver?->name ?? '—').'.',
            'Rejected' => 'Ditolak oleh '.($t->approver?->name ?? '—').'.',
            default => 'Disetujui oleh '.($t->approver?->name ?? '—').'.',
        };
    }

    /**
     * Fixed 6-slot timeline (Ticket dibuat / Approval / Assign PIC /
     * Progress / Resolved / Closed) derived purely from the ticket's
     * current fields — mirrors App\Support\TicketTimeline's read-only,
     * no-workflow-table approach, but in the admin console's fixed-slot
     * shape rather than a variable step list.
     */
    private function timelineSteps(Ticket $t): array
    {
        $inProgressStatuses = ['Assigned', 'In Progress', 'Waiting for Response'];

        return [
            ['label' => 'Ticket dibuat', 'value' => $t->created_at->format('d M Y, H:i')],
            ['label' => 'Approval', 'value' => match (true) {
                ! $t->approver_id => 'Tidak diperlukan',
                $t->status === 'Waiting for Approval' => 'Menunggu',
                $t->status === 'Rejected' => 'Ditolak',
                default => 'Disetujui',
            }],
            ['label' => 'Assign PIC', 'value' => $this->picLabel($t) ?? 'Menunggu Assignment'],
            ['label' => 'Progress', 'value' => match (true) {
                in_array($t->status, $inProgressStatuses, true) => $t->status,
                in_array($t->status, Ticket::DONE_STATUSES, true) => 'Selesai',
                default => 'Belum',
            }],
            ['label' => 'Resolved', 'value' => $t->resolved_at ? $t->resolved_at->format('d M Y, H:i') : 'Belum'],
            ['label' => 'Closed', 'value' => $t->status === 'Closed' ? $t->updated_at->format('d M Y, H:i') : 'Belum'],
        ];
    }
}
