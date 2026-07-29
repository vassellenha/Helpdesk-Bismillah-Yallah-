<?php

namespace Tests\Feature\Eva;

use App\Models\Knowledge\AnswerLog;
use App\Models\Knowledge\AnswerRating;
use App\Models\Knowledge\Article;
use App\Models\Knowledge\Faq;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Concerns\ActsAsEvaAdmin;
use Tests\TestCase;

/**
 * Rating & Feedback — daftar kerja, bukan laporan.
 *
 * Layar ini dirombak karena tabel "performa per materi" tumbuh tanpa batas:
 * pada 100 materi, tanggapan tertulis karyawan terdorong ribuan piksel ke
 * bawah dan tidak pernah terbaca. Perombakannya memasangkan tanggapan itu ke
 * materinya masing-masing, dan yang dikunci di sini adalah hal-hal yang kalau
 * salah membuat pasangan itu BOHONG:
 *
 *  1. Tanggapan tidak boleh bocor antar materi. Artikel #3 dan FAQ #3 sama-sama
 *     ada; menempelkan keluhan atas yang satu ke yang lain mengirim admin
 *     memperbaiki materi yang tidak bermasalah.
 *  2. Urutan tetap terburuk-dulu. Itu satu-satunya yang membuat daftar ini
 *     menghasilkan pekerjaan.
 *  3. Setiap baris membawa jalan keluarnya sendiri (`manage_url`).
 */
