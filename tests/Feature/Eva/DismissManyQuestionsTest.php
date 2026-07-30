<?php

declare(strict_types=1);

namespace Tests\Feature\Eva;

use App\Models\Knowledge\AnswerLog;
use App\Models\Knowledge\DismissedQuestion;
use App\Models\User;
use App\Services\Knowledge\KnowledgeSearch;
use App\Services\Knowledge\SearchHit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Concerns\ActsAsEvaAdmin;
use Tests\TestCase;

/**
 * Menghapus SEKALIGUS dari daftar "Telah terjawab".
 *
 * Daftar itu tidak punya kolom status — isinya pertanyaan yang dulu gagal lalu
 * lulus pemeriksaan ulang saat halaman dibuka. Karena itu "hapus" di sana hanya
 * bisa berarti satu hal yang sama dengan daftar kerja di atasnya: mencatat
 * keputusan menyingkirkan, bukan menghapus log.
 *
 * Yang dikunci di sini adalah hal-hal yang kalau salah membuat tombol "Hapus
 * semua" berbahaya, bukan sekadar tidak rapi:
 *
 *  1. **`kb_answer_logs` tidak boleh tersentuh.** Menghapus 20 baris sekaligus
 *     adalah cara tercepat memalsukan angka Analytics bulan lalu tanpa sadar.
 *  2. **Satu permintaan, bukan 20.** Kalau layar mengirim satu permintaan per
 *     baris, kegagalan di tengah meninggalkan separuh terhapus tanpa ada yang
 *     tahu baris mana.
 *  3. **Daftar kosong ditolak.** Permintaan tanpa isi yang dijawab 200 membuat
 *     galat di klien terbaca sebagai keberhasilan.
 */
final class DismissManyQuestionsTest extends TestCase
{
    use ActsAsEvaAdmin;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        User::factory()->create(['name' => 'Marcell Laforteza', 'email' => 'marcell.laforteza@adhi.co.id']);

        // Pencarian dipalsukan supaya SETIAP pertanyaan lulus pemeriksaan ulang
        // — itulah yang membuatnya mendarat di daftar "Telah terjawab", yang
        // memang jadi sasaran tombol ini.
        $this->app->instance(KnowledgeSearch::class, new class implements KnowledgeSearch
        {
            public function cari(string $pertanyaan, int $limit = 5): array
            {
                return [new SearchHit('Artikel', 1, 'SOP Reset Password SAP', 'isi', 95, null)];
            }
        });

        $this->actingAsEvaAdmin();
    }

    private function tanya(string $question): void
    {
        AnswerLog::create([
            'question' => $question,
            'outcome' => AnswerLog::OUTCOME_NO_ANSWER,
            'confidence' => 0,
        ]);
    }

    /** @return string[] pertanyaan pada daftar "Telah terjawab" */
    private function daftarTerjawab(): array
    {
        return array_column($this->get('/eva/unanswered')->assertOk()->viewData('closed'), 'question');
    }

    public function test_menghapus_beberapa_pertanyaan_sekaligus(): void
    {
        $this->tanya('cara reset password sap');
        $this->tanya('bagaimana unlock akun');
        $this->tanya('cara pakai vpn');

        $this->assertCount(3, $this->daftarTerjawab());

        $this->postJson('/eva/api/unanswered/dismiss-many', [
            'questions' => ['cara reset password sap', 'bagaimana unlock akun'],
        ])->assertOk()->assertJson(['dismissed' => 2]);

        $this->assertSame(['cara pakai vpn'], $this->daftarTerjawab());
    }

    public function test_log_jawaban_tidak_ikut_terhapus(): void
    {
        $this->tanya('cara reset password sap');
        $this->tanya('bagaimana unlock akun');

        $this->postJson('/eva/api/unanswered/dismiss-many', [
            'questions' => ['cara reset password sap', 'bagaimana unlock akun'],
        ])->assertOk();

        $this->assertSame(2, AnswerLog::count());
        $this->assertSame(2, DismissedQuestion::count());
    }

    public function test_menekan_dua_kali_tidak_menggandakan_catatan(): void
    {
        $this->tanya('cara reset password sap');

        $muatan = ['questions' => ['cara reset password sap']];

        $this->postJson('/eva/api/unanswered/dismiss-many', $muatan)->assertOk();
        $this->postJson('/eva/api/unanswered/dismiss-many', $muatan)->assertOk();

        $this->assertSame(1, DismissedQuestion::count());
    }

    public function test_daftar_kosong_ditolak(): void
    {
        $this->postJson('/eva/api/unanswered/dismiss-many', ['questions' => []])
            ->assertStatus(422);

        $this->postJson('/eva/api/unanswered/dismiss-many', [])
            ->assertStatus(422);
    }
}
