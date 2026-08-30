<?php

declare(strict_types=1);

namespace Tests\Feature\Eva;

use App\Models\Knowledge\Article;
use App\Services\Knowledge\EvaResponder;
use App\Services\Knowledge\KnowledgeSearch;
use App\Services\Knowledge\KnowledgeSynthesizer;
use App\Services\Knowledge\SearchHit;
use App\Services\Knowledge\Synthesis;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsRole;
use Tests\TestCase;

/**
 * Jawaban rangkuman harus menyebut SELURUH sumber yang benar-benar dipakainya.
 *
 * Dulu hanya kandidat teratas yang ditampilkan sebagai rujukan, padahal
 * jawabannya dijahit dari beberapa dokumen sekaligus. Akibatnya karyawan yang
 * mengeklik rujukan untuk memastikan nomor formulir atau batas waktu TIDAK
 * MENEMUKANNYA di sana — fakta itu datang dari dokumen lain yang tidak pernah
 * disebut. Rujukan yang tidak memuat yang dirujuknya lebih merusak kepercayaan
 * daripada tidak ada rujukan sama sekali.
 */
final class AnswerSourcesTest extends TestCase
{
    use ActsAsRole, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAsRole('requester');
    }

    /** Tiga kandidat palsu dengan keyakinan yang menurun. */
    private function fakeSearch(): array
    {
        $hits = [
            new SearchHit(Article::class, 11, 'Syarat pengajuan akses', 'Hanya karyawan tetap.', 61, null),
            new SearchHit(Article::class, 22, 'Formulir pengajuan akses', 'Pakai formulir FRM-IT-014.', 46, null),
            new SearchHit(Article::class, 33, 'Waktu proses akses', 'Dibuka paling lambat 3 hari kerja.', 42, null),
        ];

        $this->instance(KnowledgeSearch::class, new class($hits) implements KnowledgeSearch
        {
            public function __construct(private readonly array $hits) {}

            /** @return SearchHit[] */
            public function cari(string $pertanyaan, int $limit = 5): array
            {
                return array_slice($this->hits, 0, $limit);
            }
        });

        return $hits;
    }

    /** @param list<int> $used indeks potongan yang diaku dipakai perangkum */
    private function fakeSynthesizer(array $used): void
    {
        $this->instance(KnowledgeSynthesizer::class, new class($used) implements KnowledgeSynthesizer
        {
            public function __construct(private readonly array $used) {}

            public function rangkum(string $question, array $passages): ?Synthesis
            {
                return new Synthesis('Hanya karyawan tetap, pakai FRM-IT-014, selesai 3 hari kerja.', $this->used);
            }
        });
    }

    public function test_seluruh_sumber_yang_dipakai_ikut_disebut(): void
    {
        $this->fakeSearch();
        $this->fakeSynthesizer([0, 1, 2]);

        $reply = app(EvaResponder::class)->jawab('bagaimana prosedur mengajukan akses');

        $this->assertSame(
            ['Syarat pengajuan akses', 'Formulir pengajuan akses', 'Waktu proses akses'],
            array_map(fn (SearchHit $h) => $h->title, $reply->sources),
        );
    }

    /** Yang TIDAK dipakai tidak boleh ikut disebut — rujukan palsu sama buruknya. */
    public function test_sumber_yang_tidak_dipakai_tidak_disebut(): void
    {
        $this->fakeSearch();
        $this->fakeSynthesizer([0, 2]);

        $reply = app(EvaResponder::class)->jawab('bagaimana prosedur mengajukan akses');

        $this->assertSame(
            ['Syarat pengajuan akses', 'Waktu proses akses'],
            array_map(fn (SearchHit $h) => $h->title, $reply->sources),
        );
    }

    /**
     * Perangkum tidak selalu memberi tahu potongan mana yang dipakainya —
     * modelnya bisa lupa, atau balasannya cacat. Jatuhnya harus ke perilaku
     * lama (satu sumber teratas), bukan ke daftar kosong yang membuat jawaban
     * tampil TANPA rujukan sama sekali.
     */
    public function test_tanpa_keterangan_sumber_jatuh_ke_kandidat_teratas(): void
    {
        $this->fakeSearch();
        $this->fakeSynthesizer([]);

        $reply = app(EvaResponder::class)->jawab('bagaimana prosedur mengajukan akses');

        $this->assertSame(['Syarat pengajuan akses'], array_map(fn (SearchHit $h) => $h->title, $reply->sources));
    }

    /** Kontrak lama tidak boleh goyah: `hit` tetap kandidat teratas. */
    public function test_hit_tetap_kandidat_teratas_untuk_pencatatan_log(): void
    {
        $this->fakeSearch();
        $this->fakeSynthesizer([1, 2]);

        $reply = app(EvaResponder::class)->jawab('bagaimana prosedur mengajukan akses');

        $this->assertSame('Syarat pengajuan akses', $reply->hit?->title);
        $this->assertSame(11, $reply->hit?->sourceId);
    }

    public function test_sumber_ikut_terkirim_ke_layar(): void
    {
        $this->fakeSearch();
        $this->fakeSynthesizer([0, 1]);

        $reply = app(EvaResponder::class)->jawab('bagaimana prosedur mengajukan akses');

        $this->assertSame(
            ['Syarat pengajuan akses', 'Formulir pengajuan akses'],
            array_column($reply->toArray()['sources'], 'title'),
        );
    }
}
