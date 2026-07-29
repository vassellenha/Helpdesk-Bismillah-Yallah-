<?php

namespace App\Services\Knowledge;

/**
 * Satu kandidat jawaban beserta tingkat keyakinannya.
 *
 * Immutable dengan sengaja: hasil pencarian melintasi batas service → controller
 * → komponen, dan tidak boleh ada satu pun lapisan yang mengubahnya diam-diam.
 */
final class SearchHit
{
    public function __construct(
        /** Article::class atau Faq::class — dipakai apa adanya untuk kb_answer_logs.source_type. */
        public readonly string $sourceType,
        public readonly int $sourceId,
        public readonly string $title,
        public readonly string $answer,
        /** 0–100. Di bawah KnowledgeSearch::MIN_CONFIDENCE, EVA tidak menjawab. */
        public readonly int $confidence,
        public readonly ?int $catalogSubjectId,
    ) {}

    public function toArray(): array
    {
        return [
            'source_type' => $this->sourceType,
            'source_id' => $this->sourceId,
            'title' => $this->title,
            'answer' => $this->answer,
            'confidence' => $this->confidence,
            'catalog_subject_id' => $this->catalogSubjectId,
        ];
    }
}
