<?php

namespace App\Services\Knowledge;

/** Hasil satu giliran EVA menjawab, siap dikirim ke komponen. */
final class EvaReply
{
    public const TYPE_ANSWER = 'answer';

    public const TYPE_CLARIFY = 'clarify';

    public const TYPE_NO_ANSWER = 'no_answer';

    public function __construct(
        public readonly string $type,
        public readonly string $text,
        public readonly ?SearchHit $hit,
        /** id baris kb_answer_logs — dipakai komponen untuk mengirim bintang. */
        public readonly int $answerLogId,
        /** Keyakinan lewat ambang tapi masih di bawah HEDGE_CONFIDENCE. */
        public readonly bool $isHedged = false,
        /** Pilihan layanan yang ditawarkan saat EVA bertanya balik. */
        public readonly array $clarifyOptions = [],
    ) {}

    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'text' => $this->text,
            'hit' => $this->hit?->toArray(),
            'answer_log_id' => $this->answerLogId,
            'is_hedged' => $this->isHedged,
            'clarify_options' => $this->clarifyOptions,
        ];
    }
}
