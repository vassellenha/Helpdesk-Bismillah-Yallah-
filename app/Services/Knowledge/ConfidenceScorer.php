<?php

namespace App\Services\Knowledge;

/**
 * Menilai seberapa yakin sebuah kandidat menjawab pertanyaan.
 *
 * Ini BUKAN rumus mockup (bobot kata kunci manual). Di sini keyakinan
 * dihitung dari porsi kata bermakna pertanyaan yang benar-benar muncul di
 * kandidat, ditimbang panjang kata — kata panjang lebih membedakan daripada
 * kata pendek.
 *
 * Yang penting dipertahankan dari mockup hanyalah sifatnya: hasilnya angka
 * keyakinan, dan di bawah ambang EVA tidak menebak.
 *
 * Saat pindah ke pgvector, kelas ini diganti skor kemiripan vektor. Skala
 * 0–100 dan ambangnya tetap, sehingga sisa aplikasi tidak berubah.
 */
final class ConfidenceScorer
{
    /**
     * Pembagian bobot judul vs isi.
     *
     * Judul diberi porsi besar dengan sengaja. Dengan bobot isi yang dominan,
     * artikel "SOP Unlock Akun SAP" — yang hanya MENYEBUT "SOP Reset Password
     * SAP" sebagai rujukan silang — mengalahkan artikel yang benar-benar
     * berjudul itu. Judul adalah pernyataan tentang apa isinya; sebutan di
     * badan teks belum tentu.
     */
    private const BODY_WEIGHT = 60;

    private const TITLE_WEIGHT = 40;

    /** Batas atas — EVA tidak pernah mengaku 100% yakin. */
    private const MAX_CONFIDENCE = 97;

    /**
     * Pertanyaan satu kata terlalu sedikit bukti untuk keyakinan penuh.
     * Peredam ini yang mencegah "sap" saja langsung dijawab mantap.
     */
    private const SHORT_QUERY_DAMPING = 0.75;

    public function __construct(
        private readonly QuestionTokenizer $tokenizer,
        private readonly SynonymExpander $synonyms,
    ) {}

    /**
     * @param  string[]  $questionTokens  hasil QuestionTokenizer::significant()
     */
    public function score(array $questionTokens, string $title, string $body): int
    {
        if ($questionTokens === []) {
            return 0;
        }

        $titleTokens = $this->tokenizer->significant($title);
        $bodyTokens = array_merge($titleTokens, $this->tokenizer->significant($body));

        $totalWeight = 0;
        $bodyMatchedWeight = 0;
        $titleMatchedWeight = 0;

        foreach ($questionTokens as $token) {
            // Kata panjang lebih membedakan daripada kata pendek: "forticlient"
            // menunjuk satu hal, "sap" muncul di mana-mana.
            $weight = mb_strlen($token);
            $totalWeight += $weight;

            // Kecocokan lewat sinonim dihitung PENUH, tidak didiskon.
            // "sandi" yang bertemu "password" bukan kecocokan setengah — itu
            // kata yang sama dalam kosakata yang berbeda. Mendiskonnya hanya
            // memindahkan kegagalan dari "tidak ketemu" ke "ketemu tapi di
            // bawah ambang", yang justru lebih membingungkan.
            $variants = $this->synonyms->variants($token);

            if (array_intersect($variants, $bodyTokens) !== []) {
                $bodyMatchedWeight += $weight;
            }

            if (array_intersect($variants, $titleTokens) !== []) {
                $titleMatchedWeight += $weight;
            }
        }

        if ($bodyMatchedWeight === 0) {
            return 0;
        }

        $confidence = ($bodyMatchedWeight / $totalWeight) * self::BODY_WEIGHT
            + ($titleMatchedWeight / $totalWeight) * self::TITLE_WEIGHT;

        if (count($questionTokens) === 1) {
            $confidence *= self::SHORT_QUERY_DAMPING;
        }

        return (int) min(self::MAX_CONFIDENCE, round($confidence));
    }
}
