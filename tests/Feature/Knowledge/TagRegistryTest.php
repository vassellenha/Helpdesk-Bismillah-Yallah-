<?php

namespace Tests\Feature\Knowledge;

use App\Models\Knowledge\Article;
use App\Services\Knowledge\TagRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Mengunci logika TagRegistry, terutama dua yang paling mudah salah:
 *
 *  - Penyaringan tag dilakukan di PHP, bukan LIKE '%tag%'. LIKE menganggap
 *    "sap" cocok dengan "sapa" dan "wasap" — kesalahan yang tak terlihat sampai
 *    ada yang memeriksa satu per satu.
 *  - rename() MENGGABUNG, bukan menduplikasi, saat tujuan sudah ada.
 */
final class TagRegistryTest extends TestCase
{
    use RefreshDatabase;

    private TagRegistry $tags;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tags = new TagRegistry;
    }

    private function article(string $title, string $tags): void
    {
        DB::table('kb_articles')->insert(['title' => $title, 'tags' => $tags]);
    }

    private function faq(string $question, string $tags): void
    {
        DB::table('kb_faqs')->insert(['question' => $question, 'answer' => 'x', 'tags' => $tags]);
    }

    private function document(string $name, string $tags): void
    {
        DB::table('kb_documents')->insert(['name' => $name, 'tags' => $tags]);
    }

    public function test_split_mengecilkan_huruf_memangkas_dan_membuang_duplikat(): void
    {
        $this->assertSame(['sap', 'vpn'], TagRegistry::split('SAP, sap,  vpn , ,'));
    }

    public function test_all_menghitung_pemakaian_lintas_jenis(): void
    {
        $this->article('A', 'vpn, sap');
        $this->faq('Q', 'vpn');
        $this->document('D', 'sap');

        $tally = $this->tags->all()->keyBy('tag');

        $this->assertSame(2, $tally['vpn']['total']);
        $this->assertSame(['Artikel' => 1, 'FAQ' => 1], $tally['vpn']['by_type']);
        $this->assertSame(2, $tally['sap']['total']);
        $this->assertSame(['Artikel' => 1, 'Dokumen' => 1], $tally['sap']['by_type']);
    }

    /**
     * INTI: tag "sap" tidak boleh menyeret baris ber-tag "sapa" atau "wasap".
     * LIKE dipakai hanya untuk mempersempit; keputusan akhir di PHP dengan
     * pencocokan persis.
     */
    public function test_materials_tidak_cocok_dengan_tag_yang_hanya_mirip(): void
    {
        $this->article('Benar', 'sap');
        $this->article('Palsu awalan', 'sapa');
        $this->article('Palsu tengah', 'wasap');

        $judul = array_column($this->tags->materials('sap')['articles'], 'title');

        $this->assertSame(['Benar'], $judul);
    }

    public function test_rename_menggabung_bukan_menduplikasi(): void
    {
        // Baris sudah punya "vpn"; mengganti "sap" jadi "vpn" harus menyisakan
        // SATU "vpn", bukan dua.
        $this->article('A', 'sap, vpn');

        $this->tags->rename('sap', 'vpn');

        $tersimpan = DB::table('kb_articles')->where('title', 'A')->value('tags');
        $this->assertSame(['vpn'], TagRegistry::split($tersimpan));
    }

    public function test_rename_mengubah_di_seluruh_jenis(): void
    {
        $this->article('A', 'sandi');
        $this->faq('Q', 'sandi');
        $this->document('D', 'sandi');

        $changed = $this->tags->rename('sandi', 'password');

        $this->assertSame(3, $changed);
        $this->assertArrayHasKey('password', $this->tags->all()->keyBy('tag')->all());
        $this->assertArrayNotHasKey('sandi', $this->tags->all()->keyBy('tag')->all());
    }

    /** Kembar yang hanya beda tanda baca ("wi-fi" vs "wifi") jadi satu kelompok. */
    public function test_near_duplicates_mendeteksi_beda_tanda_baca(): void
    {
        $this->article('A', 'wi-fi');
        $this->faq('Q', 'wifi');

        $dup = $this->tags->nearDuplicates();

        $this->assertCount(1, $dup);
        $this->assertEqualsCanonicalizing(
            ['wi-fi', 'wifi'],
            array_column($dup[0]['tags'], 'tag'),
        );
    }

    public function test_tags_for_hanya_mengembalikan_tag_jenis_itu(): void
    {
        $this->article('A', 'vpn, sap');
        $this->faq('Q', 'mfa');

        $this->assertSame(['sap', 'vpn'], $this->tags->tagsFor(Article::class));
    }
}
