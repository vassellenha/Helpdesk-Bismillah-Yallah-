<?php

namespace App\Services\Knowledge;

use App\Models\Knowledge\Article;
use App\Models\Knowledge\Chunk;
use App\Models\Knowledge\Document;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Jalur dokumen → potongan → artikel.
 *
 * Ini jalur yang di mockup dipanggil tapi tidak pernah didefinisikan
 * (`attachArticle`), sehingga tidak pernah benar-benar berjalan — tidak
 * ketahuan karena data contohnya sudah membawa hasil jadi.
 *
 * Dua sifat yang wajib dipegang:
 *   1. Satu dokumen melahirkan SATU artikel.
 *   2. Indeks ulang memperbarui artikel yang sama, tidak menggandakannya.
 * Keduanya ditegakkan unique(source_document_id) di database, bukan hanya di
 * kode ini.
 */
final class DocumentIndexer
{
    /** Panjang target satu potongan, dalam karakter. */
    private const CHUNK_SIZE = 900;

    /**
     * Batas jumlah potongan per dokumen.
     *
     * Tanpa batas, satu unggahan 20 MB berubah jadi puluhan ribu INSERT di
     * dalam SATU transaksi — dan yang menanggungnya bukan pengunggahnya,
     * melainkan setiap pertanyaan EVA yang menunggu di belakangnya.
     *
     * 500 × 900 karakter ≈ 450 ribu karakter: jauh di atas SOP terpanjang yang
     * masuk akal, jadi yang tersentuh batas ini hampir pasti bukan SOP.
     */
    private const MAX_CHUNKS = 500;

    /** Panjang ringkasan artikel yang dihasilkan otomatis. */
    private const SUMMARY_LENGTH = 240;

    /** Perkiraan karakter per halaman, dipakai bila page_count belum diisi. */
    private const CHARS_PER_PAGE = 1800;

    public function index(Document $document): Document
    {
        $text = trim((string) $document->extracted_text);

        if ($text === '') {
            $document->update([
                'status' => Document::STATUS_FAILED,
                'indexed_at' => null,
            ]);

            return $document->fresh();
        }

        return DB::transaction(function () use ($document, $text) {
            $this->rebuildChunks($document, $text);

            $document->update([
                'status' => Document::STATUS_INDEXED,
                'page_count' => $document->page_count ?: (int) ceil(mb_strlen($text) / self::CHARS_PER_PAGE),
                'indexed_at' => now(),
            ]);

            $this->attachArticle($document->fresh(), $text);

            return $document->fresh(['article', 'chunks']);
        });
    }

    /**
     * Artikel yang lahir dari dokumen ini. Judul/ringkasan hanya diisi saat
     * artikel pertama kali dibuat — sesudah itu milik admin, dan indeks ulang
     * tidak boleh menimpa suntingannya.
     */
    private function attachArticle(Document $document, string $text): Article
    {
        $article = Article::firstOrNew(['source_document_id' => $document->id]);

        if (! $article->exists) {
            $article->fill([
                'title' => $document->name,
                'summary' => $this->summarize($text),
                'body' => $text,
                'status' => Article::STATUS_DRAFT,
                'is_eva_visible' => true,
                'author_id' => $document->uploaded_by,
                'tags' => $document->tags,
            ]);
        }

        // Subject katalog selalu ikut dokumennya: kalau admin memindahkan
        // dokumen ke subject lain, artikelnya harus ikut, kalau tidak angka
        // coverage menunjuk subject yang salah.
        $article->catalog_subject_id = $document->catalog_subject_id;
        $article->save();

        // Tautan tambahan milik admin dan sengaja TIDAK disentuh indeks ulang —
        // kecuali satu hal: subject yang baru saja jadi subject utama tidak
        // boleh tertinggal sebagai tautan tambahan. Dibiarkan, subject itu
        // tercatat di dua tempat dan namanya muncul dua kali di daftar artikel.
        if ($article->catalog_subject_id !== null) {
            $article->subjects()->detach($article->catalog_subject_id);
        }

        return $article;
    }

    private function rebuildChunks(Document $document, string $text): void
    {
        // Hapus dulu, bukan menambah — inilah yang membuat indeks ulang tidak
        // menggandakan isi.
        $document->chunks()->delete();

        $semua = $this->splitIntoChunks($text);
        $batas = (int) config('eva.max_chunks', self::MAX_CHUNKS);
        $dipakai = array_slice($semua, 0, $batas);

        // Pemotongan TIDAK boleh diam-diam: dokumen tampak "indexed" utuh
        // padahal ekornya tidak pernah masuk. Satu baris log adalah bedanya
        // antara batas yang disengaja dan materi yang hilang tanpa jejak.
        if (count($semua) > count($dipakai)) {
            Log::warning('Potongan dokumen dipangkas di batas maksimum', [
                'document_id' => $document->id,
                'potongan_dihasilkan' => count($semua),
                'potongan_disimpan' => count($dipakai),
            ]);
        }

        foreach ($dipakai as $ordinal => $content) {
            Chunk::create([
                'document_id' => $document->id,
                'ordinal' => $ordinal,
                'content' => $content,
            ]);
        }
    }

    /**
     * Potong per paragraf, gabungkan paragraf pendek sampai mendekati
     * CHUNK_SIZE. Memotong di batas paragraf menjaga satu langkah prosedur
     * tetap utuh dalam satu potongan.
     *
     * @return string[]
     */
    private function splitIntoChunks(string $text): array
    {
        $paragraphs = preg_split('/\n\s*\n/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $chunks = [];
        $buffer = '';

        foreach ($paragraphs as $paragraph) {
            $paragraph = trim($paragraph);

            if ($paragraph === '') {
                continue;
            }

            if ($buffer !== '' && mb_strlen($buffer) + mb_strlen($paragraph) > self::CHUNK_SIZE) {
                $chunks[] = $buffer;
                $buffer = '';
            }

            $buffer = $buffer === '' ? $paragraph : $buffer."\n\n".$paragraph;
        }

        if ($buffer !== '') {
            $chunks[] = $buffer;
        }

        return $chunks;
    }

    private function summarize(string $text): string
    {
        $firstParagraph = trim(preg_split('/\n\s*\n/u', $text)[0] ?? $text);

        return Str::limit($firstParagraph, self::SUMMARY_LENGTH);
    }
}
