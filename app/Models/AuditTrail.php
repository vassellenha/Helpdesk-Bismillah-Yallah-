<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditTrail extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'actor_id', 'ip_address', 'url', 'module', 'action', 'target_type', 'target_id', 'target_name',
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
     * @param array{module:string,action:string,target_type:string,target_id:int|null,target_name:string,description:string,old_value?:array|null,new_value?:array|null,ip_address?:string|null,url?:string|null} $data
     */
    public static function record(User $actor, array $data): self
    {
        return static::create([
            'actor_id' => $actor->id,
            'ip_address' => $data['ip_address'] ?? self::currentIp(),
            'url' => $data['url'] ?? self::currentUrl(),
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

    /**
     * request() is always bound, even in an artisan command — but only a
     * routed request (real HTTP dispatch, including test-simulated ones)
     * carries a meaningful IP/URL. A console-triggered entry (EmployeeSync's
     * scheduled sync) has no route, so it gets no IP/URL instead of a
     * misleading 127.0.0.1/localhost.
     */
    private static function hasRoutedRequest(): bool
    {
        return app()->bound('request') && request()->route() !== null;
    }

    private static function currentIp(): ?string
    {
        return self::hasRoutedRequest() ? request()->ip() : null;
    }

    private static function currentUrl(): ?string
    {
        return self::hasRoutedRequest() ? request()->fullUrl() : null;
    }
}
