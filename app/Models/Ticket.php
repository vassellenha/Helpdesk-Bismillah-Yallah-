<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property string $ticket_no
 * @property string $title
 * @property string|null $requester_name
 * @property int|null $requester_id
 * @property string|null $service_name
 * @property string|null $subcategory_name
 * @property string|null $subject_name
 * @property string|null $issue_category
 * @property string|null $description
 * @property string|null $category
 * @property int|null $sla_policy_id
 * @property string $priority
 * @property int|null $approver_id
 * @property int|null $assigned_agent_id
 * @property int|null $catalog_subject_id
 * @property int $response_time_minutes
 * @property int $resolution_time_minutes
 * @property int $warning_threshold_percent
 * @property Carbon $response_due_at
 * @property Carbon $resolution_due_at
 * @property Carbon $warning_at
 * @property string $status
 * @property bool $is_draft
 * @property Carbon|null $resolved_at
 * @property int|null $satisfaction_rating
 * @property bool $rating_active
 * @property string|null $feedback_note
 * @property Carbon|null $escalated_at
 * @property string|null $escalation_note
 * @property string|null $reopen_note
 * @property Carbon|null $reopen_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read string $sla_status
 * @property-read int|null $sla_minutes_remaining
 * @property-read int $sla_elapsed_percent
 * @property-read int $effective_resolution_minutes
 * @property-read int|null $response_minutes_remaining
 * @property-read string $response_kind
 * @property-read string $response_label
 * @property-read int $response_elapsed_percent
 * @property-read \Illuminate\Support\Carbon|null $sla_ended_at
 * @property-read string $response_target_label
 * @property-read string $resolution_target_label
 * @property-read string $sla_kind
 * @property-read string $sla_label
 * @property-read SlaPolicy|null $slaPolicy
 * @property-read User|null $requester
 * @property-read User|null $approver
 * @property-read \Illuminate\Database\Eloquent\Collection<int, TicketComment> $comments
 * @property-read SupportAgent|null $assignedAgent
 * @property-read ServiceCatalogSubject|null $catalogSubject
 * @property-read \Illuminate\Database\Eloquent\Collection<int, TicketNotification> $notifications
 * @property-read \Illuminate\Database\Eloquent\Collection<int, TicketApproval> $approvals
 * @property-read \Illuminate\Database\Eloquent\Collection<int, TicketAttachment> $attachments
 */
class Ticket extends Model
{
    public const ACTIVE_STATUSES = ['Waiting for Approval', 'Open', 'Assigned', 'In Progress', 'Waiting for Response'];

    public const DONE_STATUSES = ['Resolved', 'Completed', 'Closed'];

    /** Statuses with no active SLA countdown — never submitted, not yet decided, or dead-ended. */
    public const NO_SLA_STATUSES = ['Draft', 'Returned', 'Waiting for Approval', 'Rejected'];

    /**
     * Statuses where the ticket hasn't actually reached Support yet — still
     * being drafted/edited by the Requester, sitting with an Approver, or
     * dead-ended before ever routing to Support. `assigned_agent_id` is
     * frozen at creation time (see TicketController::store()), so without
     * this check Support could otherwise see/act on a ticket the Approver
     * hasn't released to them.
     */
    public const NOT_YET_RELEASED_STATUSES = ['Draft', 'Returned', 'Waiting for Approval', 'Rejected'];

    protected $fillable = [
        'ticket_no', 'title', 'requester_name', 'requester_id',
        'service_name', 'subcategory_name', 'subject_name', 'issue_category', 'description',
        'category', 'sla_policy_id', 'priority', 'approver_id', 'assigned_agent_id', 'catalog_subject_id',
        'response_time_minutes', 'resolution_time_minutes', 'warning_threshold_percent',
        'response_due_at', 'resolution_due_at', 'warning_at',
        'sla_started_at', 'first_response_at', 'sla_extension_minutes',
        'status', 'is_draft', 'resolved_at', 'satisfaction_rating', 'rating_active', 'feedback_note',
        'escalated_at', 'escalation_note',
        'reopen_note', 'reopen_at',
    ];

