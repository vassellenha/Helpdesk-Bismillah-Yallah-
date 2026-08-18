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
        return $this->serviceIdsFor('support_agent_id');
    }

    /**
     * Pasangan IT dari bpoServiceIds() di atas — dipakai SupportController
     * untuk menampilkan tiket "Lainnya" hasil eskalasi yang belum diklaim
     * PIC IT manapun (lihat ServiceCatalogService::activeItAgents()).
     *
     * @return \Illuminate\Support\Collection<int,int>
     */
    public function itServiceIds()
    {
        return $this->serviceIdsFor('it_agent_id');
    }

    /**
     * Dicocokkan ke SEMUA baris SupportAgent milik ORANG yang sama, bukan
     * cuma baris $this — sebagian orang punya lebih dari satu baris untuk
     * satu user_id (dobel peran BPO & IT, atau baris lama yang tidak
     * dibereskan waktu namanya diperbarui). Kalau Subject menunjuk baris
     * yang berbeda dari baris yang dikembalikan agentFor() saat login,
     * tiket broadcast-nya jadi tidak pernah muncul di daftar orang itu —
     * padahal loncengnya tetap bunyi, karena TicketBroadcast::eligiblePics()
     * dan canAct() mencocokkan lewat user_id. Itu ketimpangan yang bikin
     * tiket hasil eskalasi "hilang": kelihatan di notifikasi, tidak ada di
     * My Tickets.
     *
     * Yang TIDAK ikut dilebarkan: pencocokan assigned_agent_id di
     * visibleTicketsQuery — di sana filter per baris justru yang menjaga
     * tiket yang sudah dieskalasi ke baris IT seseorang tidak ikut nongol
     * di portal BPO-nya.
     *
     * @return \Illuminate\Support\Collection<int,int>
     */
    private function serviceIdsFor(string $column)
    {
        $myRowIds = $this->user_id
            ? static::where('user_id', $this->user_id)->pluck('id')
            : collect([$this->id]);

        return ServiceCatalogSubject::whereIn($column, $myRowIds)
            ->where('is_active', true)
            ->distinct()
            ->pluck('service_id');
    }
}
