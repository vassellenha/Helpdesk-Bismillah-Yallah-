<?php

namespace App\Services\Knowledge;

use App\Models\Knowledge\AnswerLog;
use App\Models\Knowledge\Conversation;
use App\Models\User;

/**
 * Satu giliran percakapan EVA, dari pertanyaan sampai baris kb_answer_logs.
 *
 * Semua jalur — termasuk yang tidak menemukan jawaban — WAJIB melewati kelas
 * ini, karena pencatatan log-nya di sini. Pertanyaan tak terjawab yang tidak
 * tercatat berarti Unanswered Questions dan Coverage Dashboard bohong.
 */
final class EvaResponder
{
    private const NO_ANSWER_TEXT = 'Maaf, saya belum menemukan jawaban yang sesuai di Knowledge Base. Saya bisa siapkan draf tiketnya agar Anda tinggal memeriksa dan mengirim.';

    private const CLARIFY_TEXT = 'Supaya saya tidak salah memberi panduan, layanan mana yang sedang bermasalah?';

    public function __construct(
        private readonly KnowledgeSearch $search,
        private readonly VagueQuestionDetector $vagueDetector,
        private readonly SubjectSearch $subjects,
    ) {}

    public function jawab(string $question, ?Conversation $conversation = null, ?User $asker = null): EvaReply
    {
        if ($this->vagueDetector->isVague($question)) {
            return $this->clarify($question, $conversation, $asker);
        }

        $hits = $this->search->cari($question);
        $best = $hits[0] ?? null;

        if ($best === null || $best->confidence < KnowledgeSearch::MIN_CONFIDENCE) {
            // Sebelum menyerah: apakah pertanyaannya jelas menunjuk satu masalah
            // tapi ambigu antar layanan (reset password → SAP atau SILO)? Kalau
            // ya, bertanya balik lebih berguna daripada langsung menawarkan draf.
            $tied = $this->subjects->calonSeri($question);

            if ($tied !== []) {
                return $this->clarifySubject($question, $tied, $conversation, $asker);
            }

            return $this->noAnswer($question, $conversation, $asker);
        }

        return $this->answer($best, $question, $conversation, $asker);
    }

    private function answer(SearchHit $hit, string $question, ?Conversation $conversation, ?User $asker): EvaReply
    {
        $log = $this->log($question, AnswerLog::OUTCOME_ANSWERED, $conversation, $asker, [
            'source_type' => $hit->sourceType,
            'source_id' => $hit->sourceId,
            'catalog_subject_id' => $hit->catalogSubjectId,
            'confidence' => $hit->confidence,
        ]);

        return new EvaReply(
            type: EvaReply::TYPE_ANSWER,
            text: $hit->answer,
            hit: $hit,
            answerLogId: $log->id,
            isHedged: $hit->confidence < KnowledgeSearch::HEDGE_CONFIDENCE,
        );
    }

    private function clarify(string $question, ?Conversation $conversation, ?User $asker): EvaReply
    {
        $log = $this->log($question, AnswerLog::OUTCOME_CLARIFY, $conversation, $asker);

        return new EvaReply(
            type: EvaReply::TYPE_CLARIFY,
            text: self::CLARIFY_TEXT,
            hit: null,
            answerLogId: $log->id,
            clarifyOptions: $this->vagueDetector->clarifyOptions(),
        );
    }

    /**
     * Bertanya balik saat dua subject seri sama kuat.
     *
     * Bedanya dengan clarify() biasa: itu untuk keluhan generik tanpa nama
     * layanan ("tidak bisa login" → tawarkan semua layanan). Ini untuk
     * pertanyaan yang JELAS soalnya tapi ambigu cabangnya ("reset password" →
     * SAP atau SILO). Pilihannya bukan daftar layanan umum, melainkan pembeda
     * nyata antar calon seri.
     */
    private function clarifySubject(string $question, array $tied, ?Conversation $conversation, ?User $asker): EvaReply
    {
        $log = $this->log($question, AnswerLog::OUTCOME_CLARIFY, $conversation, $asker);

        return new EvaReply(
            type: EvaReply::TYPE_CLARIFY,
            text: 'Ini soal "'.$tied[0]->subject.'" — untuk layanan yang mana?',
            hit: null,
            answerLogId: $log->id,
            clarifyOptions: $this->differentiators($tied),
        );
    }

    /**
     * Kata pembeda antar calon seri, untuk jadi tombol pilihan.
     *
     * Yang dikembalikan harus kata yang — bila ditambahkan ke pertanyaan asal
     * lalu ditanya ulang — benar-benar memecah serinya. Kalau layanan semua
     * calon sama, pembedanya ada di sub category (kasus SAP vs SILO di bawah
     * layanan AKUN APLIKASI yang sama); sebaliknya pembedanya layanan.
     *
     * @param  SubjectMatch[]  $tied
     * @return string[]
     */
    private function differentiators(array $tied): array
    {
        $services = array_unique(array_map(fn (SubjectMatch $m) => $m->service, $tied));

        $labels = count($services) === 1
            ? array_map(fn (SubjectMatch $m) => $m->subcategory, $tied)
            : array_map(fn (SubjectMatch $m) => $m->service, $tied);

        return array_values(array_unique($labels));
    }

    private function noAnswer(string $question, ?Conversation $conversation, ?User $asker): EvaReply
    {
        $log = $this->log($question, AnswerLog::OUTCOME_NO_ANSWER, $conversation, $asker);

        return new EvaReply(
            type: EvaReply::TYPE_NO_ANSWER,
            text: self::NO_ANSWER_TEXT,
            hit: null,
            answerLogId: $log->id,
        );
    }

    private function log(string $question, string $outcome, ?Conversation $conversation, ?User $asker, array $extra = []): AnswerLog
    {
        $log = AnswerLog::create([
            'conversation_id' => $conversation?->id,
            'question' => mb_substr(trim($question), 0, 500),
            'outcome' => $outcome,
            'asked_by' => $asker?->id,
            'confidence' => 0,
            ...$extra,
        ]);

        $this->stampConversation($conversation, $outcome);

        return $log;
    }

    /**
     * Hasil percakapan ikut diperbarui di sini, bukan di pemanggil.
     *
     * Sebelumnya hanya seeder yang melakukannya, sementara EVA Preview tidak —
     * akibatnya setiap percakapan sungguhan tetap berstatus "Berjalan" selamanya
     * di Log Percakapan, walau EVA jelas sudah menjawab. Menaruh keputusan ini
     * di satu tempat menutup celah itu untuk semua pemanggil sekaligus.
     */
    private function stampConversation(?Conversation $conversation, string $outcome): void
    {
        if ($conversation === null) {
            return;
        }

        $conversation->update([
            'outcome' => match ($outcome) {
                AnswerLog::OUTCOME_ANSWERED => Conversation::OUTCOME_ANSWERED,
                AnswerLog::OUTCOME_NO_ANSWER, AnswerLog::OUTCOME_TICKET_DRAFT => Conversation::OUTCOME_TICKET,
                // Bertanya balik bukan akhir percakapan — hasilnya baru
                // ditentukan giliran berikutnya.
                default => $conversation->outcome,
            },
        ]);
    }
}
