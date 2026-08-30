<?php

namespace App\Services\Knowledge;

/**
 * LAYANAN yang jelas dimaksud penanya, saat tidak ada satu pun SUBJECT yang
 * meyakinkan.
 *
 * Ini keadaan ketiga yang dulu tidak punya nama. Sebelumnya draf tiket hanya
 * mengenal dua: "tahu subject-nya" atau "tidak tahu apa-apa". Padahal di antara
 * keduanya ada keadaan yang paling sering terjadi dan paling mudah ditolong —
 * EVA tahu persis APLIKASI mana yang dikeluhkan, karena penanya mengetik
 * namanya, tetapi tidak tahu masalahnya yang mana dari sekian subject.
 *
 * Menyerahkan sebanyak itu saja sudah cukup: form Buat Tiket punya sub category
 * "Lainnya" yang menerima Layanan tanpa Subject, dan TicketBroadcast melempar
 * tiket semacam itu ke seluruh PIC Layanan tersebut. Tim yang benar tetap
 * menerimanya; yang diserahkan ke penanya hanya bagian yang memang cuma ia
 * yang tahu.
 *
 * Sengaja BUKAN SubjectMatch dengan subject kosong: dua hal yang dipakai
 * berbeda oleh form tiket tidak boleh memakai satu bentuk data yang sama,
 * karena kolom kosong akan diperlakukan sebagai "belum diisi" dan diam-diam
 * ditebak ulang di hilir.
 */
final class ServiceMatch
{
    public function __construct(
        public readonly int $serviceId,
        public readonly string $service,
        /** @var string[] nama Issue Category yang dipakai subject-subject layanan ini */
        public readonly array $issueCategories = [],
        /** @var string[] kata pertanyaan yang menyebut layanan ini */
        public readonly array $matchedTerms = [],
    ) {}

    /**
     * Issue Category yang boleh diisikan otomatis ke form — hanya bila layanan
     * ini memang cuma punya satu.
     *
     * Jalur "Lainnya" tidak punya Subject untuk menurunkan Issue Category-nya,
     * jadi kolom itu wajib diisi penanya. Kalau seluruh subject layanan ini
     * berada di bawah satu Issue Category yang sama, menebaknya bukan tebakan
     * melainkan satu-satunya kemungkinan. Kalau lebih dari satu, biarkan
     * penanya memilih.
     */
    public function soleIssueCategory(): ?string
    {
        return count($this->issueCategories) === 1 ? $this->issueCategories[0] : null;
    }

    public function toArray(): array
    {
        return [
            'service_id' => $this->serviceId,
            'service' => $this->service,
            'issue_categories' => $this->issueCategories,
            'issue_category' => $this->soleIssueCategory(),
            'matched_terms' => $this->matchedTerms,
        ];
    }
}