    protected $casts = [
        'response_due_at' => 'datetime',
        'resolution_due_at' => 'datetime',
        'warning_at' => 'datetime',
        'reopen_at' => 'datetime',
        'resolved_at' => 'datetime',
        'escalated_at' => 'datetime',
        'sla_started_at' => 'datetime',
        'first_response_at' => 'datetime',
        'sla_extension_minutes' => 'integer',
        'is_draft' => 'boolean',
        'rating_active' => 'boolean',
    ];

    public function slaPolicy()
    {
        return $this->belongsTo(SlaPolicy::class);
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    public function comments()
    {
        return $this->hasMany(TicketComment::class)->orderBy('created_at');
    }

    public function assignedAgent()
    {
        return $this->belongsTo(SupportAgent::class, 'assigned_agent_id');
    }

    public function catalogSubject()
    {
        return $this->belongsTo(ServiceCatalogSubject::class, 'catalog_subject_id');
    }

    public function notifications()
    {
        return $this->hasMany(TicketNotification::class);
    }

    public function approvals()
    {
        return $this->hasMany(TicketApproval::class)->orderBy('created_at');
    }

    public function attachments()
    {
        return $this->hasMany(TicketAttachment::class)->orderBy('created_at');
    }

    /**
     * Shared shape for `attachments` across every ticket-detail/list
     * endpoint (Requester, Approver, Support, Support BPO, Admin), so a
     * ticket's files are presented identically everywhere instead of each
     * controller hand-rolling the Storage URL lookup.
     */
    /**
     * A row can outlive its file — storage wiped, restored from a DB-only
     * backup, cleaned up by hand. Report that explicitly instead of handing the
     * UI a URL that 404s, which renders as a broken-image icon with no
     * explanation of what went wrong.
     */
    public function attachmentsPayload(): array
    {
        return $this->attachments->map(function (TicketAttachment $a) {
            $exists = $a->path && Storage::disk('public')->exists($a->path);

            return [
                'id' => $a->id,
                'name' => $a->name,
                'url' => $exists ? Storage::disk('public')->url($a->path) : null,
                'missing' => ! $exists,
            ];
        })->values()->all();
    }

    /**
     * Ticket numbers are unique and far more readable in a URL than the
     * numeric id, so `{ticket}` route params resolve by ticket_no.
     */
    public function getRouteKeyName(): string
    {
        return 'ticket_no';
    }

    /**
     * SLA status computed live against server time, never stored — a ticket's
     * snapshot (due dates) is fixed at creation, but its Within/Warning/Breach
     * state must always reflect "now".
     */
    public function getSlaStatusAttribute(): string
    {
        $now = Carbon::now();

        if ($now->greaterThanOrEqualTo($this->resolution_due_at)) {
            return 'Breach';
        }

        if ($now->greaterThanOrEqualTo($this->warning_at)) {
            return 'Warning';
        }

        return 'Within SLA';
    }

    /**
     * Signed minutes to the resolution deadline (negative once breached).
     * Drives both the "Tickets Approaching SLA Limit" sort order and the
     * table's countdown label — kept as one number so sorting never drifts
     * out of sync with what the label says.
     */
    public function getSlaMinutesRemainingAttribute(): ?int
    {
        if (in_array($this->status, self::NO_SLA_STATUSES, true)) {
            return null;
        }

        if (in_array($this->status, self::DONE_STATUSES, true)) {
            return $this->resolved_at
                ? (int) $this->resolved_at->diffInMinutes($this->resolution_due_at, false)
                : 0;
        }

        return (int) Carbon::now()->diffInMinutes($this->resolution_due_at, false);
    }

    public function getResponseTargetLabelAttribute(): string
    {
        return self::humanMinutes($this->response_time_minutes);
    }

    public function getResolutionTargetLabelAttribute(): string
    {
        return self::humanMinutes($this->resolution_time_minutes);
    }

    /**
     * The resolution window actually in force: the policy's commitment plus
     * whatever escalation has granted. Kept separate from
     * resolution_time_minutes so the original promise stays readable.
     */
    public function getEffectiveResolutionMinutesAttribute(): int
    {
        return $this->resolution_time_minutes + $this->sla_extension_minutes;
    }

    /*
    |--------------------------------------------------------------------------
    | Response SLA
    |--------------------------------------------------------------------------
    |
    | Deliberately its own clock, not a slice of the resolution one. The two
    | answer different questions — "did anyone pick this up in time?" versus
    | "was it finished in time?" — and they stop at different moments: response
    | freezes the instant Support first replies (first_response_at), while
    | resolution keeps running until the ticket is resolved. A ticket can meet
    | one and breach the other, so neither may be derived from the other.
    |
    */

    /** Signed minutes to the response deadline; frozen once Support has replied. */
    public function getResponseMinutesRemainingAttribute(): ?int
    {
        if (in_array($this->status, self::NO_SLA_STATUSES, true) || $this->response_due_at === null) {
            return null;
        }

        if ($this->first_response_at !== null) {
            return (int) $this->first_response_at->diffInMinutes($this->response_due_at, false);
        }

        return (int) Carbon::now()->diffInMinutes($this->response_due_at, false);
    }

    /** met | breach | warning | ontrack | none */
    public function getResponseKindAttribute(): string
    {
        $minutes = $this->response_minutes_remaining;

        if ($minutes === null) {
            return 'none';
        }

        if ($this->first_response_at !== null) {
            return $minutes >= 0 ? 'met' : 'breach';
        }

        if ($minutes <= 0) {
            return 'breach';
        }

        // Same warning threshold the resolution clock uses, applied to this window.
        $consumed = $this->response_time_minutes > 0
            ? (1 - $minutes / $this->response_time_minutes) * 100
            : 0;

        return $consumed >= $this->warning_threshold_percent ? 'warning' : 'ontrack';
    }

    public function getResponseLabelAttribute(): string
    {
        $minutes = $this->response_minutes_remaining;

        if ($minutes === null) {
            return '—';
        }

        if ($this->first_response_at !== null) {
            return $minutes >= 0
                ? 'Direspons '.self::formatDuration($minutes).' sebelum batas'
                : 'Terlambat respons '.self::formatDuration(abs($minutes));
        }

        return $minutes <= 0
            ? 'Belum direspons · lewat '.self::formatDuration(abs($minutes))
            : 'Belum direspons · sisa '.self::formatDuration($minutes);
    }

    public function getResponseElapsedPercentAttribute(): int
    {
        if ($this->response_minutes_remaining === null || $this->response_time_minutes <= 0) {
            return 0;
        }

        if ($this->response_kind === 'breach') {
            return 100;
        }

        $elapsed = (int) round((1 - max($this->response_minutes_remaining, 0) / $this->response_time_minutes) * 100);

        return max(0, min(100, $elapsed));
    }

    /** When the resolution clock stopped, or null while it is still running. */
    public function getSlaEndedAtAttribute(): ?Carbon
    {
        return in_array($this->status, self::DONE_STATUSES, true) ? $this->resolved_at : null;
    }

    /** "90 minutes" / "4 hours" / "2 days" — one wording for every role's SLA panel. */
    private static function humanMinutes(int $minutes): string
    {
        if ($minutes % 1440 === 0) {
            return ($minutes / 1440).' day'.($minutes / 1440 > 1 ? 's' : '');
        }

        if ($minutes % 60 === 0) {
            return ($minutes / 60).' hour'.($minutes / 60 > 1 ? 's' : '');
        }

        return "{$minutes} minutes";
    }

    /**
     * How much of the resolution window has been consumed, 0–100, for the SLA
     * progress bar. Lives here rather than in a controller because every role's
     * ticket detail draws the same bar and they must not drift apart.
     */
    public function getSlaElapsedPercentAttribute(): int
    {
        $window = $this->effective_resolution_minutes;

        if ($this->sla_minutes_remaining === null || $window <= 0) {
            return 0;
        }

        if ($this->sla_kind === 'breach') {
            return 100;
        }

        // Measured against the extended window, otherwise granting extra time on
        // escalation would make the bar jump backwards.
        $elapsed = (int) round((1 - max($this->sla_minutes_remaining, 0) / $window) * 100);

        return max(0, min(100, $elapsed));
    }

    /**
     * Grants extra resolution time because the ticket changed hands. Pushes the
     * resolution deadline and the warning mark out together — moving only the
     * deadline would leave the warning already in the past, so the ticket would
     * light up amber the instant it was escalated.
     *
     * Returns the minutes granted (0 when the ticket has no live clock, e.g. it
     * is already resolved, so an escalation never revives a finished SLA).
     */
    public function grantEscalationExtension(): int
    {
        if (in_array($this->status, [...self::DONE_STATUSES, ...self::NO_SLA_STATUSES], true)) {
            return 0;
        }

        $percent = (int) config('helpdesk.sla.escalation_extension_percent', 50);
        $minutes = (int) round($this->resolution_time_minutes * $percent / 100);

        if ($minutes <= 0) {
            return 0;
        }

        $this->forceFill([
            'sla_extension_minutes' => $this->sla_extension_minutes + $minutes,
            'resolution_due_at' => $this->resolution_due_at?->clone()->addMinutes($minutes),
            'warning_at' => $this->warning_at?->clone()->addMinutes($minutes),
        ])->save();

        return $minutes;
    }

    /**
     * Stamps the moment Support first replied, which stops the response clock.
     * Idempotent — only the first reply counts, later ones must not reset it.
     */
    public function markFirstResponse(?Carbon $at = null): bool
    {
        if ($this->first_response_at !== null) {
            return false;
        }

        $this->forceFill(['first_response_at' => $at ?? Carbon::now()])->save();

        return true;
    }

    /**
     * Everything a ticket's SLA panel needs, in one shape every role shares —
     * five screens drawing the same numbers from five hand-rolled arrays is how
     * they drift apart.
     *
     * @return array<string,mixed>
     */
    public function slaPayload(): array
    {
        return [
            'label' => $this->sla_label,
            'kind' => $this->sla_kind,
            'pct' => $this->sla_elapsed_percent,
            'responseTarget' => $this->response_target_label,
            'resolutionTarget' => $this->resolution_target_label,
            'priority' => $this->priority,
            'startedAt' => optional($this->sla_started_at ?? $this->created_at)->format('d M Y · H:i'),
            'endedAt' => optional($this->sla_ended_at)->format('d M Y · H:i'),
            'dueAt' => optional($this->resolution_due_at)->format('d M Y · H:i'),
            'response' => [
                'label' => $this->response_label,
                'kind' => $this->response_kind,
                'pct' => $this->response_elapsed_percent,
                'at' => optional($this->first_response_at)->format('d M Y · H:i'),
                'dueAt' => optional($this->response_due_at)->format('d M Y · H:i'),
            ],
            'extensionMinutes' => $this->sla_extension_minutes,
            'extensionLabel' => $this->sla_extension_minutes > 0
                ? self::humanMinutes($this->sla_extension_minutes)
                : null,
        ];
    }

    public function getSlaKindAttribute(): string
    {
        if (in_array($this->status, self::DONE_STATUSES, true)) {
            return 'ontrack';
        }

        $minutes = $this->sla_minutes_remaining;

        if ($minutes === null) {
            return 'none';
        }

        if ($minutes <= 0) {
            return 'breach';
        }

        $warningMinutes = Carbon::now()->diffInMinutes($this->warning_at, false);

        return $warningMinutes <= 0 ? 'warning' : 'ontrack';
    }

    public function getSlaLabelAttribute(): string
    {
        if (in_array($this->status, self::DONE_STATUSES, true)) {
            return 'Met';
        }

        if ($this->status === 'Waiting for Approval') {
            return 'Not started';
        }

        if (in_array($this->status, ['Draft', 'Returned', 'Rejected'], true)) {
            return '—';
        }

        $minutes = $this->sla_minutes_remaining ?? 0;

        if ($minutes <= 0) {
            return 'Breach +'.self::formatDuration(abs($minutes));
        }

        return self::formatDuration($minutes).' left';
    }

    protected static function formatDuration(int $minutes): string
    {
        $days = intdiv($minutes, 1440);
        $hours = intdiv($minutes % 1440, 60);
        $mins = $minutes % 60;

        if ($days > 0) {
            return $hours > 0 ? "{$days}d {$hours}h" : "{$days}d";
        }

        if ($hours > 0) {
            return $mins > 0 ? "{$hours}h {$mins}m" : "{$hours}h";
        }

        return "{$mins} min";
    }
}
