<?php

declare(strict_types=1);

namespace Tests\Feature\Eva;

use App\Models\Knowledge\Article;
use App\Services\Knowledge\AnswerReach;
use App\Services\Knowledge\KnowledgeSearch;
use App\Services\Knowledge\SearchHit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsRole;
use Tests\TestCase;

/**
 * Ambang 55 bukan satu-satunya jalan EVA menjawab.
 *
 * EvaResponder memanggil perangkum SEBELUM ambang diperiksa: begitu rangkuman
 * berhasil disusun dari potongan yang lolos SYNTHESIS_FLOOR, jawabannya
 * dikirim berapa pun keyakinan kandidat terbaiknya. Itu disengaja — jawaban
 * yang tersebar di beberapa dokumen tidak pernah membuat satu pun di antaranya
 * terlihat meyakinkan sendirian.
 *
 * Yang tidak disengaja adalah alat ukurnya ikut tidak tahu. Uji langsung pada
 * Search Settings menyatakan "EVA belum akan menjawab" untuk pertanyaan yang
 * nyatanya dijawab EVA, dan subjudul EVA Preview menjanjikan aturan yang tidak
 * dijalankan. Pengelola EVA memakai layar-layar itu untuk menilai cakupan, dan
 * keduanya menuntunnya ke kesimpulan yang salah.
 *
 * Aturannya sekarang tinggal di satu tempat supaya responder dan alat ukurnya
 * tidak bisa berselisih lagi.
 *
 * Ditemukan saat UAT test case 40.
 */
final class AnswerReachTest extends TestCase
{
    use ActsAsRole, RefreshDatabase;

    public function test_kandidat_di_atas_ambang_pasti_dijawab(): void
    {
        $reach = AnswerReach::for([$this->hit(KnowledgeSearch::MIN_CONFIDENCE)]);

        $this->assertSame(AnswerReach::ANSWER, $reach);
    }

    public function test_kandidat_di_bawah_ambang_tapi_di_atas_lantai_rangkuman_masih_mungkin_dijawab(): void
    {
        $reach = AnswerReach::for([$this->hit(32)]);

        $this->assertSame(AnswerReach::MAYBE, $reach, 'inilah pita yang selama ini dilaporkan sebagai "tidak dijawab"');
    }

    public function test_kandidat_di_bawah_lantai_rangkuman_tidak_dijawab(): void
    {
        $this->assertSame(AnswerReach::NONE, AnswerReach::for([$this->hit(19)]));
        $this->assertSame(AnswerReach::NONE, AnswerReach::for([]));
    }

    /**
     * Uji langsung tidak boleh lagi menyatakan "belum akan menjawab" untuk
     * pertanyaan yang EVA jawab lewat rangkuman.
     */
    public function test_uji_langsung_melaporkan_pita_rangkuman_apa_adanya(): void
    {
        $this->pakaiPencarianPalsu([$this->hit(32)]);
        $this->actingAsRole('eva');

        $this->postJson(route('eva.search.test'), ['question' => 'imel saya tidak bisa terima pesan masuk'])
            ->assertOk()
            ->assertJsonPath('reach', AnswerReach::MAYBE);
    }

    public function test_uji_langsung_tetap_tegas_saat_di_atas_ambang(): void
    {
        $this->pakaiPencarianPalsu([$this->hit(70)]);
        $this->actingAsRole('eva');

        $this->postJson(route('eva.search.test'), ['question' => 'email saya tidak bisa terima pesan masuk'])
            ->assertOk()
            ->assertJsonPath('reach', AnswerReach::ANSWER)
            ->assertJsonPath('would_answer', true);
    }

    public function test_uji_langsung_tetap_tegas_saat_tidak_ada_kandidat(): void
    {
        $this->pakaiPencarianPalsu([]);
        $this->actingAsRole('eva');

        $this->postJson(route('eva.search.test'), ['question' => 'pertanyaan tanpa materi sama sekali'])
            ->assertOk()
            ->assertJsonPath('reach', AnswerReach::NONE)
            ->assertJsonPath('would_answer', false);
    }

    /** @param SearchHit[] $hits */
    private function pakaiPencarianPalsu(array $hits): void
    {
        $this->app->bind(KnowledgeSearch::class, fn () => new class($hits) implements KnowledgeSearch
        {
            /** @param SearchHit[] $hits */
            public function __construct(private readonly array $hits) {}

            public function cari(string $pertanyaan, int $limit = 5): array
            {
                return array_slice($this->hits, 0, $limit);
            }
        });
    }

    private function hit(int $confidence): SearchHit
    {
        return new SearchHit(
            sourceType: Article::class,
            sourceId: 1,
            title: 'Troubleshooting Email MAILIA',
            answer: 'Outlook MAILIA yang tidak menerima email masuk…',
            confidence: $confidence,
            catalogSubjectId: null,
        );
    }
}
