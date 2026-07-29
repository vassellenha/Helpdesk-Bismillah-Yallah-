<?php

declare(strict_types=1);

namespace App\Services\Knowledge;

use App\Models\Knowledge\Article;
use App\Models\Knowledge\Faq;

/**
 * Teks mana dari sebuah kandidat yang boleh dinilai ConfidenceScorer.
 *
 * Dipisah jadi satu tempat karena jawabannya adalah keputusan, bukan detail
 * perakitan string: apa pun yang masuk ke sini berhak menaikkan keyakinan EVA.
 *
 * `tags` SENGAJA TIDAK IKUT. Tag adalah label kearsipan yang diisi bebas dari
 * layar Taxonomy — bukan pernyataan bahwa materinya menjawab kata itu. Selama
 * ia ikut dinilai, satu artikel toner printer yang diberi tag "klaim tunjangan
 * kesehatan" bisa melewati ambang 55 dan dijawab EVA dengan percaya diri.
 *
 * Membuangnya tidak mengurangi daya temu sedikit pun: tahap RECALL di
 * FulltextKnowledgeSearch memang tidak pernah memindai kolom `tags`, baik di
 * jalur FULLTEXT maupun fallback LIKE.
 */
final class ScoredText
{
    public static function forArticle(Article $article): string
    {
        return trim($article->summary.' '.$article->body);
    }

    public static function forFaq(Faq $faq): string
    {
        return trim((string) $faq->answer);
    }
}
