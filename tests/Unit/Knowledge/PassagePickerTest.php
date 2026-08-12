<?php

declare(strict_types=1);

namespace Tests\Unit\Knowledge;

use App\Models\Knowledge\Article;
use App\Models\Knowledge\Chunk;
use App\Models\Knowledge\Document;
use App\Services\Knowledge\PassagePicker;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Jawaban EVA untuk dokumen diambil dari POTONGAN yang cocok, bukan ringkasan.
 *
 * Bug yang memicu kelas ini: ringkasan artikel hasil unggahan dibuat otomatis
 * dari paragraf PERTAMA dokumen, dan untuk SOP berkop surat paragraf itu adalah
 * kop suratnya. Setiap pertanyaan tentang dokumen tersebut dijawab
 * "PT ADHI KARYA (PERSERO) TBK…" — artikelnya benar, jawabannya tidak menjawab
 * apa pun. Ditemukan saat menguji EVA dengan SOP yang sesungguhnya.
 */
final class PassagePickerTest extends TestCase
{
    use RefreshDatabase;

    private const KOP = 'PT ADHI KARYA (PERSERO) TBK DEPARTEMEN TEKNOLOGI INFORMASI HELPDESK 2.0';

    private function picker(): PassagePicker
    {
        return app(PassagePicker::class);
    }

    /** @param list<string> $isiChunk */
    private function artikelBerdokumen(array $isiChunk, string $ringkasan = self::KOP): Article
    {
        $document = Document::create([
            'name' => 'SOP-TI-01 Login dan Otorisasi SAP',
            'extension' => 'pdf',
            'status' => 'indexed',
        ]);

        foreach ($isiChunk as $i => $isi) {
            Chunk::create(['document_id' => $document->id, 'ordinal' => $i, 'content' => $isi]);
        }

        return Article::create([
            'title' => $document->name,
            'summary' => $ringkasan,
            'body' => implode("\n\n", $isiChunk),
            'source_document_id' => $document->id,
            'status' => Article::STATUS_PUBLISHED,
            'is_eva_visible' => true,
        ]);
    }

    private function jawaban(Article $article, array $tokens): ?string
    {
        return $this->picker()->forArticles(new EloquentCollection([$article]), $tokens)[$article->id] ?? null;
    }

    public function test_kop_surat_tidak_pernah_jadi_jawaban_bila_ada_potongan_yang_cocok(): void
    {
        $article = $this->artikelBerdokumen([
            self::KOP,
            'Password Expired. Password SAP telah melewati masa berlaku 90 hari sejak terakhir diubah.',
        ]);

        $jawaban = $this->jawaban($article, ['password', 'berlaku']);

        $this->assertNotNull($jawaban);
        $this->assertStringNotContainsString('PT ADHI KARYA', $jawaban);
        $this->assertStringContainsString('90 hari', $jawaban);
    }

    /**
     * Inti perbaikan kedua: kata yang muncul di SEMUA potongan tidak boleh
     * menentukan. Versi pertama hanya menghitung jumlah kata yang cocok, dan
     * potongan Password Expired menang atas potongan User Locked hanya karena
     * memuat lebih banyak kata umum.
     */
    public function test_kata_pembeda_mengalahkan_kata_yang_ada_di_mana_mana(): void
    {
        $article = $this->artikelBerdokumen([
            'SAP password akun tiket. Password Expired: ganti password lewat layar login SAP.',
            'SAP password akun tiket. Bila akun terkunci, tunggu 30 menit sebelum mencoba lagi.',
            'SAP password akun tiket. Gagal print pada SAP diselesaikan tim printing.',
        ]);

        $jawaban = $this->jawaban($article, ['sap', 'akun', 'terkunci']);

        $this->assertStringContainsString('30 menit', $jawaban);
    }

    /**
     * Chunk dipotong per ~900 karakter dan kerap melintasi batas bagian, jadi
     * kalimat yang menjawab sering berada di TENGAH potongan.
     */
    public function test_cuplikan_bergeser_ke_bagian_yang_cocok_bukan_selalu_awal_potongan(): void
    {
        $article = $this->artikelBerdokumen([
            str_repeat('Bagian pembuka yang panjang dan tidak relevan sama sekali. ', 12)
                .'Bila akun terkunci, tunggu 30 menit sebelum mencoba lagi.',
        ]);

        $jawaban = $this->jawaban($article, ['terkunci']);

        $this->assertStringContainsString('30 menit', $jawaban);
    }

    public function test_kembali_ke_ringkasan_bila_tidak_ada_potongan_yang_cocok(): void
    {
        $article = $this->artikelBerdokumen([
            self::KOP,
            'Password Expired. Ganti password lewat layar login.',
        ]);

        // Tidak ada potongan yang memuat kata ini sama sekali.
        $this->assertNull($this->jawaban($article, ['jaringan', 'vpn']));
    }

    /**
     * Artikel tulisan tangan tidak punya dokumen sumber. Ringkasannya ditulis
     * manusia sebagai jawaban, jadi tidak boleh diganggu.
     */
    public function test_artikel_tulisan_tangan_dibiarkan_memakai_ringkasannya(): void
    {
        $article = Article::create([
            'title' => 'Cara Reset Password',
            'summary' => 'Hubungi service desk di ekstensi 100.',
            'body' => 'Isi panjang.',
            'status' => Article::STATUS_PUBLISHED,
            'is_eva_visible' => true,
        ]);

        $this->assertNull($this->jawaban($article, ['password']));
    }

    public function test_jawaban_dipangkas_dan_spasi_berlebih_dari_pdf_dipadatkan(): void
    {
        $article = $this->artikelBerdokumen([
            "Akun    terkunci.\n\n   Tunggu     30 menit.".str_repeat(' Kalimat tambahan yang panjang sekali.', 40),
        ]);

        $jawaban = $this->jawaban($article, ['terkunci']);

        $this->assertStringNotContainsString('  ', $jawaban);
        $this->assertLessThanOrEqual(650, mb_strlen($jawaban));
    }
}
