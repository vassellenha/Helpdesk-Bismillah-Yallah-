<?php

namespace Tests\Feature\Eva;

use App\Models\Knowledge\AnswerLog;
use App\Models\Knowledge\DismissedQuestion;
use App\Models\User;
use App\Services\Knowledge\KnowledgeSearch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Concerns\ActsAsEvaAdmin;
use Tests\TestCase;

/**
 * Menyingkirkan pertanyaan dari daftar kerja Unanswered Questions.
 *
 * Sebelum ini tidak ada cara menyingkirkan apa pun. Pertanyaan yang materinya
 * sudah ditulis memang hilang sendiri (layar ini memeriksa ulang tiap dibuka),
 * tetapi pertanyaan yang MEMANG TIDAK AKAN PERNAH dijawab materi — salah ketik,
 * sapaan, permintaan pribadi — menumpuk selamanya dan mendorong pekerjaan nyata
 * ke luar batas 40 baris.
 *
 * Tiga hal yang dikunci di sini:
 *
 *  1. **Log jawabannya TIDAK dihapus.** `kb_answer_logs` adalah catatan
 *     kejadian; menghapusnya berarti mengubah angka Analytics dan deflection
 *     rate bulan lalu — memalsukan masa lalu demi merapikan daftar hari ini.
 *  2. **Keputusan bisa kedaluwarsa.** Pertanyaan yang ditanyakan LAGI sesudah
 *     disingkirkan muncul kembali. Itu bukti baru, dan daftar kerja yang
 *     membungkam bukti baru diam-diam berhenti berguna.
 *  3. **Penyaringan terjadi di SQL.** Kalau baris yang disingkirkan tetap
 *     memakan jatah 40, daftar kerja mengecil sendiri tanpa alasan yang
 *     terlihat di layar.
 */
final class DismissQuestionTest extends TestCase
{
    use ActsAsEvaAdmin;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        User::factory()->create(['name' => 'Marcell Laforteza', 'email' => 'marcell.laforteza@adhi.co.id']);

        // Pencarian A dipalsukan supaya tes jalan di SQLite dan setiap
        // pertanyaan pasti dianggap "masih celah" — yang diuji di sini
        // penyingkirannya, bukan mesin pencarinya.
        $this->app->instance(KnowledgeSearch::class, new class implements KnowledgeSearch
        {
            public function cari(string $pertanyaan, int $limit = 5): array
            {
                return [];
            }
        });

