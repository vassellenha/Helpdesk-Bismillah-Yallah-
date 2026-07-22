<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * The leaf node of Issue Category → Layanan → Sub Category → Subject.
 * Support and Level are configured per Subject (this row), never inherited
 * from the Layanan or Sub Category — see ServiceCatalogController::updateSupport().
 */
class ServiceCatalogSubject extends Model
{
    protected $table = 'service_catalog_subjects';

    protected $fillable = [
        'issue_category_id', 'service_id', 'subcategory_id', 'name',
        'requires_approval', 'support_agent_id', 'it_agent_id', 'support_level', 'is_active',
    ];

    protected $casts = [
        'requires_approval' => 'boolean',
        'is_active' => 'boolean',
        'support_level' => 'integer',
    ];

    public function issueCategory()
    {
        return $this->belongsTo(IssueCategory::class);
    }

    public function service()
    {
        return $this->belongsTo(ServiceCatalogService::class, 'service_id');
    }

    public function subcategory()
    {
        return $this->belongsTo(ServiceCatalogSubcategory::class, 'subcategory_id');
    }

    public function supportAgent()
    {
        return $this->belongsTo(SupportAgent::class);
    }

    public function itAgent()
    {
        return $this->belongsTo(SupportAgent::class, 'it_agent_id');
    }
}
