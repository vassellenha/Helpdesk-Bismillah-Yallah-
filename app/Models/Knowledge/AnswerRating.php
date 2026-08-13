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

    /**
     * Bintang yang PERNAH diberikan orang ini untuk MATERI ini, atau null.
     *
     * Penilaian tersimpan per baris kb_answer_logs, dan setiap pertanyaan
     * melahirkan baris baru — jadi menanyakan "sudah dinilai?" ke tabel ini apa
     * adanya selalu dijawab "belum". Akibatnya orang yang sudah menilai SOP
     * Reset Password SAP kemarin disodori bintang lagi hari ini untuk artikel
     * yang sama persis. Yang ditanyakan di sini karena itu materinya
     * (source_type + source_id), bukan barisnya.
     */
    public static function starsGivenBy(User $rater, string $sourceType, int $sourceId): ?int
    {
        return static::query()
            ->where('rated_by', $rater->id)
            ->whereHas('answerLog', fn ($log) => $log
                ->where('source_type', $sourceType)
                ->where('source_id', $sourceId))
            ->latest('id')
            ->value('stars');
    }
}
