<?php

namespace App\Models\Knowledge;

use App\Models\ServiceCatalogSubject;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Berkas sumber Knowledge Base. Hulu dari segalanya: artikel lahir dari sini,
 * tidak pernah ditulis manual.
 */
class Document extends Model
{
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_INDEXED = 'indexed';
    public const STATUS_FAILED = 'failed';

    public const STATUSES = [self::STATUS_PROCESSING, self::STATUS_INDEXED, self::STATUS_FAILED];

    protected $table = 'kb_documents';

    protected $fillable = [
        'name', 'original_filename', 'extension', 'size_bytes', 'storage_path',
        'extracted_text', 'catalog_subject_id', 'status', 'failure_reason', 'page_count',
        'is_eva_visible', 'tags', 'uploaded_by', 'indexed_at',
    ];

    protected $casts = [
        'is_eva_visible' => 'boolean',
        'page_count' => 'integer',
        'size_bytes' => 'integer',
        'indexed_at' => 'datetime',
    ];

    public function catalogSubject()
    {
        return $this->belongsTo(ServiceCatalogSubject::class, 'catalog_subject_id');
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /** Satu dokumen melahirkan tepat satu artikel — ditegakkan unique di DB. */
    public function article()
    {
        return $this->hasOne(Article::class, 'source_document_id');
    }

    public function chunks()
    {
        return $this->hasMany(Chunk::class, 'document_id');
    }

    public function isIndexed(): bool
    {
        return $this->status === self::STATUS_INDEXED;
    }
}
