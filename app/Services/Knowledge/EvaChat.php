<?php

declare(strict_types=1);

namespace App\Services\Knowledge;

use App\Models\Knowledge\AnswerLog;
use App\Models\Knowledge\AnswerRating;
use App\Models\Knowledge\Conversation;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Satu jalur percakapan EVA, dipakai bersama oleh dua permukaan.
 *
 * EVA Preview (layar admin) dan widget di portal (permukaan karyawan)
 * menjalankan hal yang persis sama: buka percakapan, tanya, catat giliran,
 * beri bintang, siapkan draf tiket. Sebelum kelas ini ada, seluruh urutan itu
 * hidup di dalam PreviewController — sehingga permukaan kedua hanya bisa
 * dibuat dengan menyalinnya. Dua salinan berarti perbaikan pada satu jalur
 * diam-diam tidak berlaku di jalur lain, dan bedanya baru terasa saat angka
 * Analytics mulai berselisih.
 *
 * Aturan #4 dijaga di sini: `ticketDraft()` tidak menulis satu baris pun ke
 * tabel tiket. Nomor tiket terbit setelah karyawan mengirim sendiri.
 */
final class EvaChat
{
    /** @var array<string, string|array<int, string>> */
    public const ASK_RULES = [
        'question' => 'required|string|max:500',
        'conversation_id' => ['nullable', 'integer', 'exists:kb_conversations,id'],
    ];

    /** @var array<string, string> */
    public const NOTE_RULES = [
        'answer_log_id' => 'required|integer|exists:kb_answer_logs,id',
        'reason' => 'nullable|string|max:255',
        'comment' => 'nullable|string|max:2000',
    ];

    /** @var array<string, string> */
    public const TICKET_DRAFT_RULES = [
        'answer_log_id' => 'required|integer|exists:kb_answer_logs,id',
        'question' => 'required|string|max:500',
    ];

    public function __construct(
        private readonly EvaResponder $responder,
        private readonly SubjectSearch $subjects,
    ) {}

    /**
     * Aturan validasi penilaian dibangun, bukan ditulis harfiah — batas bintang
     * hidup di AnswerRating dan tidak boleh diketik ulang di dua controller.
     *
     * @return array<string, string>
     */
    public static function rateRules(): array
    {
        return [
            'answer_log_id' => 'required|integer|exists:kb_answer_logs,id',
            'stars' => 'required|integer|min:'.AnswerRating::MIN_STARS.'|max:'.AnswerRating::MAX_STARS,
            'reason' => 'nullable|string|max:255',
            'comment' => 'nullable|string|max:2000',
        ];
    }

    /**
     * Satu giliran tanya-jawab, berikut pencatatannya.
     *
     * @return array<string, mixed> balasan EvaReply + id percakapannya
     */
    public function ask(string $question, ?int $conversationId, User $asker): array
    {
        $conversation = $this->resolveConversation($conversationId, $asker);
        $reply = $this->responder->jawab($question, $conversation, $asker);

        $this->recordTurns($conversation, $question, $reply);

        return [
            ...$reply->toArray(),
            'conversation_id' => $conversation->id,
        ];
    }

    /**
     * Bintang dari penanya. Sekali nilai per jawaban — ditegakkan unique di
     * database, jadi percobaan kedua ditolak alih-alih diam-diam menimpa nilai
     * pertama.
     *
     * @return bool false bila jawaban ini sudah pernah dinilai orang yang sama
     */
    public function rate(int $answerLogId, int $stars, ?string $reason, ?string $comment, User $rater): bool
    {
        try {
            AnswerRating::create([
                'answer_log_id' => $answerLogId,
                'stars' => $stars,
                'reason' => $reason,
                'comment' => $comment,
                'rated_by' => $rater->id,
            ]);
        } catch (UniqueConstraintViolationException) {
            // Sengaja memakai exception Laravel, bukan memeriksa kode error
            // driver: kode 1062 hanya dikenal MySQL, sehingga di driver lain
            // penilaian kedua akan meledak jadi 500 alih-alih 409 — dan itu
            // baru ketahuan jauh setelah pindah driver.
            return false;
        }

        return true;
    }

