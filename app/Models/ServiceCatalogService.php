<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * "Layanan" — covers both applications (SAP, ADELE) and non-application
 * services (VPN, Network, Printer). Deliberately not named "Application".
 */
class ServiceCatalogService extends Model
{
    protected $table = 'service_catalog_services';

    protected $fillable = ['name'];

    public function subcategories()
    {
        return $this->hasMany(ServiceCatalogSubcategory::class, 'service_id');
    }

    public function subjects()
    {
        return $this->hasMany(ServiceCatalogSubject::class, 'service_id');
    }
}
