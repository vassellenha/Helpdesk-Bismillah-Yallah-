<?php

namespace Tests\Feature\Knowledge;

use App\Services\Knowledge\AnswerSourceSettings;
use App\Services\Knowledge\KnowledgeSearch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Mengunci sifat mati sakelar sumber jawaban: ia NYATA, bukan hiasan.
 *
 * Tes terakhir di sini membuktikan integrasinya — mematikan kedua sumber
 * membuat FulltextKnowledgeSearch mengembalikan kosong TANPA menyentuh DB.
 * Itu bukti gerbangnya benar-benar ada di jalur pencarian, bukan sekadar nilai
 * tersimpan. (Pemotongan satu-sumber end-to-end butuh FULLTEXT MySQL yang tak
 * ada di SQLite; itu diverifikasi manual dan dicatat di rencana.)
 */
final class AnswerSourceSettingsTest extends TestCase
{
    use RefreshDatabase;

    private AnswerSourceSettings $settings;

    protected function setUp(): void
    {
        parent::setUp();
        $this->settings = app(AnswerSourceSettings::class);
    }

    public function test_bawaan_semua_menyala_tanpa_baris(): void
    {
        $this->assertTrue($this->settings->articlesEnabled());
        $this->assertTrue($this->settings->faqsEnabled());
    }

    public function test_mematikan_satu_sumber_tidak_menyentuh_yang_lain(): void
    {
        $this->settings->set(AnswerSourceSettings::SOURCE_FAQS, false);

        $this->assertFalse($this->settings->faqsEnabled(), 'FAQ dimatikan');
        $this->assertTrue($this->settings->articlesEnabled(), 'artikel tak ikut mati');
    }

    public function test_perubahan_langsung_terbaca_bukan_cache_basi(): void
    {
        $this->settings->set(AnswerSourceSettings::SOURCE_ARTICLES, false);
        $this->assertFalse($this->settings->articlesEnabled());

        // Nyalakan lagi — pembacaan berikutnya wajib memantulkan nilai baru,
        // bukan nilai lama dari cache.
        $this->settings->set(AnswerSourceSettings::SOURCE_ARTICLES, true);
        $this->assertTrue($this->settings->articlesEnabled());
    }

    public function test_menolak_sumber_yang_tak_dikenal(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->settings->set('tiket', true);
    }

    /**
     * Bukti gerbang nyata: kedua sumber mati → EVA tak punya apa pun untuk
     * dicari, cari() mengembalikan kosong dan berhenti sebelum menyentuh DB.
     * Kalau toggle cuma hiasan, baris ini akan menembus ke query FULLTEXT.
     */
    public function test_kedua_sumber_mati_membuat_pencarian_kosong(): void
    {
        $this->settings->set(AnswerSourceSettings::SOURCE_ARTICLES, false);
        $this->settings->set(AnswerSourceSettings::SOURCE_FAQS, false);

        $hasil = app(KnowledgeSearch::class)->cari('reset password sap');

        $this->assertSame([], $hasil);
    }
}
