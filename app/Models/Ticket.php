<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class Ticket extends Model
{
    protected $fillable = [
        'ticket_no', 'title', 'requester_name', 'category',
        'sla_policy_id', 'priority',
        'response_time_minutes', 'resolution_time_minutes', 'warning_threshold_percent',
        'response_due_at', 'resolution_due_at', 'warning_at',
        'status',
    ];

    protected $casts = [
        'response_due_at' => 'datetime',
        'resolution_due_at' => 'datetime',
        'warning_at' => 'datetime',
    ];

    public function slaPolicy()
    {
        return $this->belongsTo(SlaPolicy::class);
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
}
