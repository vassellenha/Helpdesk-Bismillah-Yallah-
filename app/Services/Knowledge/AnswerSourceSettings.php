<?php

namespace App\Services\Knowledge;

use App\Models\Knowledge\KbSetting;
use Illuminate\Support\Facades\Cache;

/**
 * Sakelar sumber jawaban EVA: artikel dan FAQ.
 *
 * INI BUKAN HIASAN. FulltextKnowledgeSearch membaca kelas ini di setiap
 * pencarian; mematikan sebuah sumber di sini benar-benar mengeluarkannya dari
 * hasil EVA. Toggle yang tersimpan rapi tapi tidak mengubah jawaban lebih buruk
 * daripada tidak ada — admin akan mematikannya lalu heran kenapa FAQ masih
 * muncul.
 *
 * Sumber yang bisa dimatikan hanya artikel dan FAQ, karena hanya itu yang
 * dibaca EVA (aturan #3 — EVA tidak membaca tiket). Dokumen bukan sumber
 * jawaban langsung: ia hulu yang melahirkan artikel, jadi mematikannya di sini
 * tidak punya arti.
 */
final class AnswerSourceSettings
{
    public const SOURCE_ARTICLES = 'articles';

    public const SOURCE_FAQS = 'faqs';

    /** Sumber yang bisa disetel, beserta nilai bawaannya (semua menyala). */
    private const SOURCES = [
        self::SOURCE_ARTICLES => true,
        self::SOURCE_FAQS => true,
    ];

    private const KEY_PREFIX = 'answer_source.';

    private const CACHE_KEY = 'eva.answer-source-settings';

    private const CACHE_TTL_SECONDS = 300;

    public function articlesEnabled(): bool
    {
        return $this->enabled(self::SOURCE_ARTICLES);
    }

    public function faqsEnabled(): bool
    {
        return $this->enabled(self::SOURCE_FAQS);
    }

    public function enabled(string $source): bool
    {
        return $this->map()[$source] ?? true;
    }

    /** @return array<string,bool> */
    public function all(): array
    {
        return $this->map();
    }

    /**
     * Ubah satu sumber. Menolak kunci di luar daftar supaya tabel pengaturan
     * tidak terisi kunci liar yang tak pernah dibaca siapa pun.
     */
    public function set(string $source, bool $enabled): void
    {
        if (! array_key_exists($source, self::SOURCES)) {
            throw new \InvalidArgumentException("Sumber jawaban tidak dikenal: {$source}");
        }

        KbSetting::updateOrCreate(
            ['key' => self::KEY_PREFIX.$source],
            ['value' => $enabled ? '1' : '0'],
        );

        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Peta sumber → nyala/mati. Nilai yang belum pernah disimpan memakai bawaan
     * SOURCES, jadi sistem yang baru dipasang berperilaku seolah semua menyala.
     *
     * @return array<string,bool>
     */
    private function map(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL_SECONDS, function () {
            $stored = KbSetting::query()
                ->where('key', 'like', self::KEY_PREFIX.'%')
                ->pluck('value', 'key');

            $map = [];

            foreach (self::SOURCES as $source => $default) {
                $key = self::KEY_PREFIX.$source;
                $map[$source] = $stored->has($key) ? $stored[$key] === '1' : $default;
            }

            return $map;
        });
    }
}
