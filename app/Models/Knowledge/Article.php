<?php

namespace App\Models\Knowledge;

use App\Models\ServiceCatalogSubject;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;

/**
 * Ringkasan sebuah dokumen.
 *
 * Sengaja TIDAK punya kolom rating/helpful/views — semuanya diagregasi dari
 * kb_answer_logs & kb_answer_ratings lewat ArticleStats.
 */
class Article extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_PUBLISHED = 'published';

    public const STATUSES = [self::STATUS_DRAFT, self::STATUS_PUBLISHED];

    protected $table = 'kb_articles';

    protected $fillable = [
        'title', 'summary', 'body', 'source_document_id', 'catalog_subject_id',
        'status', 'is_eva_visible', 'tags', 'author_id', 'updated_by',
    ];

    protected $casts = [
        'is_eva_visible' => 'boolean',
    ];

    public function sourceDocument()
    {
        return $this->belongsTo(Document::class, 'source_document_id');
    }

    public function catalogSubject()
    {
        return $this->belongsTo(ServiceCatalogSubject::class, 'catalog_subject_id');
    }

    /**
     * Subject TAMBAHAN yang dilayani artikel ini, di luar subject utama.
     *
     * Jangan dibaca langsung untuk menghitung cakupan — pakai allSubjectIds()
     * agar subject utama selalu ikut terhitung.
     */
    public function subjects(): BelongsToMany
    {
        return $this->belongsToMany(
            ServiceCatalogSubject::class,
            'kb_article_subject',
            'article_id',
            'subject_id',
        )->withTimestamps();
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * Penyunting TERAKHIR — berbeda dari author, yang tidak pernah berubah.
     * Null berarti isinya masih sebagaimana lahir dari dokumennya.
     */
    public function editor()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Satu-satunya jawaban atas "artikel ini melayani subject apa saja":
     * subject utama ∪ tautan tambahan, tanpa duplikat.
     *
     * @return Collection<int,int>
     */
    public function allSubjectIds(): Collection
    {
        $extra = $this->relationLoaded('subjects')
            ? $this->subjects->pluck('id')
            : $this->subjects()->pluck('service_catalog_subjects.id');

        return collect([$this->catalog_subject_id])
            ->merge($extra)
            ->filter()
            ->unique()
            ->values();
    }

    /**
     * Berapa artikel siap-jawab yang menutup tiap subject katalog.
     *
     * Gerbangnya tetap scopeAnswerable() — satu-satunya definisi "boleh dipakai
     * EVA" — sehingga tautan tambahan tidak pernah bisa membuat artikel draf
     * terlihat menutup sebuah subject di layar Coverage.
     *
     * @return Collection<int,int> subject_id => jumlah artikel
     */
    public static function answerableCountsBySubject(): Collection
    {
        return static::query()->answerable()
            ->with('subjects:id')
            ->get(['id', 'catalog_subject_id'])
            ->flatMap(fn (self $article) => $article->allSubjectIds())
            ->countBy()
            ->sortKeys();
    }

    public function testCases(): MorphMany
    {
        return $this->morphMany(TestCase::class, 'testable');
    }

    /**
     * Satu-satunya definisi "artikel yang boleh dipakai EVA menjawab".
     * Dipakai FulltextKnowledgeSearch maupun perhitungan coverage supaya
     * keduanya tidak pernah berbeda pendapat.
     */
    public function scopeAnswerable(Builder $query): Builder
    {
        return $query->where('is_eva_visible', true)
            ->where('status', self::STATUS_PUBLISHED);
    }
}
