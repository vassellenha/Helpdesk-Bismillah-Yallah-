<?php

declare(strict_types=1);

namespace App\Services\Knowledge;

/**
 * Tidak mengubah apa pun — dan itu perilaku bawaan EVA.
 *
 * Dipakai selama parafrase dimatikan (atau kuncinya belum diisi), dan di
 * seluruh tes: tanpa ini setiap tes EvaResponder akan menyentuh jaringan.
 */
final class PassthroughParaphraser implements AnswerParaphraser
{
    public function parafrase(string $answer): string
    {
        return $answer;
    }
}
