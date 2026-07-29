<?php

namespace Tests\Feature\Knowledge;

use App\Models\Knowledge\Faq;
use App\Services\Knowledge\FulltextKnowledgeSearch;
use App\Services\Knowledge\KnowledgeSearch;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use PDOException;
use Throwable;

/**
 * Pencarian A di atas FULLTEXT MySQL — mesin yang menentukan SETIAP jawaban EVA.
 *
 * Seluruh perilaku yang harus benar apa pun mesinnya ada di
 * KnowledgeSearchContract; berkas ini hanya menyediakan lingkungannya, lalu
 * menambahkan hal-hal yang KHAS mesin ini dan tidak berlaku untuk penggantinya.
 *
 * Sampai iterasi 16 kelas ini nol tes, dengan alasan yang sah: `whereFullText()`
 * tidak ada di SQLite, jadi tes lain (yang berjalan di SQLite memori) melempar
 * exception sebelum menyentuh apa pun. Akibatnya terbalik — komponen paling
 * menentukan justru paling tak terjaga, dan regresinya TIDAK memunculkan error:
 * EVA cuma menjawab lebih buruk, diam-diam.
 *
 * Maka tes ini berjalan di MySQL sungguhan, memakai pola melewati-diri-sendiri
 * yang sama seperti PdfOcrTest terhadap binari OCR: kalau MySQL tidak bisa
 * dihubungi, seluruh tes DILEWATI — bukan gagal palsu. (Sudah dibuktikan dengan
 * menjalankannya ke port yang salah: skipped, bukan failed.)
 *
 * Databasenya sendiri TIDAK perlu dibuat manual: sejak Laravel 11, `migrate`
 * membuat database yang belum ada. Jadi jangan menyimpulkan dari "tesnya hijau"
 * bahwa arahnya sudah benar — yang menjaga arah adalah penjaga `_test` di
 * `setUp()`, bukan keberadaan databasenya.
 *
 * DATABASE TERPISAH, BUKAN DATABASE DEV. Trait di bawah mengosongkan tabel;
 * menjalankannya di database dev akan menghapus seluruh isi Knowledge Base.
 *
 * DatabaseTruncation, BUKAN RefreshDatabase — dan ini bukan selera. Indeks
 * FULLTEXT InnoDB diperbarui saat COMMIT, sehingga baris yang ditulis di dalam
 * transaksi RefreshDatabase tidak terlihat oleh MATCH…AGAINST. Tesnya akan tetap
 * "hijau" lewat jalur fallback LIKE, dan jalur FULLTEXT-nya — yang justru jadi
 * alasan tes ini ada — tak pernah benar-benar dijalankan.
 */
final class FulltextKnowledgeSearchTest extends KnowledgeSearchContract
{
    use DatabaseTruncation;

    /** Database uji. Dibuat otomatis oleh migrate kalau belum ada. */
    private const TEST_DATABASE = 'helpdesk_eva_test';

    public function createApplication()
    {
        $app = parent::createApplication();

        $app['config']->set('database.default', 'mysql');
        $app['config']->set('database.connections.mysql.database', self::TEST_DATABASE);

        return $app;
    }

    protected function setUp(): void
    {
        // Penjaga terakhir sebelum tabel dikosongkan. Nama yang tidak berakhiran
        // `_test` berarti konfigurasinya salah arah, dan yang paling mungkin
        // ditunjuknya adalah database dev.
        if (! str_ends_with(self::TEST_DATABASE, '_test')) {
            $this->markTestSkipped('Database uji wajib berakhiran _test — menolak menyentuh database lain.');
        }

        try {
            parent::setUp();
            DB::connection()->getPdo();
        } catch (PDOException|Throwable $e) {
            $this->markTestSkipped(
                'MySQL/'.self::TEST_DATABASE.' tidak bisa dihubungi ('.$e->getMessage().'). '
                .'Nyalakan MySQL lebih dulu.'
            );
        }
    }

    protected function searchUnderTest(): KnowledgeSearch
    {
        return $this->app->make(FulltextKnowledgeSearch::class);
    }

    // ---- khas mesin ini, bukan kontrak -------------------------------------

    /**
     * Membuktikan bahwa yang menjembatani beda bentuk kata di kontrak
     * (`test_menjembatani_bentuk_kata_yang_berbeda`) benar-benar jalur FALLBACK,
     * bukan FULLTEXT yang kebetulan menemukannya.
     *
     * SEBABNYA adalah PELUCUTAN IMBUHAN, bukan token pendek seperti yang
     * tertulis di komentar `candidates()`. QuestionTokenizer melucuti
     * "mengaktifkan" → "aktif" sebelum kata itu sampai ke database, sedangkan
     * FULLTEXT natural-language mencocokkan KATA UTUH — dan "aktif" tidak ada di
     * dokumen mana pun. (Alasan yang tertulis di kode — token di bawah
     * `innodb_ft_min_token_size` = 3 — tidak pernah terjadi: tokenizer sudah
     * membuang kata di bawah 3 huruf lebih dulu.)
     *
     * Tanpa fallback ini, kegagalannya terlihat persis seperti "KB memang belum
     * punya jawabannya" — kesalahan termahal di sistem ini, karena tak ada
     * apa pun yang menunjukkan bahwa jawabannya sebenarnya ada.
     */
    public function test_fulltext_sendirian_tidak_menemukan_kata_hasil_pelucutan(): void
    {
        $this->faq([
            'question' => 'Bagaimana cara mengaktifkan lisensi Autocad?',
            'answer' => 'Kirim permintaan lisensi Autocad ke tim Helpdesk.',
        ]);

        $this->assertSame(
            0,
            Faq::query()->whereFullText(['question', 'answer'], 'aktif')->count(),
            'kalau ini tidak lagi 0, fallback LIKE bukan lagi yang menyelamatkan kontrak',
        );
    }

    /**
     * Pertanyaan tanpa kata bermakna berhenti SEBELUM menyentuh database.
     *
     * Kontrak hanya menuntut hasilnya kosong; di mesin berbasis SQL, tidak
     * menembak query sama sekali adalah janji yang lebih kuat dan bisa diperiksa.
     * Mesin embedding akan punya ukuran lain (tidak memanggil API), jadi ini
     * memang tempatnya di sini, bukan di kontrak.
     */
    public function test_pertanyaan_tanpa_kata_bermakna_tidak_menyentuh_database(): void
    {
        $this->article();

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->assertSame([], $this->searchUnderTest()->cari('yang dan atau'));

        $this->assertSame([], DB::getQueryLog());
    }
}
