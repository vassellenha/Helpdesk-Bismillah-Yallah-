<?php

namespace App\Models\Knowledge;

use Illuminate\Database\Eloquent\Model;

/** Potongan teks dokumen. Kolom embedding menyusul saat pindah ke pgvector. */
class Chunk extends Model
{
    protected $table = 'kb_chunks';

    protected $fillable = ['document_id', 'ordinal', 'content'];

    protected $casts = [
        'ordinal' => 'integer',
    ];

    public function document()
    {
        return $this->belongsTo(Document::class, 'document_id');
    }
}
