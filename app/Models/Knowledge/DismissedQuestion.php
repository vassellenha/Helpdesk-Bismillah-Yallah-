<?php

namespace App\Models\Knowledge;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Keputusan admin untuk menyingkirkan sebuah pertanyaan dari daftar kerja.
 *
 * Bukan penghapusan: `kb_answer_logs` tidak tersentuh, jadi riwayat dan seluruh
 * angka Analytics tetap utuh. Yang hilang hanya barisnya dari daftar Unanswered
 * Questions — dan itu pun tidak permanen (lihat hiddenQuestions()).
 */
class DismissedQuestion extends Model
{
    protected $table = 'kb_dismissed_questions';

    protected $fillable = ['question', 'dismissed_at', 'dismissed_by'];

    protected $casts = ['dismissed_at' => 'datetime'];

    public function dismissedBy()
    {
        return $this->belongsTo(User::class, 'dismissed_by');
    }

    /**
     * Pertanyaan yang boleh disembunyikan SEKARANG.
     *
     * Sebuah keputusan menyingkirkan hanya berlaku selama pertanyaannya tidak
     * ditanyakan lagi sesudahnya. Begitu ada yang menanyakannya kembali,
     * barisnya muncul lagi — orang masih membutuhkannya, dan daftar kerja yang
     * membungkam bukti baru akan diam-diam berhenti berguna.
     *
     * @return Collection<int,string>
     */
    public static function hiddenQuestions(): Collection
    {
        $dismissals = self::query()->pluck('dismissed_at', 'question');

        if ($dismissals->isEmpty()) {
            return collect();
        }

        $lastAsked = AnswerLog::query()->unanswered()
            ->whereIn('question', $dismissals->keys())
            ->groupBy('question')
            ->pluck(DB::raw('max(created_at) as last_asked'), 'question');

        return $dismissals
            ->filter(fn ($dismissedAt, $question) => $lastAsked->has($question)
                && $dismissedAt->gte($lastAsked->get($question)))
            ->keys();
    }
}
