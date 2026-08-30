<?php

namespace App\Services\Knowledge;

/** Hasil satu giliran EVA menjawab, siap dikirim ke komponen. */
final class EvaReply
{
    public const TYPE_ANSWER = 'answer';

    public const TYPE_CLARIFY = 'clarify';

    public const TYPE_NO_ANSWER = 'no_answer';

    /** Sapaan dan basa-basi — dijawab tanpa menyentuh Knowledge Base. */
    public const TYPE_SMALL_TALK = 'small_talk';

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
        /** Bintang yang pernah diberikan penanya untuk MATERI ini; null = belum pernah. */
        public readonly ?int $previousStars = null,
        /**
         * SELURUH materi yang isinya benar-benar dipakai menyusun jawaban ini.
         *
         * Berbeda dari `hit`, yang tetap satu kandidat TERATAS karena itulah
         * yang dicatat kb_answer_logs dan dihitung Coverage. Jawaban rangkuman
         * dijahit dari beberapa dokumen, dan menampilkan satu saja membuat
         * karyawan yang mengeklik rujukan tidak menemukan fakta yang ia cek.
         *
         * @var SearchHit[]
         */
        public readonly array $sources = [],
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
            'previous_stars' => $this->previousStars,
            'sources' => array_map(fn (SearchHit $h) => $h->toArray(), $this->sources),
        ];
    }
}
