<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string $name
 * @property string|null $email
 * @property string $type
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property-read User|null $user
 */
class SupportAgent extends Model
{
    protected $fillable = ['name', 'email', 'type', 'is_active', 'user_id'];

    protected $casts = ['is_active' => 'boolean'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * ID Layanan tempat agent ini sudah jadi PIC BPO di salah satu Subject
     * aktifnya — dipakai SupportBpoController untuk menampilkan tiket
     * "Lainnya" yang belum diklaim siapa pun (lihat
     * ServiceCatalogService::activeBpoAgents(), sisi baliknya).
     *
     * @return \Illuminate\Support\Collection<int,int>
     */
    public function bpoServiceIds()
    {
        return ServiceCatalogSubject::where('support_agent_id', $this->id)
            ->where('is_active', true)
            ->distinct()
            ->pluck('service_id');
    }
}
