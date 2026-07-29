<?php

namespace App\Models\Knowledge;

use App\Models\ServiceCatalogSubject;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Satu baris per pertanyaan yang masuk ke EVA.
 *
 * Ini sumber tunggal untuk Top Questions, Unanswered Questions, deflection
 * rate, dan jumlah pemakaian tiap artikel. Jangan pernah menyalin angka
 * turunannya menjadi kolom di kb_articles/kb_faqs.
 */
class AnswerLog extends Model
{
    public const OUTCOME_ANSWERED = 'answered';
    public const OUTCOME_CLARIFY = 'clarify';
    public const OUTCOME_NO_ANSWER = 'no_answer';
    public const OUTCOME_TICKET_DRAFT = 'ticket_draft';

    public const OUTCOMES = [
        self::OUTCOME_ANSWERED, self::OUTCOME_CLARIFY,
        self::OUTCOME_NO_ANSWER, self::OUTCOME_TICKET_DRAFT,
    ];

    protected $table = 'kb_answer_logs';

    protected $fillable = [
        'conversation_id', 'question', 'source_type', 'source_id',
        'catalog_subject_id', 'confidence', 'outcome', 'asked_by',
    ];

    protected $casts = [
        'confidence' => 'integer',
    ];

    public function conversation()
    {
        return $this->belongsTo(Conversation::class, 'conversation_id');
    }

    public function catalogSubject()
    {
        return $this->belongsTo(ServiceCatalogSubject::class, 'catalog_subject_id');
    }

    public function asker()
    {
        return $this->belongsTo(User::class, 'asked_by');
    }

    public function ratings()
    {
        return $this->hasMany(AnswerRating::class, 'answer_log_id');
    }

    /** Pertanyaan yang tidak terjawab — bahan Unanswered Questions. */
    public function scopeUnanswered(Builder $query): Builder
    {
        return $query->whereIn('outcome', [self::OUTCOME_NO_ANSWER, self::OUTCOME_TICKET_DRAFT]);
    }
}
