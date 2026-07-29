<?php

namespace App\Models\Knowledge;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/** Bintang 1–5 atas satu jawaban EVA. Sekali nilai per (jawaban, penilai). */
class AnswerRating extends Model
{
    public const MIN_STARS = 1;
    public const MAX_STARS = 5;

    /** Ambang "membantu" yang dipakai seluruh laporan Rating & Feedback. */
    public const HELPFUL_THRESHOLD = 4;

    protected $table = 'kb_answer_ratings';

    protected $fillable = ['answer_log_id', 'rated_by', 'stars', 'reason', 'comment'];

    protected $casts = [
        'stars' => 'integer',
    ];

    public function answerLog()
    {
        return $this->belongsTo(AnswerLog::class, 'answer_log_id');
    }

    public function rater()
    {
        return $this->belongsTo(User::class, 'rated_by');
    }
}
