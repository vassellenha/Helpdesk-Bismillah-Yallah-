<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceCatalogSubcategory extends Model
{
    protected $table = 'service_catalog_subcategories';

    protected $fillable = ['service_id', 'name'];

    public function service()
    {
        return $this->belongsTo(ServiceCatalogService::class, 'service_id');
    }

    public function subjects()
    {
        return $this->hasMany(ServiceCatalogSubject::class, 'subcategory_id');
    }
}