        $this->actingAsEvaAdmin();
    }

    private function tanya(string $question, ?string $at = null): AnswerLog
    {
        $log = AnswerLog::create([
            'question' => $question,
            'outcome' => AnswerLog::OUTCOME_NO_ANSWER,
            'confidence' => 0,
        ]);

        if ($at !== null) {
            $log->forceFill(['created_at' => $at])->save();
        }

        return $log;
    }

    /** @return string[] pertanyaan yang tampil di daftar kerja */
    private function daftarKerja(): array
    {
        return array_column($this->get('/eva/unanswered')->assertOk()->viewData('gaps'), 'question');
    }

    private function singkirkan(string $question)
    {
        return $this->postJson('/eva/api/unanswered/dismiss', ['question' => $question]);
    }

    // ---- menyingkirkan -----------------------------------------------------

    public function test_pertanyaan_yang_disingkirkan_hilang_dari_daftar_kerja(): void
    {
        $this->tanya('halo');
        $this->tanya('cara pakai vpn untuk wfh');

        $this->assertCount(2, $this->daftarKerja());

        $this->singkirkan('halo')->assertOk()->assertJsonPath('question', 'halo');

        $this->assertSame(['cara pakai vpn untuk wfh'], $this->daftarKerja());
    }

    /**
     * Invarian terpenting: riwayatnya utuh. Kalau baris log ikut terhapus,
     * deflection rate bulan lalu berubah tanpa ada yang menyadarinya.
     */
    public function test_log_jawaban_tidak_ikut_terhapus(): void
    {
        $this->tanya('halo');
        $this->tanya('halo');

        $this->singkirkan('halo')->assertOk();

        $this->assertSame(2, AnswerLog::count());
        $this->assertSame(2, AnswerLog::unanswered()->count());
    }

    public function test_penyingkir_dan_waktunya_dicatat(): void
    {
        $this->tanya('cara pakai vpn untuk wfh');

        $this->singkirkan('cara pakai vpn untuk wfh')
            ->assertOk()
            ->assertJsonPath('dismissed_by_name', 'Marcell Laforteza');

        $this->assertNotNull(DismissedQuestion::sole()->dismissed_at);
    }

    /** Ditekan dua kali tidak menggandakan barisnya — satu keputusan per teks. */
    public function test_menyingkirkan_dua_kali_tetap_satu_baris(): void
    {
        $this->tanya('halo');

        $this->singkirkan('halo')->assertOk();
        $this->singkirkan('halo')->assertOk();

        $this->assertSame(1, DismissedQuestion::count());
    }

    public function test_pertanyaan_wajib_disebut(): void
    {
        $this->postJson('/eva/api/unanswered/dismiss', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('question');
    }

    // ---- kedaluwarsa -------------------------------------------------------

    /**
     * Inti rancangannya: keputusan menyingkirkan hanya berlaku selama
     * pertanyaannya tidak ditanyakan lagi. Ditanyakan lagi = bukti baru.
     */
    public function test_pertanyaan_yang_ditanyakan_lagi_muncul_kembali(): void
    {
        $this->tanya('halo', at: '2026-07-01 09:00:00');
        $this->singkirkan('halo')->assertOk();

        $this->assertSame([], $this->daftarKerja());

        $this->tanya('halo', at: now()->addMinute()->toDateTimeString());

        $this->assertSame(['halo'], $this->daftarKerja(), 'ditanyakan lagi = bukti baru');
    }

    /**
     * Yang sudah muncul kembali hanya boleh tampil SEKALI, di daftar kerja.
     *
     * Layar ini tidak lagi punya daftar "dihapus" — penghapusan berarti
     * barisnya hilang, titik. Yang tersisa untuk dijaga: baris yang kembali
     * tidak menggandakan dirinya sendiri.
     */
    public function test_yang_muncul_kembali_tampil_sekali_saja(): void
    {
        $this->tanya('halo', at: '2026-07-01 09:00:00');
        $this->singkirkan('halo')->assertOk();
        $this->tanya('halo', at: now()->addMinute()->toDateTimeString());

        $this->assertSame(['halo'], $this->daftarKerja());
    }

    // ---- mengembalikan -----------------------------------------------------

    public function test_keputusan_bisa_ditarik_kembali(): void
    {
        $this->tanya('cara pakai vpn untuk wfh');
        $this->singkirkan('cara pakai vpn untuk wfh')->assertOk();

        $this->assertSame([], $this->daftarKerja());

        $this->postJson('/eva/api/unanswered/restore', ['question' => 'cara pakai vpn untuk wfh'])
            ->assertOk()
            ->assertJsonPath('restored', true);

        $this->assertSame(['cara pakai vpn untuk wfh'], $this->daftarKerja());
        $this->assertSame(0, DismissedQuestion::count());
    }

    // ---- daftar ------------------------------------------------------------

    /**
     * Penghapusan tidak lagi ditampilkan di mana pun — barisnya benar-benar
     * hilang dari layar. Jejaknya tetap ada di DATA, dan itu yang menahan
     * pertanyaannya tidak muncul lagi sampai betul-betul ditanyakan ulang.
     */
    public function test_yang_disingkirkan_hilang_dari_layar_tapi_tercatat_di_data(): void
    {
        $this->tanya('halo');
        $this->singkirkan('halo')->assertOk();

        $layar = $this->get('/eva/unanswered')->assertOk();

        $this->assertSame([], $this->daftarKerja());
        $this->assertSame([], array_column($layar->viewData('closed'), 'question'));

        $tercatat = DismissedQuestion::where('question', 'halo')->sole();

        $this->assertSame('Marcell Laforteza', $tercatat->dismissedBy->name);
        $this->assertNotNull($tercatat->dismissed_at);
    }

    /**
     * Penyaringan harus terjadi di SQL: baris yang disingkirkan tidak boleh
     * memakan jatah batas 40, kalau tidak daftar kerja mengecil sendiri.
     */
    public function test_yang_disingkirkan_tidak_memakan_jatah_daftar(): void
    {
        foreach (range(1, 41) as $i) {
            $this->tanya("pertanyaan nomor {$i}");
        }

        $this->assertCount(40, $this->daftarKerja());

        $this->singkirkan('pertanyaan nomor 1')->assertOk();

        $this->assertCount(40, $this->daftarKerja(), 'baris ke-41 naik menggantikan yang disingkirkan');
    }
}
