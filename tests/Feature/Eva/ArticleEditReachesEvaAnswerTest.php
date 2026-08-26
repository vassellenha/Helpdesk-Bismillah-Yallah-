<?php

declare(strict_types=1);

namespace Tests\Feature\Eva;

use App\Models\Knowledge\Article;
use App\Models\Knowledge\Chunk;
use App\Models\Knowledge\Document;
use App\Services\Knowledge\DocumentIndexer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsRole;
use Tests\TestCase;

/**
 * Menyunting artikel harus sampai ke jawaban EVA.
 *
 * Sebelum perbaikan ini tidak. Teks yang dikutip EVA diambil dari POTONGAN
 * dokumen (lihat FulltextKnowledgeSearch::scoreArticles), sedangkan menyunting
 * artikel hanya menulis kolom `body` milik artikel — tidak ada satu pun kode
 * yang memperbarui potongannya. Artikel dan dokumen menyimpan dua salinan teks
 * yang sama, dan hanya salinan yang TIDAK disunting yang dibaca EVA.
 *
 * Yang membuatnya berbahaya adalah semuanya terlihat berhasil: tombol Simpan
 * sukses, artikel tetap dikutip, keyakinannya tetap tinggi. Yang tidak terlihat
 * hanya satu — kalimat yang sampai ke pengguna masih yang lama. Seorang
 * penyunting yang membetulkan nominal biaya, tenggat, atau langkah keamanan
 * tidak punya cara tahu koreksinya tidak pernah tayang.
 *
 * Ditemukan saat UAT test case 38.
 */
final class ArticleEditReachesEvaAnswerTest extends TestCase
{
    use ActsAsRole, RefreshDatabase;

    private const TEKS_ASLI = "SOP Ganti Kartu Akses\n\nBiaya penggantian kartu akses adalah Rp 50.000 dan dipotong dari payroll bulan berikutnya.\n\nKartu pengganti terbit paling lama 3 hari kerja sejak permohonan disetujui atasan.";

    private const TEKS_BARU = "SOP Ganti Kartu Akses\n\nBiaya penggantian kartu akses adalah Rp 75.000 dan dipotong dari payroll bulan berikutnya.\n\nKartu pengganti terbit paling lama 5 hari kerja sejak permohonan disetujui atasan.";

    public function test_menyunting_isi_artikel_memperbarui_potongan_yang_dibaca_eva(): void
    {
        $article = $this->artikelTerbit();
        $this->actingAsRole('eva');

        $this->sunting($article, self::TEKS_BARU)->assertOk();

        $potongan = Chunk::where('document_id', $article->source_document_id)
            ->pluck('content')
            ->implode("\n");

        $this->assertStringContainsString('Rp 75.000', $potongan);
        $this->assertStringNotContainsString('Rp 50.000', $potongan);
    }

    /**
     * Indeks ulang memotong dari `extracted_text`, bukan dari berkas aslinya.
     * Kalau suntingan artikel tidak ikut menulis ke sana, tombol Indeks ulang
     * akan diam-diam mengembalikan teks lama — jebakan kedua yang persis
     * sesulit dilihat seperti yang pertama.
     */
    public function test_indeks_ulang_tidak_mengembalikan_teks_lama(): void
    {
        $article = $this->artikelTerbit();
        $this->actingAsRole('eva');
        $this->sunting($article, self::TEKS_BARU)->assertOk();

        app(DocumentIndexer::class)->index(Document::find($article->source_document_id));

        $potongan = Chunk::where('document_id', $article->source_document_id)
            ->pluck('content')
            ->implode("\n");

        $this->assertStringContainsString('Rp 75.000', $potongan);
        $this->assertStringNotContainsString('Rp 50.000', $potongan);
    }

    /** Judul dan ringkasan milik penyunting; indeks ulang tidak boleh menimpanya. */
    public function test_indeks_ulang_tidak_menimpa_judul_hasil_suntingan(): void
    {
        $article = $this->artikelTerbit();
        $this->actingAsRole('eva');

        $this->putJson(route('eva.articles.update', $article), [
            'title' => 'SOP Ganti Kartu Akses (Revisi 2)',
            'summary' => 'Ringkasan hasil suntingan admin.',
            'body' => self::TEKS_BARU,
            'status' => Article::STATUS_PUBLISHED,
            'is_eva_visible' => true,
        ])->assertOk();

        app(DocumentIndexer::class)->index(Document::find($article->source_document_id));

        $article = $article->fresh();
        $this->assertSame('SOP Ganti Kartu Akses (Revisi 2)', $article->title);
        $this->assertSame('Ringkasan hasil suntingan admin.', $article->summary);
    }

    /** Artikel tanpa dokumen sumber tetap bisa disunting tanpa error. */
    public function test_artikel_tanpa_dokumen_sumber_tetap_bisa_disunting(): void
    {
        $article = Article::create([
            'title' => 'Artikel Tulisan Tangan',
            'summary' => 'Ditulis manusia, bukan dari dokumen.',
            'body' => 'Isi lama.',
            'status' => Article::STATUS_PUBLISHED,
            'is_eva_visible' => true,
        ]);

        $this->actingAsRole('eva');

        $this->sunting($article, 'Isi baru hasil suntingan.')->assertOk();

        $this->assertSame('Isi baru hasil suntingan.', $article->fresh()->body);
    }

    private function sunting(Article $article, string $body)
    {
        return $this->putJson(route('eva.articles.update', $article), [
            'title' => $article->title,
            'summary' => $article->summary,
            'body' => $body,
            'status' => Article::STATUS_PUBLISHED,
            'is_eva_visible' => true,
        ]);
    }

    private function artikelTerbit(): Article
    {
        $document = Document::create([
            'name' => 'SOP Ganti Kartu Akses',
            'original_filename' => 'sop-kartu-akses.md',
            'extension' => 'md',
            'size_bytes' => 1024,
            'storage_path' => 'kb/sop-kartu-akses.md',
            'extracted_text' => self::TEKS_ASLI,
            'status' => Document::STATUS_INDEXED,
            'is_eva_visible' => true,
        ]);

        app(DocumentIndexer::class)->index($document);

        $article = Article::where('source_document_id', $document->id)->firstOrFail();
        $article->update(['status' => Article::STATUS_PUBLISHED, 'is_eva_visible' => true]);

        return $article->fresh();
    }
}
