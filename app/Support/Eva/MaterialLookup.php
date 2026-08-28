<?php

declare(strict_types=1);

namespace App\Support\Eva;

use App\Models\Knowledge\Article;
use App\Models\Knowledge\Document;
use App\Models\Knowledge\Faq;

/**
 * Mengambil satu materi rujukan untuk ditampilkan utuh di popup jawaban EVA.
 *
 * Berdiri sendiri, bukan method controller, karena dipanggil dari DUA permukaan
 * yang berbeda gerbang aksesnya — widget karyawan di portal dan EVA Preview di
 * konsol admin — dan keduanya wajib memulangkan materi yang sama persis. Aturan
 * "materi mana yang boleh dibaca" hanya boleh ditulis di satu tempat.
 *
 * GERBANGNYA scopeAnswerable(), sama persis dengan gerbang menjawab. Ini bukan
 * kehati-hatian berlebih: tanpa itu, endpoint ini menjadi jalan membaca artikel
 * draf dan materi yang sengaja disembunyikan dari EVA cukup dengan menebak
 * nomornya — sesuatu yang bahkan tidak menuntut peran admin. Materi yang tidak
 * pernah bisa dikutip EVA tidak punya alasan untuk bisa dibuka dari kutipan.
 *
 * Kunci jenisnya ('article'/'faq') SENGAJA bukan nama kelas PHP. Nama kelas
 * yang mengalir lewat URL memberi tahu penyerang susunan kode di dalam, dan
 * mengikat alamat publik pada namespace yang boleh berubah kapan saja.
 */
final class MaterialLookup
{
    public const TYPE_ARTICLE = 'article';

    public const TYPE_FAQ = 'faq';

    /**
     * Peta dari kunci di URL ke kelas modelnya. Jenis di luar daftar ini
     * memulangkan null — pemanggil menerjemahkannya jadi 404, bukan 500.
     */
    private const MODELS = [
        self::TYPE_ARTICLE => Article::class,
        self::TYPE_FAQ => Faq::class,
    ];

    /**
     * @return array<string, mixed>|null null bila jenisnya tak dikenal, materinya
     *                                   tidak ada, atau tidak boleh dipakai EVA
     */
    public static function find(string $type, int $id): ?array
    {
        $model = self::MODELS[$type] ?? null;

        if ($model === null) {
            return null;
        }

        $material = $model::query()->answerable()
            ->with('catalogSubject.service')
            ->find($id);

        if ($material === null) {
            return null;
        }

        return $type === self::TYPE_ARTICLE
            ? self::fromArticle($material)
            : self::fromFaq($material);
    }

    /** @return array<string, mixed> */
    private static function fromArticle(Article $article): array
    {
        return [
            'type' => self::TYPE_ARTICLE,
            'id' => $article->id,
            'title' => $article->title,
            'summary' => $article->summary,
            'body' => $article->body,
            'subject' => self::subject($article),
            'document' => SourceDocument::present(self::documentOf($article)),
            'updated_at' => $article->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Dokumen asal artikel — DIAMBIL LEWAT scopeQuotable(), bukan lewat relasi
     * telanjang.
     *
     * Artikelnya memang sudah lolos gerbang menjawab saat sampai di sini, tapi
     * dokumennya punya saklar sendiri: admin bisa menyembunyikan berkas asli
     * dari EVA sambil membiarkan artikelnya tetap menjawab. Membaca relasinya
     * langsung akan mengabaikan saklar itu diam-diam.
     *
     * Memakai scope yang sama dengan endpoint berkas juga menjaga keduanya
     * tidak pernah berbeda pendapat: apa pun yang popup tawarkan untuk dibuka,
     * pasti lolos gerbang saat benar-benar dibuka.
     */
    private static function documentOf(Article $article): ?Document
    {
        return $article->sourceDocument()->quotable()->first();
    }

    /**
     * FAQ tidak punya judul terpisah dari pertanyaannya, dan tidak punya
     * ringkasan. Pertanyaannya yang naik jadi judul supaya popup punya bentuk
     * yang sama untuk kedua jenis materi — layar tidak perlu tahu ia sedang
     * menampilkan artikel atau FAQ untuk bisa merendernya.
     *
     * @return array<string, mixed>
     */
    private static function fromFaq(Faq $faq): array
    {
        return [
            'type' => self::TYPE_FAQ,
            'id' => $faq->id,
            'title' => $faq->question,
            'summary' => null,
            'body' => $faq->answer,
            'subject' => self::subject($faq),
            // FAQ ditulis langsung oleh admin, tidak pernah lahir dari berkas.
            // Kuncinya tetap dikirim (bernilai null) supaya layar punya SATU
            // bentuk data untuk kedua jenis materi.
            'document' => null,
            'updated_at' => $faq->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Materi boleh tidak bertaut ke katalog — FAQ khususnya sering ditulis
     * lebih dulu, sebelum ada subject yang cocok. Itu keadaan yang wajar, jadi
     * hasilnya null dan popup cukup tidak menampilkan barisnya.
     *
     * @return array<string, string>|null
     */
    private static function subject(Article|Faq $material): ?array
    {
        $subject = $material->catalogSubject;

        if ($subject === null) {
            return null;
        }

        return [
            'subject' => $subject->name,
            'service' => $subject->service?->name ?? '—',
        ];
    }
}
