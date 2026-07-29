<?php

namespace App\Support\Eva;

use App\Models\ServiceCatalogSubject;
use Illuminate\Support\Facades\Cache;

/**
 * Daftar subject katalog untuk dropdown di layar EVA.
 *
 * Dibaca saja — EVA tidak pernah membuat, mengubah, atau menghapus baris
 * service_catalog_* (aturan #5). Kalau sebuah subject perlu ditambah, itu
 * pekerjaan Admin di Service Catalog, bukan di sini.
 */
final class CatalogOptions
{
    private const CACHE_KEY = 'eva.catalog-subject-options';
    private const CACHE_TTL_SECONDS = 300;

    public static function all(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL_SECONDS, function () {
            return ServiceCatalogSubject::query()
                ->where('service_catalog_subjects.is_active', true)
                ->join('service_catalog_services as svc', 'svc.id', '=', 'service_catalog_subjects.service_id')
                ->join('service_catalog_subcategories as sub', 'sub.id', '=', 'service_catalog_subjects.subcategory_id')
                ->orderBy('svc.name')
                ->orderBy('sub.name')
                ->orderBy('service_catalog_subjects.name')
                ->get([
                    'service_catalog_subjects.id',
                    'service_catalog_subjects.name as subject',
                    'svc.name as service',
                    'sub.name as subcategory',
                ])
                ->map(fn ($row) => [
                    'id' => $row->id,
                    'service' => $row->service,
                    'subcategory' => $row->subcategory,
                    'subject' => $row->subject,
                    'label' => $row->service.' › '.$row->subcategory.' › '.$row->subject,
                ])
                ->all();
        });
    }

    /** Daftar nama layanan untuk filter, diturunkan dari daftar yang sama. */
    public static function services(): array
    {
        return array_values(array_unique(array_column(self::all(), 'service')));
    }
}
