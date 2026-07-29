<?php

namespace Tests\Feature\Eva;

use App\Models\Knowledge\Conversation;
use App\Models\Knowledge\ConversationTurn;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Concerns\ActsAsEvaAdmin;
use Tests\TestCase;

/**
 * Log Percakapan — membaca kalimat aslinya.
 *
 * Controller ini sebelumnya nol tes, dan isinya bukan sekadar meneruskan kolom:
 * judul baris DITURUNKAN dari giliran pertama pengguna, keyakinan diambil dari
 * giliran TERTINGGI, dan urutan giliran menentukan apakah percakapan terbaca
 * masuk akal atau jadi potongan acak.
 *
 * Yang dikunci di sini adalah turunan-turunan itu — bagian yang diam-diam salah
 * tanpa satu pun error, karena layarnya tetap tampak berisi.
 */
final class ConversationLogTest extends TestCase
{
    use ActsAsEvaAdmin;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        $this->actingAsEvaAdmin();
    }

    private function conversation(array $attributes = []): Conversation
    {
        return Conversation::create(array_merge([
            'requester_name' => 'Andi Pratama',
            'department' => 'Keuangan',
            'outcome' => Conversation::OUTCOME_ANSWERED,
            'started_at' => now()->subHour(),
        ], $attributes));
    }

    private function turn(Conversation $conversation, int $ordinal, string $role, string $message, array $extra = []): ConversationTurn
    {
        return ConversationTurn::create(array_merge([
            'conversation_id' => $conversation->id,
            'ordinal' => $ordinal,
            'role' => $role,
            'message' => $message,
        ], $extra));
    }

    /** @return array<int,array<string,mixed>> */
    private function rows(): array
    {
        return $this->get('/eva/conversations')->assertOk()->viewData('conversations')->all();
    }

    // ---- layar -------------------------------------------------------------

    public function test_halaman_tampil_walau_belum_ada_percakapan(): void
    {
        $this->get('/eva/conversations')
            ->assertOk()
            ->assertViewHas('showing', 0);
    }

    public function test_kartu_statistik_dihitung_per_hasil(): void
    {
        $this->conversation();
        $this->conversation(['outcome' => Conversation::OUTCOME_TICKET]);
        $this->conversation(['outcome' => Conversation::OUTCOME_ABANDONED]);

        $stats = $this->get('/eva/conversations')->assertOk()->viewData('stats');

        $this->assertSame(3, $stats['total']);
        $this->assertSame(1, $stats['answered']);
        $this->assertSame(1, $stats['ticket']);
        $this->assertSame(1, $stats['abandoned']);
    }

    // ---- turunan -----------------------------------------------------------

    /**
     * Judul baris = pertanyaan pembuka, yaitu giliran PENGGUNA pertama. Kalau
     * yang terambil giliran EVA, seluruh daftar berjudul kalimat sapaan robot
     * dan tidak ada yang bisa ditelusuri.
     */
    public function test_judul_baris_diambil_dari_pertanyaan_pengguna_pertama(): void
    {
        $conversation = $this->conversation();
        $this->turn($conversation, 1, ConversationTurn::ROLE_EVA, 'Halo, ada yang bisa dibantu?');
        $this->turn($conversation, 2, ConversationTurn::ROLE_USER, 'Kenapa VPN saya putus terus?');
        $this->turn($conversation, 3, ConversationTurn::ROLE_USER, 'Sudah saya coba ulang.');

        $this->assertSame('Kenapa VPN saya putus terus?', $this->rows()[0]['opening_question']);
    }

    public function test_percakapan_tanpa_giliran_pengguna_tidak_membuat_layar_meledak(): void
    {
        $conversation = $this->conversation();
        $this->turn($conversation, 1, ConversationTurn::ROLE_EVA, 'Halo.');

        $this->assertSame('—', $this->rows()[0]['opening_question']);
    }

    /** Keyakinan yang ditampilkan adalah yang TERTINGGI, bukan yang terakhir. */
    public function test_keyakinan_diambil_dari_giliran_tertinggi(): void
    {
        $conversation = $this->conversation();
        $this->turn($conversation, 1, ConversationTurn::ROLE_USER, 'reset password sap');
        $this->turn($conversation, 2, ConversationTurn::ROLE_EVA, 'Begini caranya.', ['confidence' => 88]);
        $this->turn($conversation, 3, ConversationTurn::ROLE_EVA, 'Ada lagi?', ['confidence' => 12]);

        $this->assertSame(88, $this->rows()[0]['confidence']);
    }

    /**
     * Urutan giliran mengikuti `ordinal`, bukan urutan penyimpanan. Percakapan
     * yang terbaca terbalik menyesatkan lebih parah daripada tidak terbaca.
     */
    public function test_giliran_terurut_menurut_ordinal_bukan_urutan_simpan(): void
    {
        $conversation = $this->conversation();
        $this->turn($conversation, 3, ConversationTurn::ROLE_EVA, 'Ketiga.');
        $this->turn($conversation, 1, ConversationTurn::ROLE_USER, 'Pertama.');
        $this->turn($conversation, 2, ConversationTurn::ROLE_EVA, 'Kedua.');

        $this->assertSame(
            ['Pertama.', 'Kedua.', 'Ketiga.'],
            array_column($this->rows()[0]['turns'], 'message'),
        );
    }

    public function test_jumlah_giliran_dihitung_dari_giliran_nyata(): void
    {
        $conversation = $this->conversation();
        $this->turn($conversation, 1, ConversationTurn::ROLE_USER, 'Halo.');
        $this->turn($conversation, 2, ConversationTurn::ROLE_EVA, 'Halo juga.');

        $this->assertSame(2, $this->rows()[0]['turn_count']);
    }

    // ---- identitas ---------------------------------------------------------

    public function test_nama_penanya_jatuh_ke_akun_lalu_ke_tanpa_nama(): void
    {
        $user = User::factory()->create(['name' => 'Budi Santoso']);

        $this->conversation(['requester_name' => null, 'user_id' => $user->id, 'started_at' => now()->subMinutes(10)]);
        $this->conversation(['requester_name' => null, 'user_id' => null, 'started_at' => now()->subMinutes(20)]);

        $this->assertSame(
            ['Budi Santoso', 'Tanpa nama'],
            array_column($this->rows(), 'requester_name'),
        );
    }

    /**
     * Nomor tiket menyambungkan percakapan yang gagal ke tiket yang lahir
     * darinya. Sempat dikirim controller tapi tidak pernah ditampilkan layar —
     * tes ini menjaga jalurnya tetap ada setelah layar dirombak.
     */
    public function test_nomor_tiket_ikut_terkirim_ke_layar(): void
    {
        $this->conversation([
            'outcome' => Conversation::OUTCOME_TICKET,
            'ticket_reference' => 'TKT-2026-0042',
        ]);

        $this->assertSame('TKT-2026-0042', $this->rows()[0]['ticket_reference']);
    }

    // ---- urutan ------------------------------------------------------------

    /** Terbaru di atas — log yang dibaca orang selalu dari kejadian terakhir. */
    public function test_percakapan_terbaru_berada_di_atas(): void
    {
        $this->conversation(['requester_name' => 'Lama', 'started_at' => now()->subDays(3)]);
        $this->conversation(['requester_name' => 'Baru', 'started_at' => now()->subMinutes(5)]);

        $this->assertSame(['Baru', 'Lama'], array_column($this->rows(), 'requester_name'));
    }
}
