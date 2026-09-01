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

    /**
     * Kasus nyata dari produksi: SOP pendaftaran vendor ELISA. Pertanyaan
     * "berapa lama verifikasi vendor baru" tidak terjawab, padahal dokumennya
     * menulis "paling lambat 5 hari kerja" — hanya di potongan yang berbeda
     * dari yang terpilih.
     *
     * Potongan pembuka memuat lebih banyak kata pertanyaan ("verifikasi",
     * "vendor", "baru"), jadi penghitungan polos memenangkannya. Yang membalik
     * urutan adalah "lama": kata itu cuma ada di potongan yang menjawab.
     */
    public function test_potongan_penjawab_ikut_terbawa_walau_bukan_yang_paling_mirip(): void
    {
        $article = $this->artikelBerdokumen([
            'Pendaftaran vendor baru. Verifikasi vendor baru dilakukan Bagian Procurement lalu Bagian Keuangan atas lima dokumen legalitas vendor baru.',
            'Waktu proses. Verifikasi diselesaikan paling lambat 5 hari kerja sejak dokumen lengkap diterima.',
        ]);

        $tokens = ['berapa', 'lama', 'verifikasi', 'vendor', 'baru'];

        // Potongan pembuka memang yang paling mirip: ia memuat tiga kata
        // pertanyaan, sementara potongan penjawab cuma satu. Tidak ada satu pun
        // kata yang bisa memenangkannya — penanya menulis "lama", dokumennya
        // menulis "paling lambat". Memilih satu terbaik akan selalu meleset di
        // sini, dan itulah sebabnya keduanya harus ikut terbawa.
        $this->assertStringNotContainsString('5 hari kerja', (string) $this->jawaban($article, $tokens));

        $gabungan = implode(' ', $this->picker()->passagesFor(new EloquentCollection([$article]), $tokens)[$article->id] ?? []);

        $this->assertStringContainsString('5 hari kerja', $gabungan);
    }

    /**
     * CELAH YANG TERSISA: satu artikel hanya pernah menyumbang SATU potongan,
     * padahal pertanyaan sering membutuhkan dua bagian dokumen yang sama.
     *
     * "Apa syaratnya dan berapa lama prosesnya" adalah bentuk pertanyaan yang
     * wajar, dan jawabannya memang tersebar: syaratnya di bagian awal, lama
     * prosesnya di bagian lain. Selama hanya satu potongan yang dioper ke
     * perangkum, separuh jawabannya tidak pernah terbaca — dan perangkum
     * dengan jujur menjawab "tidak ada di KB" untuk sesuatu yang ada.
     *
     * Risikonya bukan cuma tidak menjawab: dokumen panjang punya sampai 500
     * potongan, dan 499 di antaranya tidak pernah ikut dipertimbangkan dalam
     * satu pertanyaan.
     */
    public function test_dua_bagian_dokumen_yang_sama_bisa_terbawa_sekaligus(): void
    {
        $article = $this->artikelBerdokumen([
            'Syarat pengajuan. Pemohon wajib melampirkan akta pendirian dan NPWP badan usaha.',
            'Bagian lain yang tidak menjawab apa pun mengenai pengajuan tersebut.',
            'Waktu proses. Pengajuan diselesaikan paling lambat 5 hari kerja sejak dokumen lengkap.',
        ]);

        $potongan = $this->picker()->passagesFor(
            new EloquentCollection([$article]),
            ['syarat', 'akta', 'lama', 'waktu', 'proses'],
        )[$article->id] ?? [];

        $gabungan = implode(' ', $potongan);

        $this->assertStringContainsString('akta pendirian', $gabungan, 'syaratnya tidak terbawa');
        $this->assertStringContainsString('5 hari kerja', $gabungan, 'lama prosesnya tidak terbawa');
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
