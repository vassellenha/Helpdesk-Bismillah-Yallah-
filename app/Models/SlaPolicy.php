<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SlaPolicy extends Model
{
    protected $fillable = [
        'policy_name',
        'priority',
        'response_time_minutes',
        'resolution_time_minutes',
        'escalation_extension_minutes',
        'warning_threshold_percent',
        'status',
    ];

    protected $casts = [
        'response_time_minutes' => 'integer',
        'resolution_time_minutes' => 'integer',
        'escalation_extension_minutes' => 'integer',
        'warning_threshold_percent' => 'integer',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }
}
