<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class Ticket extends Model
{
    public const ACTIVE_STATUSES = ['Waiting for Approval', 'Open', 'Assigned', 'In Progress', 'Waiting for Response'];

    public const DONE_STATUSES = ['Resolved', 'Completed', 'Closed'];

    protected $fillable = [
        'ticket_no', 'title', 'requester_name', 'requester_id',
        'service_name', 'subcategory_name', 'subject_name', 'issue_category', 'description',
        'attachment_name', 'attachment_path',
        'category', 'sla_policy_id', 'priority', 'approver_id', 'assigned_agent_id', 'catalog_subject_id',
        'response_time_minutes', 'resolution_time_minutes', 'warning_threshold_percent',
        'response_due_at', 'resolution_due_at', 'warning_at',
        'status', 'is_draft', 'resolved_at', 'satisfaction_rating',
    ];

    protected $casts = [
        'response_due_at' => 'datetime',
        'resolution_due_at' => 'datetime',
        'warning_at' => 'datetime',
        'resolved_at' => 'datetime',
        'is_draft' => 'boolean',
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
        if (in_array($this->status, ['Draft', 'Waiting for Approval', 'Rejected'], true)) {
            return null;
        }

        if (in_array($this->status, self::DONE_STATUSES, true)) {
            return $this->resolved_at
                ? $this->resolved_at->diffInMinutes($this->resolution_due_at, false)
                : 0;
        }

        return Carbon::now()->diffInMinutes($this->resolution_due_at, false);
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

        if (in_array($this->status, ['Draft', 'Rejected'], true)) {
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
