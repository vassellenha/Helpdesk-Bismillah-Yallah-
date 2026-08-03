<?php

declare(strict_types=1);

namespace Tests\Feature\Eva;

use App\Models\Knowledge\AnswerLog;
use App\Models\Knowledge\Conversation;
use App\Models\Knowledge\ConversationTurn;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

/**
 * Penyapu log EVA yang sudah lewat masa simpan.
 *
 * Perintah ini MENGHAPUS PERMANEN, jadi yang paling dijaga di sini bukan
 * "apakah yang tua terhapus" melainkan "apakah yang tidak diminta ikut
 * terhapus". Tiga batas yang harus tetap berdiri:
 *
 *   1. Log yang TERJAWAB tidak boleh ikut disapu betapapun tuanya. Yang diminta
 *      hanya pertanyaan tak terjawab; log terjawab adalah penyebut Analytics —
 *      menghapusnya membuat angka deflection melompat naik tanpa sebab.
 *   2. Menghapus percakapan tidak boleh menyeret answer log-nya. Kolom
 *      conversation_id memang nullOnDelete, dan tes ini yang menjaga supaya
 *      seseorang tidak diam-diam mengubahnya jadi cascade.
 *   3. Yang masih di dalam masa simpan tidak boleh tersentuh sama sekali.
 */
final class PurgeExpiredLogsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('eva.log_retention_days', 14);
    }

    public function test_percakapan_lewat_masa_simpan_dihapus_beserta_turnnya(): void
    {
        $lama = $this->percakapan(20);
        $this->turn($lama);

        $this->artisan('eva:purge-expired-logs')->assertSuccessful();

        $this->assertSame(0, Conversation::count());
        // Turn ikut hilang lewat cascade di tingkat basis data, bukan lewat
        // penghapusan manual — kalau constraint-nya dicabut, tes ini gagal.
        $this->assertSame(0, ConversationTurn::count());
    }

    public function test_percakapan_yang_masih_di_dalam_masa_simpan_tidak_tersentuh(): void
    {
        $baru = $this->percakapan(13);
        $this->turn($baru);

        $this->artisan('eva:purge-expired-logs')->assertSuccessful();

        $this->assertSame(1, Conversation::count());
        $this->assertSame(1, ConversationTurn::count());
    }

    public function test_pertanyaan_tak_terjawab_yang_tua_dihapus(): void
    {
        $this->log(AnswerLog::OUTCOME_NO_ANSWER, 20);
        $this->log(AnswerLog::OUTCOME_TICKET_DRAFT, 20);
        $this->log(AnswerLog::OUTCOME_NO_ANSWER, 3);

        $this->artisan('eva:purge-expired-logs')->assertSuccessful();

        $this->assertSame(1, AnswerLog::count());
        $this->assertTrue(AnswerLog::sole()->created_at->greaterThan(now()->subDays(14)));
    }

    public function test_log_terjawab_tidak_ikut_disapu_betapapun_tuanya(): void
    {
        $this->log(AnswerLog::OUTCOME_ANSWERED, 400);
        $this->log(AnswerLog::OUTCOME_CLARIFY, 400);

        $this->artisan('eva:purge-expired-logs')->assertSuccessful();

        $this->assertSame(2, AnswerLog::count());
    }

    public function test_menghapus_percakapan_tidak_menyeret_answer_lognya(): void
    {
        $lama = $this->percakapan(20);
        $this->log(AnswerLog::OUTCOME_ANSWERED, 20, $lama);

        $this->artisan('eva:purge-expired-logs')->assertSuccessful();

        $this->assertSame(0, Conversation::count());
        $log = AnswerLog::sole();
        $this->assertNull($log->conversation_id);
    }

    public function test_masa_simpan_mengikuti_config_bukan_angka_yang_ditanam(): void
    {
        Config::set('eva.log_retention_days', 60);
        $this->percakapan(30);
        $this->log(AnswerLog::OUTCOME_NO_ANSWER, 30);

        $this->artisan('eva:purge-expired-logs')->assertSuccessful();

        $this->assertSame(1, Conversation::count());
        $this->assertSame(1, AnswerLog::count());
    }

    private function percakapan(int $umurHari): Conversation
    {
        return Conversation::create([
            'requester_name' => 'Andi Pratama',
            'outcome' => Conversation::OUTCOME_ANSWERED,
            'started_at' => now()->subDays($umurHari),
            'created_at' => now()->subDays($umurHari),
            'updated_at' => now()->subDays($umurHari),
        ]);
    }

    private function turn(Conversation $conversation): ConversationTurn
    {
        return $conversation->turns()->create([
            'ordinal' => 0,
            'role' => 'user',
            'message' => 'printer lantai 7 macet',
        ]);
    }

    /**
     * created_at ditulis SETELAH baris terbentuk, bukan lewat create().
     *
     * Kolom itu tidak `fillable` di AnswerLog, jadi kalau dititipkan di array
     * create() ia diabaikan tanpa suara dan barisnya lahir bertanggal hari ini
     * — tesnya lalu hijau/merah karena alasan yang salah.
     */
    private function log(string $outcome, int $umurHari, ?Conversation $conversation = null): AnswerLog
    {
        $log = AnswerLog::create([
            'conversation_id' => $conversation?->id,
            'question' => 'printer lantai 7 macet',
            'confidence' => 0,
            'outcome' => $outcome,
        ]);

        $log->created_at = now()->subDays($umurHari);
        $log->save();

        return $log;
    }
}
