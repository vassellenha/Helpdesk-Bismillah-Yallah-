<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditTrail extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'actor_id', 'module', 'action', 'target_type', 'target_id', 'target_name',
        'old_value', 'new_value', 'description',
    ];

    protected $casts = [
        'old_value' => 'array',
        'new_value' => 'array',
        'created_at' => 'datetime',
    ];

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * @param array{module:string,action:string,target_type:string,target_id:int|null,target_name:string,description:string,old_value?:array|null,new_value?:array|null} $data
     */
    public static function record(User $actor, array $data): self
    {
        return static::create([
            'actor_id' => $actor->id,
            'module' => $data['module'],
            'action' => $data['action'],
            'target_type' => $data['target_type'],
            'target_id' => $data['target_id'] ?? null,
            'target_name' => $data['target_name'],
            'old_value' => $data['old_value'] ?? null,
            'new_value' => $data['new_value'] ?? null,
            'description' => $data['description'],
        ]);
    }
}
