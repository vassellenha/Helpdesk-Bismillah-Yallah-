<?php

namespace App\Services\Knowledge;

/**
 * Pemecah pertanyaan menjadi kata bermakna.
 *
 * Dipakai bersama oleh penilai keyakinan dan pendeteksi pertanyaan ambigu,
 * supaya keduanya tidak pernah punya pendapat berbeda soal "kata apa yang
 * dianggap penting".
 */
final class QuestionTokenizer
{
    /** Kata umum bahasa Indonesia yang tidak membedakan satu masalah dari lainnya. */
    private const STOPWORDS = [
        'tidak', 'bisa', 'yang', 'dari', 'pada', 'untuk', 'dan', 'atau', 'ini', 'itu',
        'saya', 'apa', 'gimana', 'bagaimana', 'kenapa', 'mohon', 'tolong', 'bantu',
        'sudah', 'belum', 'dengan', 'akan', 'agar', 'supaya', 'saat', 'ketika',
        'ada', 'jadi', 'lagi', 'juga', 'sangat', 'terus', 'kalau', 'kok', 'nya',
        'cara', 'adalah', 'oleh', 'akan', 'bila', 'jika', 'setelah', 'sebelum',
    ];

    /**
     * Akhiran dan awalan yang dilucuti agar "membukanya" dan "dibuka" dianggap
     * kata yang sama.
     *
     * Ini stemmer ringan, sengaja bukan Nazief-Adriani lengkap. Yang dibutuhkan
     * pencarian hanyalah KONSISTENSI: pertanyaan dan isi artikel dilucuti
     * dengan aturan yang sama persis, jadi over-stemming sesekali tidak membuat
     * keduanya gagal bertemu. Tanpa ini, dokumen yang menulis "akun dibuka"
     * tidak pernah cocok dengan pertanyaan "cara membukanya".
     */
    private const SUFFIXES = ['nya', 'kah', 'lah', 'kan', 'an', 'i'];

    private const PREFIXES = ['meng', 'meny', 'mem', 'men', 'peng', 'peny', 'pem', 'pen', 'ter', 'ber', 'me', 'di', 'pe', 'se', 'ke'];

    private const MIN_TOKEN_LENGTH = 3;

    /** Panjang minimal hasil pelucutan; di bawah ini kata asli dipertahankan. */
    private const MIN_STEM_LENGTH = 3;

    /** @return string[] */
    public function tokens(?string $text): array
    {
        preg_match_all('/[\p{L}\p{N}]+/u', mb_strtolower((string) $text), $matches);

        return $matches[0] ?? [];
    }

    /**
     * Kata bermakna dalam bentuk dasarnya, tanpa duplikat.
     *
     * @return string[]
     */
    public function significant(?string $text): array
    {
        $stems = [];

        foreach ($this->tokens($text) as $token) {
            if (mb_strlen($token) < self::MIN_TOKEN_LENGTH || in_array($token, self::STOPWORDS, true)) {
                continue;
            }

            $stems[] = $this->stem($token);
        }

        return array_values(array_unique($stems));
    }

    public function stem(string $token): string
    {
        return $this->stripPrefix($this->stripSuffix($token));
    }

    private function stripSuffix(string $token): string
    {
        foreach (self::SUFFIXES as $suffix) {
            if (! str_ends_with($token, $suffix)) {
                continue;
            }

            $stripped = mb_substr($token, 0, mb_strlen($token) - mb_strlen($suffix));

            if (mb_strlen($stripped) >= self::MIN_STEM_LENGTH) {
                // Sekali saja: "membukanya" → "membuka", tidak diteruskan
                // sampai habis menjadi potongan yang tak berarti.
                return $stripped;
            }
        }

        return $token;
    }

    private function stripPrefix(string $token): string
    {
        foreach (self::PREFIXES as $prefix) {
            if (! str_starts_with($token, $prefix)) {
                continue;
            }

            $stripped = mb_substr($token, mb_strlen($prefix));

            if (mb_strlen($stripped) >= self::MIN_STEM_LENGTH) {
                return $stripped;
            }
        }

        return $token;
    }
}
