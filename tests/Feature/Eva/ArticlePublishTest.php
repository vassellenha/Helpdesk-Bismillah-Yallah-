<?php

namespace Tests\Feature\Eva;

use App\Models\Knowledge\Article;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsEvaAdmin;
use Tests\TestCase;

/**
 * Menerbitkan artikel dengan satu klik.
 *
 * Sebelum ini status hanya bisa diubah lewat dropdown di dalam drawer sunting:
 * Sunting → gulir → ganti Status → Simpan. Empat langkah untuk tindakan yang
 * paling sering dilakukan setelah dokumen diunggah, sementara "Tampilkan di
 * EVA" — yang jauh lebih jarang disentuh — sudah bisa satu klik di baris.
 *
 * Yang dikunci di sini:
 *  1. Statusnya benar-benar berpindah, dua arah.
 *  2. Menerbitkan MENGUBAH apa yang bisa dijawab EVA. Itu inti tombolnya;
 *     kalau `answerable()` tidak ikut berubah, tombolnya cuma mengganti warna
 *     lencana sementara artikelnya tetap tak terpakai.
 *  3. Tombol ini TIDAK menyentuh isi artikel — suntingan admin tidak boleh
 *     hilang gara-gara menekan Terbitkan.
 */
final class ArticlePublishTest extends TestCase
{
    use ActsAsEvaAdmin;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsEvaAdmin();
    }

    private function article(string $status, array $attributes = []): Article
    {
        return Article::create(array_merge([
            'title' => 'SOP Unlock Akun SAP',
            'summary' => 'Ringkasan',
            'body' => 'Langkah membuka akun SAP yang terkunci.',
            'status' => $status,
            'is_eva_visible' => true,
        ], $attributes));
    }

    public function test_artikel_draf_bisa_diterbitkan_satu_klik(): void
    {
        $article = $this->article(Article::STATUS_DRAFT);

        $this->postJson("/eva/api/articles/{$article->id}/publish")
            ->assertOk()
            ->assertJsonPath('status', Article::STATUS_PUBLISHED);

        $this->assertSame(Article::STATUS_PUBLISHED, $article->fresh()->status);
    }

    /** Simetris: artikel yang keliru terbit bisa ditarik kembali secepat menerbitkannya. */
    public function test_artikel_terbit_bisa_ditarik_jadi_draf(): void
    {
        $article = $this->article(Article::STATUS_PUBLISHED);

        $this->postJson("/eva/api/articles/{$article->id}/publish")
            ->assertOk()
            ->assertJsonPath('status', Article::STATUS_DRAFT);

        $this->assertSame(Article::STATUS_DRAFT, $article->fresh()->status);
    }

    /**
     * Inti tombolnya: gerbang answerable() ikut berpindah. Artikel draf tidak
     * pernah dipakai EVA menjawab dan tidak dihitung menutup subject mana pun.
     */
    public function test_menerbitkan_membuat_artikel_bisa_dipakai_menjawab(): void
    {
        $article = $this->article(Article::STATUS_DRAFT);

        $this->assertSame(0, Article::answerable()->count());

        $this->postJson("/eva/api/articles/{$article->id}/publish")->assertOk();

        $this->assertSame(1, Article::answerable()->count());
    }

    /**
     * Artikel yang disembunyikan dari EVA tetap tersembunyi walau diterbitkan —
     * dua gerbang itu berdiri sendiri, dan tombol ini hanya menyentuh satu.
     */
    public function test_terbit_tidak_membatalkan_sembunyikan_dari_eva(): void
    {
        $article = $this->article(Article::STATUS_DRAFT, ['is_eva_visible' => false]);

        $this->postJson("/eva/api/articles/{$article->id}/publish")->assertOk();

        $this->assertFalse($article->fresh()->is_eva_visible);
        $this->assertSame(0, Article::answerable()->count());
    }

    /** Isi artikel tidak boleh tersentuh — tombol ini bukan penyimpan suntingan. */
    public function test_isi_artikel_tidak_berubah(): void
    {
        $article = $this->article(Article::STATUS_DRAFT, [
            'title' => 'Judul hasil suntingan admin',
            'body' => 'Isi yang sudah dirapikan dari hasil OCR.',
        ]);

        $this->postJson("/eva/api/articles/{$article->id}/publish")->assertOk();

        $fresh = $article->fresh();
        $this->assertSame('Judul hasil suntingan admin', $fresh->title);
        $this->assertSame('Isi yang sudah dirapikan dari hasil OCR.', $fresh->body);
    }
}