final class RatingScreenTest extends TestCase
{
    use ActsAsEvaAdmin;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        $this->actingAsEvaAdmin();
    }

    private function article(string $title): Article
    {
        return Article::create([
            'title' => $title,
            'summary' => 'Ringkasan.',
            'body' => 'Isi artikel.',
            'status' => Article::STATUS_PUBLISHED,
            'is_eva_visible' => true,
        ]);
    }

    private function faq(string $question): Faq
    {
        return Faq::create([
            'question' => $question,
            'answer' => 'Jawaban.',
            'is_eva_visible' => true,
        ]);
    }

    /** Satu penilaian atas satu jawaban EVA yang bersumber dari `$source`. */
    private function rate(object $source, int $stars, ?string $comment = null): AnswerRating
    {
        $log = AnswerLog::create([
            'question' => 'pertanyaan uji',
            'source_type' => $source::class,
            'source_id' => $source->id,
            'outcome' => AnswerLog::OUTCOME_ANSWERED,
            'confidence' => 80,
        ]);

        return AnswerRating::create([
            'answer_log_id' => $log->id,
            'stars' => $stars,
            'comment' => $comment,
        ]);
    }

    /** @return array<int,array<string,mixed>> */
    private function sources(): array
    {
        return $this->get('/eva/ratings')->assertOk()->viewData('sources');
    }

    private function sourceFor(string $key): array
    {
        $row = collect($this->sources())->firstWhere('key', $key);

        $this->assertNotNull($row, "baris {$key} tidak ada di layar");

        return $row;
    }

    // ---- layar -------------------------------------------------------------

    public function test_halaman_tampil_walau_belum_ada_penilaian(): void
    {
        $this->get('/eva/ratings')
            ->assertOk()
            ->assertViewHas('sources', [])
            ->assertViewHas('comments', []);
    }

    // ---- urutan ------------------------------------------------------------

    /**
     * Terburuk lebih dulu. Kalau ini terbalik, layar tetap "berisi" tapi materi
     * yang perlu ditulis ulang berada di dasar daftar yang tak pernah digulir.
     */
    public function test_materi_bernilai_terburuk_berada_di_urutan_pertama(): void
    {
        $bagus = $this->article('SOP yang disukai');
        $buruk = $this->article('SOP yang membingungkan');

        $this->rate($bagus, 5);
        $this->rate($buruk, 1);

        $this->assertSame('Artikel-'.$buruk->id, $this->sources()[0]['key']);
    }

    /** Seri rata-rata dipecah oleh jumlah penilai: yang lebih ramai lebih dulu. */
    public function test_nilai_seri_diurutkan_dari_yang_paling_banyak_dinilai(): void
    {
        $sepi = $this->article('Jarang dinilai');
        $ramai = $this->article('Sering dinilai');

        $this->rate($sepi, 2);
        $this->rate($ramai, 2);
        $this->rate($ramai, 2);

        $this->assertSame('Artikel-'.$ramai->id, $this->sources()[0]['key']);
    }

    // ---- tanggapan menempel pada materinya ---------------------------------

    public function test_tanggapan_menempel_pada_materi_yang_dinilai(): void
    {
        $article = $this->article('Panduan Printer');
        $this->rate($article, 2, 'Langkahnya tidak cocok dengan printer saya.');

        $row = $this->sourceFor('Artikel-'.$article->id);

        $this->assertCount(1, $row['comments']);
        $this->assertSame('Langkahnya tidak cocok dengan printer saya.', $row['comments'][0]['comment']);
    }

    /**
     * Inti perombakan ini. Artikel dan FAQ punya rentang id yang sama, jadi
     * pengelompokan yang hanya memakai id akan menempelkan keluhan atas FAQ ke
     * artikel bernomor sama — admin lalu menulis ulang materi yang tidak apa-apa.
     */
    public function test_tanggapan_tidak_bocor_antara_artikel_dan_faq_bernomor_sama(): void
    {
        $article = $this->article('Artikel bernomor sama');
        $faq = $this->faq('FAQ bernomor sama');

        $this->assertSame($article->id, $faq->id, 'prasyarat uji: keduanya harus bernomor sama');

        $this->rate($article, 2, 'Keluhan atas ARTIKEL.');
        $this->rate($faq, 2, 'Keluhan atas FAQ.');

        $this->assertSame(
            ['Keluhan atas ARTIKEL.'],
            array_column($this->sourceFor('Artikel-'.$article->id)['comments'], 'comment'),
        );
        $this->assertSame(
            ['Keluhan atas FAQ.'],
            array_column($this->sourceFor('FAQ-'.$faq->id)['comments'], 'comment'),
        );
    }

    /** Bintang tanpa kalimat bukan tanggapan — baris kosong tidak boleh muncul. */
    public function test_penilaian_tanpa_kalimat_tidak_dihitung_sebagai_tanggapan(): void
    {
        $article = $this->article('Dinilai tanpa komentar');
        $this->rate($article, 2);
        $this->rate($article, 1, '');

        $row = $this->sourceFor('Artikel-'.$article->id);

        $this->assertSame([], $row['comments']);
        $this->assertSame(2, $row['rating_count'], 'bintangnya tetap dihitung');
    }

    public function test_nama_penilai_anonim_saat_tidak_tercatat(): void
    {
        $article = $this->article('Dinilai tanpa nama');
        $this->rate($article, 2, 'Kurang jelas.');

        $this->assertSame('Anonim', $this->sourceFor('Artikel-'.$article->id)['comments'][0]['rater_name']);
    }

    public function test_nama_penilai_ikut_saat_tercatat(): void
    {
        $user = User::factory()->create(['name' => 'Andi Pratama']);
        $article = $this->article('Dinilai bernama');

        $this->rate($article, 2, 'Kurang jelas.')->update(['rated_by' => $user->id]);

        $this->assertSame('Andi Pratama', $this->sourceFor('Artikel-'.$article->id)['comments'][0]['rater_name']);
    }

    // ---- jalan keluar ------------------------------------------------------

    /**
     * Tiap baris membawa alamat layar tempat materinya diperbaiki. Tanpa ini
     * layar cuma memberi vonis lalu menyuruh admin mencari sendiri.
     */
    public function test_setiap_baris_membawa_alamat_layar_pengelolanya(): void
    {
        $article = $this->article('Artikel A');
        $faq = $this->faq('FAQ B');
        $this->rate($article, 3);
        $this->rate($faq, 3);

        $this->assertSame(route('eva.articles'), $this->sourceFor('Artikel-'.$article->id)['manage_url']);
        $this->assertSame(route('eva.faq'), $this->sourceFor('FAQ-'.$faq->id)['manage_url']);
    }

    // ---- angka -------------------------------------------------------------

    public function test_rata_rata_dan_persen_membantu_dihitung_dari_bintang_yang_masuk(): void
    {
        $article = $this->article('Dihitung');

        $this->rate($article, 5);
        $this->rate($article, 4);
        $this->rate($article, 1);

        $row = $this->sourceFor('Artikel-'.$article->id);

        $this->assertSame(3.3, $row['avg']);
        $this->assertSame(3, $row['rating_count']);
        $this->assertSame(67, $row['helpful_percent'], AnswerRating::HELPFUL_THRESHOLD.' bintang ke atas = membantu');
    }

    /**
     * Materi yang sudah dihapus tidak boleh muncul sebagai baris tanpa judul.
     * Logging jawabannya tetap ada — itu memang catatan kejadian.
     */
    public function test_materi_yang_sudah_dihapus_tidak_muncul(): void
    {
        $article = $this->article('Akan dihapus');
        $this->rate($article, 1, 'Buruk.');
        $article->delete();

        $this->assertSame([], $this->sources());
    }

    // ---- daftar global -----------------------------------------------------

    /** Panel kanan saat belum ada materi dipilih tetap menampilkan yang terbaru. */
    public function test_tanggapan_terbaru_tetap_tersedia_sebagai_daftar_global(): void
    {
        $article = $this->article('Materi');
        $this->rate($article, 2, 'Tanggapan lama.');
        $this->rate($article, 1, 'Tanggapan baru.');

        $comments = $this->get('/eva/ratings')->assertOk()->viewData('comments');

        $this->assertSame(
            ['Tanggapan baru.', 'Tanggapan lama.'],
            array_column($comments, 'comment'),
            'terbaru di atas',
        );
    }
}
