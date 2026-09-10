<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceCatalogSubcategory extends Model
{
    protected $table = 'service_catalog_subcategories';

    protected $fillable = ['service_id', 'name', 'team_lead_bpo_user_id'];

    public function service()
    {
        return $this->belongsTo(ServiceCatalogService::class, 'service_id');
    }

    public function subjects()
    {
        return $this->hasMany(ServiceCatalogSubject::class, 'subcategory_id');
    }

    /**
     * Team Lead BPO yang mengawasi Subkategori ini. NULL bukan keadaan
     * rusak: Subkategori tanpa pemilik tetap terbaca oleh SEMUA Team Lead
     * BPO — lihat App\Support\TeamLeadScope.
     */
    public function teamLeadBpo()
    {
        return $this->belongsTo(User::class, 'team_lead_bpo_user_id');
    }
}
