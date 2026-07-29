<?php

namespace Tests\Feature\Eva;

use App\Models\Knowledge\Faq;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Concerns\ActsAsEvaAdmin;
use Tests\TestCase;

/**
 * Tombol "Tulis FAQ" di Unanswered Questions membawa pertanyaannya.
 *
 * Sebelum ini tombol itu membuka Manage FAQ kosong, dan admin mengetik ulang
 * pertanyaan karyawan dari ingatan — cara paling mudah membuat FAQ yang
 * menjawab kalimat yang SEDIKIT BERBEDA dari yang sebenarnya diajukan.
 * Karena Pencarian A mencocokkan kata, beda sedikit itu berarti celahnya tidak
 * benar-benar tertutup, dan barisnya tetap muncul di Unanswered — sementara
 * admin merasa sudah menyelesaikannya.
 *
 * Yang dikunci: pertanyaannya sampai APA ADANYA, dan membuka layar dengan
 * pertanyaan bawaan tidak membuat FAQ apa pun (form baru terisi, belum
 * tersimpan).
 */
final class FaqPrefillTest extends TestCase
{
    use ActsAsEvaAdmin;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        $this->actingAsEvaAdmin();
    }

    public function test_pertanyaan_dibawa_apa_adanya_ke_layar_faq(): void
    {
        $question = 'cara pakai vpn untuk wfh';

        $this->get('/eva/faq?question='.urlencode($question))
            ->assertOk()
            ->assertViewHas('prefillQuestion', $question);
    }

    /** Tanda baca dan huruf besar tidak boleh berubah — ini kalimat orang lain. */
    public function test_tanda_baca_dan_huruf_tidak_diubah(): void
    {
        $question = 'Kenapa VPN saya "disconnect" terus saat WFH?';

        $this->get('/eva/faq?question='.urlencode($question))
            ->assertOk()
            ->assertViewHas('prefillQuestion', $question);
    }

    public function test_tanpa_query_string_form_tetap_kosong(): void
    {
        $this->get('/eva/faq')
            ->assertOk()
            ->assertViewHas('prefillQuestion', null);
    }

    /** Query kosong atau hanya spasi sama dengan tidak ada — bukan string kosong. */
    public function test_pertanyaan_kosong_dianggap_tidak_ada(): void
    {
        $this->get('/eva/faq?question=%20%20')
            ->assertOk()
            ->assertViewHas('prefillQuestion', null);
    }

    /**
     * Dipotong di batas validasi `question`. Tanpa ini, form terbuka dengan isi
     * yang PASTI ditolak saat Simpan ditekan — dan admin baru tahu setelah
     * mengetik jawabannya.
     */
    public function test_pertanyaan_kepanjangan_dipotong_di_batas_validasi(): void
    {
        $prefill = $this->get('/eva/faq?question='.urlencode(str_repeat('a', 600)))
            ->assertOk()
            ->viewData('prefillQuestion');

        $this->assertSame(500, mb_strlen($prefill));
    }

    /** Membuka layar bukan menyimpan. Formnya terisi, FAQ-nya belum ada. */
    public function test_membuka_layar_tidak_membuat_faq(): void
    {
        $this->get('/eva/faq?question=cara+pakai+vpn+untuk+wfh')->assertOk();

        $this->assertSame(0, Faq::count());
    }
}
