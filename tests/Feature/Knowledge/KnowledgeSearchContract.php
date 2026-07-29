<?php

namespace Tests\Feature\Knowledge;

use App\Models\Knowledge\Article;
use App\Models\Knowledge\Faq;
use App\Services\Knowledge\AnswerSourceSettings;
use App\Services\Knowledge\KnowledgeSearch;
use App\Services\Knowledge\SearchHit;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Perilaku yang WAJIB dipenuhi setiap implementasi Pencarian A.
 *
 * Kelas ini abstrak dan tidak dijalankan sendiri: subkelas menyediakan
 * lingkungannya (database, binari, layanan) lewat `searchUnderTest()`.
 *
 * KENAPA KONTRAK, BUKAN TES SATU KELAS. Rencananya `FulltextKnowledgeSearch`
 * akan ditukar dengan mesin berbasis embedding (lihat "Prasyarat sebelum model
 * AI ditanam" di docs/eva-status.md). Tes yang menempel pada kelas konkret tidak
 * ikut menguji penggantinya — padahal justru MEMBANDINGKAN dua mesin yang jadi
 * tujuannya. Ditulis sebagai kontrak, mesin baru cukup menambah satu subkelas
 * dan seluruh perilaku di bawah langsung berlaku untuknya.
 *
 * Yang BOLEH ada di sini hanya perilaku yang harus benar apa pun mesinnya.
 * Segala yang khas satu mesin — MATCH…AGAINST, fallback LIKE, jumlah query SQL —
 * milik subkelasnya, bukan kontrak ini.
 */
abstract class KnowledgeSearchContract extends TestCase
{
    /** Implementasi yang sedang diuji, sudah siap pakai. */
    abstract protected function searchUnderTest(): KnowledgeSearch;

    protected function article(array $attributes = []): Article
    {
        return Article::create(array_merge([
            'title' => 'SOP Reset Password SAP',
            'summary' => 'Cara mengatur ulang kata sandi SAP yang kedaluwarsa.',
            'body' => 'Langkah pertama membuka aplikasi SAP GUI lalu memilih menu ubah password.',
            'status' => Article::STATUS_PUBLISHED,
            'is_eva_visible' => true,
        ], $attributes));
    }

    protected function faq(array $attributes = []): Faq
    {
        return Faq::create(array_merge([
            'question' => 'Bagaimana cara mengaktifkan VPN FortiClient?',
            'answer' => 'Ajukan permintaan aktivasi VPN melalui Helpdesk dengan menyertakan NIK.',
            'is_eva_visible' => true,
        ], $attributes));
    }

    /**
     * Penanda hit, LENGKAP dengan tipe sumbernya.
     *
     * Id artikel dan id FAQ adalah dua urutan yang terpisah — keduanya bisa
     * bernilai 4. Membandingkan id telanjang membuat FAQ terlihat seperti
     * artikel, dan tes gerbang sumber jawaban lulus/gagal karena alasan yang
     * sama sekali salah. (Ini bukan kekhawatiran teoretis: cacat itu benar-benar
     * terjadi saat tes ini pertama ditulis.)
     *
     * @param  SearchHit[]  $hits
     * @return string[]
     */
    protected function keys(array $hits): array
    {
        return array_map(fn (SearchHit $hit) => class_basename($hit->sourceType).'#'.$hit->sourceId, $hits);
    }

    protected function key(Article|Faq $model): string
    {
        return class_basename($model::class).'#'.$model->id;
    }

    // ---- menemukan ---------------------------------------------------------

    /**
     * Kata yang HANYA ada di body, bukan di judul maupun ringkasan. Mesin yang
     * cuma mencocokkan judul akan lolos semua tes lain di sini kecuali yang ini.
     */
    public function test_menemukan_artikel_dari_kata_yang_hanya_ada_di_body(): void
    {
        $article = $this->article([
            'title' => 'Prosedur Akun',
            'summary' => 'Ringkasan singkat.',
            'body' => 'Gunakan transaksi SU01 untuk membuka kunci akun yang terblokir.',
        ]);

        $hits = $this->searchUnderTest()->cari('transaksi SU01');

        $this->assertContains($this->key($article), $this->keys($hits));
    }

    public function test_faq_ikut_dicari(): void
    {
        $faq = $this->faq();

        $hits = $this->searchUnderTest()->cari('aktivasi VPN FortiClient');

        $this->assertContains($this->key($faq), $this->keys($hits));
    }

    /**
     * Pertanyaan memakai bentuk berimbuhan ("mengaktifkan") sementara dokumennya
     * memakai bentuk lain. Setiap mesin wajib menjembatani ini — dengan cara
     * apa pun. FulltextKnowledgeSearch memakai pelucutan imbuhan + fallback
     * LIKE; mesin embedding akan menyelesaikannya secara semantik. Yang dikunci
     * kontrak adalah HASILNYA, bukan caranya.
     */
    public function test_menjembatani_bentuk_kata_yang_berbeda(): void
    {
        $faq = $this->faq([
            'question' => 'Bagaimana cara mengaktifkan lisensi Autocad?',
            'answer' => 'Kirim permintaan lisensi Autocad ke tim Helpdesk.',
        ]);

        $hits = $this->searchUnderTest()->cari('mengaktifkan');

        $this->assertContains($this->key($faq), $this->keys($hits));
    }

    // ---- gerbang -----------------------------------------------------------

    /** Artikel draf tidak pernah dipakai menjawab — gerbang answerable(). */
    public function test_artikel_draf_tidak_pernah_muncul(): void
    {
        $this->article(['status' => Article::STATUS_DRAFT]);

        $this->assertSame([], $this->searchUnderTest()->cari('reset password SAP'));
    }

    public function test_artikel_yang_disembunyikan_tidak_muncul(): void
    {
        $this->article(['is_eva_visible' => false]);

        $this->assertSame([], $this->searchUnderTest()->cari('reset password SAP'));
    }

    public function test_faq_yang_disembunyikan_tidak_muncul(): void
    {
        $this->faq(['is_eva_visible' => false]);

        $this->assertSame([], $this->searchUnderTest()->cari('aktivasi VPN FortiClient'));
    }

    /**
     * Sakelar sumber jawaban di Training Overview harus terasa di pencarian
     * BERIKUTNYA, bukan setelah proses di-restart — itu yang membuatnya nyata,
     * bukan hiasan. Mesin yang membaca pengaturan sekali di konstruktor akan
     * gagal di sini, dan memang seharusnya.
     */
    public function test_mematikan_sumber_artikel_langsung_terasa(): void
    {
        $article = $this->article();
        $faq = $this->faq(['question' => 'Bagaimana reset password SAP?', 'answer' => 'Hubungi Helpdesk.']);

        $sebelum = $this->keys($this->searchUnderTest()->cari('reset password SAP'));
        $this->assertContains($this->key($article), $sebelum);

        $this->app->make(AnswerSourceSettings::class)->set('articles', false);

        $sesudah = $this->keys($this->searchUnderTest()->cari('reset password SAP'));
        $this->assertNotContains($this->key($article), $sesudah);
        $this->assertContains($this->key($faq), $sesudah, 'FAQ tetap menjawab saat artikel dimatikan');
    }

    public function test_mematikan_sumber_faq_langsung_terasa(): void
    {
        $faq = $this->faq();

        $this->app->make(AnswerSourceSettings::class)->set('faqs', false);

        $this->assertNotContains(
            $this->key($faq),
            $this->keys($this->searchUnderTest()->cari('aktivasi VPN FortiClient')),
        );
    }

    // ---- bentuk hasil ------------------------------------------------------

    public function test_hasil_diurutkan_dari_keyakinan_tertinggi(): void
    {
        $this->article(['title' => 'Catatan Rapat', 'summary' => 'Menyinggung password sekilas.', 'body' => 'Agenda lain.']);
        $tepat = $this->article();

        $hits = $this->searchUnderTest()->cari('reset password SAP');

        $this->assertNotEmpty($hits);
        $this->assertSame($this->key($tepat), $this->keys($hits)[0], 'yang paling cocok harus di puncak');

        $confidences = array_map(fn (SearchHit $h) => $h->confidence, $hits);
        $urut = $confidences;
        rsort($urut);
        $this->assertSame($urut, $confidences);
    }

    public function test_batas_jumlah_hasil_dihormati(): void
    {
        foreach (range(1, 4) as $i) {
            $this->article(['title' => "SOP Reset Password SAP bagian {$i}"]);
        }

        $this->assertCount(2, $this->searchUnderTest()->cari('reset password SAP', limit: 2));
    }

    /**
     * Pertanyaan tanpa satu pun kata bermakna mengembalikan kosong — BUKAN
     * seluruh isi KB, dan bukan pula hasil acak berkeyakinan rendah.
     */
    public function test_pertanyaan_tanpa_kata_bermakna_mengembalikan_kosong(): void
    {
        $this->article();

        $this->assertSame([], $this->searchUnderTest()->cari('yang dan atau'));
    }

    /** SearchHit membawa subject katalog — itu yang dicatat ke kb_answer_logs. */
    public function test_hit_membawa_subject_katalog(): void
    {
        $subjectId = DB::table('service_catalog_subjects')->value('id');
        $article = $this->article(['catalog_subject_id' => $subjectId]);

        $hits = $this->searchUnderTest()->cari('reset password SAP');

        $hit = collect($hits)->first(
            fn (SearchHit $h) => $h->sourceType === Article::class && $h->sourceId === $article->id,
        );

        $this->assertNotNull($hit);
        $this->assertSame($subjectId, $hit->catalogSubjectId);
    }
}
