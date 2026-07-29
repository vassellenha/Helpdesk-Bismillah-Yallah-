<?php

namespace App\Services\Knowledge;

use App\Models\Knowledge\Article;
use App\Models\Knowledge\Faq;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Implementasi Pencarian A di atas FULLTEXT MySQL/MariaDB.
 *
 * Pembagian kerja yang disengaja:
 *   - Database melakukan RECALL (menyaring kandidat) lewat MATCH…AGAINST
 *     natural language mode, dijalankan Query Builder — tanpa SQL mentah,
 *     supaya tetap jalan di MariaDB 10.4 yang tidak punya QUERY EXPANSION.
 *   - PHP melakukan RANKING lewat ConfidenceScorer.
 *
 * Pemisahan itu bukan gaya-gayaan: skor relevansi FULLTEXT tidak punya skala
 * yang bisa dibandingkan antar tabel, sedangkan ambang 55 harus berarti hal
 * yang sama untuk artikel maupun FAQ.
 */
final class FulltextKnowledgeSearch implements KnowledgeSearch
{
    /** Jumlah kandidat yang ditarik per sumber sebelum diberi skor. */
    private const CANDIDATE_LIMIT = 25;

    /** Driver yang punya FULLTEXT — di luar ini kelas ini tidak bisa bekerja. */
    private const FULLTEXT_DRIVERS = ['mysql', 'mariadb'];

    public function __construct(
        private readonly QuestionTokenizer $tokenizer,
        private readonly ConfidenceScorer $scorer,
        private readonly SynonymExpander $synonyms,
        private readonly AnswerSourceSettings $sources,
    ) {}

    public function cari(string $pertanyaan, int $limit = 5): array
    {
        $tokens = $this->tokenizer->significant($pertanyaan);

        if ($tokens === []) {
            return [];
        }

        // Sumber dibaca di sini, bukan di konstruktor: admin bisa mematikan FAQ
        // dari layar Training Overview dan efeknya harus terasa di pencarian
        // BERIKUTNYA, bukan setelah proses di-restart. Inilah yang membuat
        // toggle-nya nyata, bukan hiasan.
        $hits = [];

        if ($this->sources->articlesEnabled()) {
            $hits = [...$hits, ...$this->scoreArticles($this->articleCandidates($tokens), $tokens)];
        }

        if ($this->sources->faqsEnabled()) {
            $hits = [...$hits, ...$this->scoreFaqs($this->faqCandidates($tokens), $tokens)];
        }

        usort($hits, fn (SearchHit $a, SearchHit $b) => $b->confidence <=> $a->confidence);

        return array_slice(
            array_values(array_filter($hits, fn (SearchHit $hit) => $hit->confidence > 0)),
            0,
            $limit,
        );
    }

    /**
     * `whereFullText()` tidak ada di luar MySQL/MariaDB, dan kegagalannya
     * muncul sebagai SQL error mentah 500 di tengah pertanyaan pengguna —
     * bukan sebagai "mesin pencarinya salah pasang". Server baru dengan
     * DB_CONNECTION yang salah setel adalah cara paling mudah tiba di sini.
     *
     * Dilempar sebagai RuntimeException dengan kalimat yang menunjuk ke
     * penyebabnya, supaya yang membaca log tahu ini soal konfigurasi.
     */
    private function pastikanDriverMendukungFulltext(Builder $query): void
    {
        $driver = $query->getConnection()->getDriverName();

        if (! in_array($driver, self::FULLTEXT_DRIVERS, true)) {
            throw new RuntimeException(sprintf(
                'Pencarian EVA butuh MySQL/MariaDB (FULLTEXT), tapi koneksi database memakai driver "%s". '
                .'Periksa DB_CONNECTION, atau pasang implementasi KnowledgeSearch lain di AppServiceProvider.',
                $driver,
            ));
        }
    }

    private function articleCandidates(array $tokens): Collection
    {
        return $this->candidates(
            Article::query()->answerable(),
            ['title', 'summary', 'body'],
            ['title', 'summary'],
            $tokens,
        );
    }

    private function faqCandidates(array $tokens): Collection
    {
        return $this->candidates(
            Faq::query()->answerable(),
            ['question', 'answer'],
            ['question', 'answer'],
            $tokens,
        );
    }

    /**
     * FULLTEXT dulu; kalau tidak mengembalikan apa pun, jatuh ke LIKE.
     *
     * Fallback ini perlu karena InnoDB mengabaikan token di bawah
     * innodb_ft_min_token_size (default 3) — pertanyaan pendek seperti
     * "vpn mati" bisa mengembalikan nol baris padahal jawabannya ada.
     * Tanpa fallback, kegagalan itu terlihat seperti "KB memang belum punya
     * jawaban", yaitu kesalahan diam yang paling mahal di sistem ini.
     *
     * @param  string[]  $fulltextColumns  kolom yang punya indeks FULLTEXT
     * @param  string[]  $likeColumns  kolom yang dipindai fallback
     */
    private function candidates(Builder $query, array $fulltextColumns, array $likeColumns, array $tokens): Collection
    {
        // Diperiksa di sini, bukan di cari(): ini titik tempat FULLTEXT
        // benar-benar dipakai. Pencarian yang berakhir tanpa query sama sekali
        // (mis. semua sumber jawaban dimatikan admin) tidak perlu gagal.
        $this->pastikanDriverMendukungFulltext($query);

        // Sinonim diperluas di tahap RECALL juga, bukan hanya saat memberi skor.
        // Kalau hanya penilai yang tahu "sandi" = "password", artikel yang cuma
        // menulis "password" tidak akan pernah masuk daftar kandidat — dan
        // penilai tidak bisa memberi skor pada sesuatu yang tidak pernah dilihat.
        $tokens = $this->synonyms->expandAll($tokens);

        $found = (clone $query)
            ->whereFullText($fulltextColumns, implode(' ', $tokens))
            ->orderBy('id')
            ->limit(self::CANDIDATE_LIMIT)
            ->get();

        if ($found->isNotEmpty()) {
            return $found;
        }

        return $query
            ->where(function (Builder $inner) use ($likeColumns, $tokens) {
                foreach ($tokens as $token) {
                    foreach ($likeColumns as $column) {
                        $inner->orWhere($column, 'like', '%'.$token.'%');
                    }
                }
            })
            ->orderBy('id')
            ->limit(self::CANDIDATE_LIMIT)
            ->get();
    }

    /** @return SearchHit[] */
    private function scoreArticles(Collection $articles, array $tokens): array
    {
        return $articles->map(fn (Article $article) => new SearchHit(
            sourceType: Article::class,
            sourceId: $article->id,
            title: $article->title,
            answer: (string) ($article->summary ?: $article->body),
            confidence: $this->scorer->score(
                $tokens,
                $article->title,
                ScoredText::forArticle($article),
            ),
            catalogSubjectId: $article->catalog_subject_id,
        ))->all();
    }

    /** @return SearchHit[] */
    private function scoreFaqs(Collection $faqs, array $tokens): array
    {
        return $faqs->map(fn (Faq $faq) => new SearchHit(
            sourceType: Faq::class,
            sourceId: $faq->id,
            title: $faq->question,
            answer: $faq->answer,
            confidence: $this->scorer->score(
                $tokens,
                $faq->question,
                ScoredText::forFaq($faq),
            ),
            catalogSubjectId: $faq->catalog_subject_id,
        ))->all();
    }
}
