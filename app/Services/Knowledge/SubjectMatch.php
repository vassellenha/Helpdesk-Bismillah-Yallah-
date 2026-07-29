<?php

namespace App\Services\Knowledge;

/**
 * Satu calon subject katalog untuk sebuah pertanyaan.
 *
 * Sengaja membawa SELURUH jalur (Issue Category → Layanan → Sub Category →
 * Subject), bukan hanya id dan nama. Nama subject sering tidak berarti apa-apa
 * tanpa jalurnya — ada "Reset Password" di bawah AKUN APLIKASI › SAP dan
 * "Reset Password" lain di bawah AKUN APLIKASI › SILO (OTHER APPS), dan
 * memilih yang salah berarti tiket mendarat di tim yang salah.
 */
final class SubjectMatch
{
    public function __construct(
        public readonly int $subjectId,
        public readonly string $subject,
        public readonly string $service,
        public readonly string $subcategory,
        public readonly string $issueCategory,
        public readonly int $confidence,
        public readonly bool $requiresApproval,
        public readonly ?int $supportLevel,
        /** @var string[] kata pertanyaan yang membuat subject ini terpilih */
        public readonly array $matchedTerms = [],
    ) {}

    public function path(): string
    {
        return $this->issueCategory.' › '.$this->service.' › '.$this->subcategory;
    }

    public function toArray(): array
    {
        return [
            'subject_id' => $this->subjectId,
            'subject' => $this->subject,
            'service' => $this->service,
            'subcategory' => $this->subcategory,
            'issue_category' => $this->issueCategory,
            'path' => $this->path(),
            'confidence' => $this->confidence,
            'requires_approval' => $this->requiresApproval,
            'support_level' => $this->supportLevel,
            'matched_terms' => $this->matchedTerms,
        ];
    }
}
