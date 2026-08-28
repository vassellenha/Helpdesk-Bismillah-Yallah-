<?php

namespace App\Models\Knowledge;

use App\Models\ServiceCatalogSubject;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
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

    /**
     * Satu-satunya definisi "dokumen yang boleh dibuka dari kutipan EVA".
     *
     * Dua syarat, dan keduanya wajib:
     *   1. Dokumennya sendiri tidak disembunyikan admin dari EVA.
     *   2. Artikel turunannya lolos gerbang menjawab (scopeAnswerable) — sebab
     *      itulah satu-satunya alasan dokumen ini bisa dikutip sama sekali.
     *
     * Tanpa syarat kedua, endpoint berkas menjadi jalan mengunduh SOP internal
     * cukup dengan menebak nomor dokumen — termasuk dokumen yang artikelnya
     * masih draf dan belum pernah boleh dibaca siapa pun di luar konsol admin.
     */
    public function scopeQuotable(Builder $query): Builder
    {
        return $query->where('is_eva_visible', true)
            ->whereHas('article', fn (Builder $article) => $article->answerable());
    }

    /**
     * Berkas aslinya ada di disk? Dokumen boleh lahir dari teks yang diketik
     * admin langsung — baris seperti itu tidak punya berkas, dan itu keadaan
     * yang wajar, bukan kerusakan.
     */
    public function hasFile(): bool
    {
        return filled($this->storage_path);
    }

    public function isIndexed(): bool
    {
        return $this->status === self::STATUS_INDEXED;
    }
}
