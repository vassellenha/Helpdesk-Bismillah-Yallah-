<?php

declare(strict_types=1);

namespace App\Services\Knowledge;

/**
 * Tidak merangkum apa pun — dan itu perilaku bawaan EVA.
 *
 * Dipakai selama fitur rangkuman dimatikan (atau kuncinya kosong) dan di
 * seluruh tes. Mengembalikan null berarti EVA menjalankan perilaku aslinya:
 * menjawab dari satu sumber paling meyakinkan, atau menawarkan draf tiket.
 */
final class NoSynthesizer implements KnowledgeSynthesizer
{
    public function rangkum(string $question, array $passages): ?Synthesis
    {
        return null;
    }
}
