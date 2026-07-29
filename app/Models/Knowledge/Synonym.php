<?php

namespace App\Models\Knowledge;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Satu kelompok kata yang dianggap setara saat mencari. */
class Synonym extends Model
{
    /** Minimal dua kata; satu kata bukan kelompok sinonim. */
    public const MIN_TERMS = 2;

    protected $table = 'kb_synonyms';

    protected $fillable = ['terms', 'is_active', 'note'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /** @return string[] */
    public function termList(): array
    {
        return self::splitTerms($this->terms);
    }

    /**
     * Pemecah tunggal untuk seluruh aplikasi — dipakai model, validasi, dan
     * SynonymExpander. Kalau aturan pemisahnya ditulis di tiga tempat, cepat
     * atau lambat ketiganya akan berbeda soal spasi dan huruf besar.
     *
     * @return string[]
     */
    public static function splitTerms(?string $terms): array
    {
        $parts = array_map(
            fn (string $term) => mb_strtolower(trim($term)),
            explode(',', (string) $terms),
        );

        return array_values(array_unique(array_filter($parts, fn (string $t) => $t !== '')));
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
