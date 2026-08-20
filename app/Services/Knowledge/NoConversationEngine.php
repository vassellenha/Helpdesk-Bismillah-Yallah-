<?php

declare(strict_types=1);

namespace App\Services\Knowledge;

/**
 * Perilaku EVA sebelum lapisan percakapan ada — dan tempat ia kembali ketika
 * layanan model dimatikan atau tidak tersedia.
 *
 * Sengaja tidak melakukan apa pun: pertanyaan dicari apa adanya, basa-basi
 * dibalas kalimat tetap. Yang hilang hanyalah kelenturan bahasanya; EVA tetap
 * menemukan dan menjawab dari Knowledge Base seperti biasa.
 *
 * Itu keputusan yang disengaja. Kemampuan mengobrol adalah lapisan tambahan di
 * atas swalayan pengetahuan, bukan syarat hidupnya — satu gangguan di pihak
 * ketiga tidak boleh membuat karyawan kehilangan akses ke SOP perusahaannya
 * sendiri.
 */
final class NoConversationEngine implements ConversationEngine
{
    public function standalone(string $question, array $memory): string
    {
        return $question;
    }

    public function chat(string $question, array $memory, string $fallback): string
    {
        return $fallback;
    }

    public function converse(string $question, array $memory): ?string
    {
        return null;
    }

    /**
     * Selalu true — tanpa mesin percakapan, penilaian relevansi sepenuhnya
     * diserahkan pada ambang keyakinan pencarian, persis seperti sebelumnya.
     */
    public function materialAnswers(string $question, string $title, string $material): bool
    {
        return true;
    }
}
