<?php

namespace App\Models\Knowledge;

use App\Models\ServiceCatalogSubject;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * FAQ ditulis admin dan langsung tayang — tidak ada status/review.
 * Satu-satunya gerbang adalah is_eva_visible.
 */
class Faq extends Model
{
    protected $table = 'kb_faqs';

    protected $fillable = [
        'question', 'answer', 'catalog_subject_id', 'is_eva_visible', 'tags', 'author_id',
    ];

    protected $casts = [
        'is_eva_visible' => 'boolean',
    ];

    public function catalogSubject()
    {
        return $this->belongsTo(ServiceCatalogSubject::class, 'catalog_subject_id');
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function testCases(): MorphMany
    {
        return $this->morphMany(TestCase::class, 'testable');
    }

    public function scopeAnswerable(Builder $query): Builder
    {
        return $query->where('is_eva_visible', true);
    }
}