    /**
     * Catatan menyusul, menempel pada baris penilaian yang sudah ada.
     *
     * Kenapa bukan sekadar memanggil `rate()` lagi dengan reason/comment
     * terisi: `unique(answer_log_id, rated_by)` akan menolaknya sebagai
     * penilaian kedua, sehingga catatan karyawan hilang tepat setelah ia
     * menulisnya. Jalur terpisah juga menjaga sifat yang memang diinginkan —
     * BINTANGNYA tetap sekali dan tidak bisa diubah lewat pintu ini; yang
     * ditambahkan hanya keterangannya.
     *
     * @return bool false bila jawaban ini belum pernah dinilai orang tersebut
     */
    public function annotate(int $answerLogId, ?string $reason, ?string $comment, User $rater): bool
    {
        $rating = AnswerRating::where('answer_log_id', $answerLogId)
            ->where('rated_by', $rater->id)
            ->first();

        if ($rating === null) {
            return false;
        }

        $rating->update(['reason' => $reason, 'comment' => $comment]);

        return true;
    }

    /**
     * EVA berhenti di DRAF (aturan #4).
     *
     * @return array<string, mixed>
     */
    public function ticketDraft(int $answerLogId, string $question): array
    {
        $log = AnswerLog::findOrFail($answerLogId);
        $log->update(['outcome' => AnswerLog::OUTCOME_TICKET_DRAFT]);
        $log->conversation?->update(['outcome' => Conversation::OUTCOME_TICKET]);

        // Subject tebakan sengaja TIDAK ditulis ke $log->catalog_subject_id.
        // Kolom itu sudah berarti "subject artikel yang menjawab"; mengisinya
        // dengan tebakan membuat satu kolom berarti dua hal dan diam-diam
        // merusak hitungan di Coverage Dashboard.
        $suggested = $this->subjects->terbaik($question);

        return [
            'draft' => [
                'description' => $question,
                'subject' => $suggested?->toArray(),
                'note' => 'Draf tiket sudah disiapkan. Silakan periksa dan kirim di halaman Buat Tiket — nomor tiket terbit setelah Anda mengirimnya.',
            ],
            'submit_url' => route('dashboard.requester'),
        ];
    }

    /**
     * Percakapan lanjutan dicari DALAM MILIK PENANYA, bukan lewat id telanjang.
     *
     * Selama identitas masih persona tunggal, penyaringan ini memang belum
     * membedakan siapa pun. Yang membuatnya tetap perlu ditulis sekarang:
     * widget portal adalah endpoint publik pertama EVA — begitu SSO memberi
     * identitas sungguhan, `findOrFail($id)` tanpa penyaring berarti siapa pun
     * bisa menyisipkan giliran ke percakapan orang lain hanya dengan menebak
     * angka. Memperbaikinya belakangan berarti mencarinya lagi di dua tempat.
     */
    private function resolveConversation(?int $id, User $asker): Conversation
    {
        if ($id !== null) {
            return Conversation::where('user_id', $asker->id)->findOrFail($id);
        }

        return Conversation::create([
            'user_id' => $asker->id,
            'requester_name' => $asker->name,
            'outcome' => Conversation::OUTCOME_OPEN,
            'started_at' => now(),
        ]);
    }

    private function recordTurns(Conversation $conversation, string $question, EvaReply $reply): void
    {
        $next = $conversation->turns()->max('ordinal');
        $next = $next === null ? 0 : $next + 1;

        $conversation->turns()->create([
            'ordinal' => $next,
            'role' => 'user',
            'message' => $question,
        ]);

        $conversation->turns()->create([
            'ordinal' => $next + 1,
            'role' => 'eva',
            'message' => $reply->text,
            'source_type' => $reply->hit?->sourceType,
            'source_id' => $reply->hit?->sourceId,
            'confidence' => $reply->hit?->confidence,
            'is_clarifying' => $reply->type === EvaReply::TYPE_CLARIFY,
        ]);
    }
}
