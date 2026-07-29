<?php

namespace App\Services\Knowledge;

use App\Models\Knowledge\Synonym;
use Illuminate\Support\Facades\Cache;

/**
 * Memperluas satu kata menjadi seluruh kata yang dianggap setara dengannya.
 *
 * Ini yang membuat "saya lupa SANDI SAP" menemukan artikel berjudul "SOP Reset
 * PASSWORD SAP". Tanpa ini, kegagalan itu terlihat seperti "materinya belum
 * ada" — padahal materinya ada dan lengkap; hanya kosakatanya berbeda.
 *
 * Kata dicocokkan dalam bentuk DASAR (sudah melewati stemmer QuestionTokenizer),
 * bukan bentuk mentahnya. Dengan begitu admin cukup menulis "password, sandi"
 * sekali dan "sandinya", "passwordku" ikut tertangani — bukan harus mendaftar
 * tiap bentuk berimbuhan satu per satu.
 */
final class SynonymExpander
{
    private const CACHE_KEY = 'eva.synonym-map';

    private const CACHE_TTL_SECONDS = 300;

    public function __construct(private readonly QuestionTokenizer $tokenizer) {}

    /**
     * Semua bentuk setara sebuah kata, termasuk kata itu sendiri.
     *
     * @return string[]
     */
    public function variants(string $stem): array
    {
        return $this->map()[$stem] ?? [$stem];
    }

    /**
     * Perluas sekumpulan kata sekaligus — dipakai untuk memperlebar jangkauan
     * pencarian FULLTEXT.
     *
     * @param  string[]  $stems
     * @return string[]
     */
    public function expandAll(array $stems): array
    {
        $expanded = [];

        foreach ($stems as $stem) {
            foreach ($this->variants($stem) as $variant) {
                $expanded[] = $variant;
            }
        }

        return array_values(array_unique($expanded));
    }

    /** Buang cache setelah admin mengubah daftar sinonim. */
    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Peta kata-dasar → seluruh kata setaranya.
     *
     * @return array<string,string[]>
     */
    private function map(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL_SECONDS, function () {
            $map = [];

            foreach (Synonym::query()->active()->orderBy('id')->get() as $synonym) {
                // Frasa seperti "kata sandi" ikut dipecah jadi kata-kata
                // dasarnya, karena pencocokan terjadi per kata.
                $stems = [];

                foreach ($synonym->termList() as $term) {
                    foreach ($this->tokenizer->significant($term) as $stem) {
                        $stems[] = $stem;
                    }
                }

                $stems = array_values(array_unique($stems));

                foreach ($stems as $stem) {
                    $map[$stem] = array_values(array_unique([...($map[$stem] ?? [$stem]), ...$stems]));
                }
            }

            return $map;
        });
    }
}
