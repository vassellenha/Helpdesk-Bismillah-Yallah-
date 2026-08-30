<?php

declare(strict_types=1);

namespace Tests\Feature\Eva;

use App\Services\Knowledge\KnowledgeSearch;
use App\Services\Knowledge\SearchHit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsRole;
use Tests\TestCase;

/**
 * Widget EVA mengirim FORM, bukan JSON — dan seluruh endpointnya harus tahan
 * terhadap itu.
 *
 * Bentuk kiriman itu bukan selera: proxy portal SINTA tidak meneruskan body
 * JSON maupun header X-CSRF-TOKEN, jadi `resources/js/lib/api.js` mengemas
 * semuanya sebagai `application/x-www-form-urlencoded`. Konsekuensinya mudah
 * terlewat: pada form, SETIAP nilai tiba sebagai STRING. `answer_log_id=306`
 * menjadi `"306"`, bukan `306`.
 *
 * Aturan `integer` di validator MELOLOSKAN string angka tanpa mengubahnya, dan
 * controller di sini memakai `declare(strict_types=1)`. Jadi begitu nilai itu
 * diteruskan ke parameter ber-tipe `int`, PHP melempar TypeError dan endpointnya
 * balas 500 — tanpa satu pun tes yang gagal, karena seluruh tes sebelumnya
 * memakai `postJson()` yang mengirim angka sungguhan.
 *
 * Berkas ini sengaja memakai `post()` (form) di setiap kasus. Itu satu-satunya
 * bentuk yang benar-benar dipakai widget di produksi.
 */
final class FormEncodedRequestTest extends TestCase
{
    use ActsAsRole, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAsRole('requester');

        // FULLTEXT tidak jalan di SQLite, dan isi jawabannya tidak relevan di
        // sini — yang diuji bentuk kiriman, bukan mutu pencarian.
        $this->instance(KnowledgeSearch::class, new class implements KnowledgeSearch
        {
            /** @return SearchHit[] */
            public function cari(string $pertanyaan, int $limit = 5): array
            {
                return [];
            }
        });
    }

    /** Satu pertanyaan lewat form, mengembalikan id log dan id percakapan. */
    private function bertanya(string $question, ?int $conversationId = null): array
    {
        $response = $this->post(route('eva.assistant.ask'), array_filter([
            'question' => $question,
            'conversation_id' => $conversationId,
        ]))->assertOk();

        return [(int) $response->json('answer_log_id'), (int) $response->json('conversation_id')];
    }

    public function test_pertanyaan_lanjutan_lewat_form_tidak_menjatuhkan_endpoint(): void
    {
        [, $conversationId] = $this->bertanya('printer lantai 7 macet');

        // Giliran kedua membawa conversation_id — di form ia tiba sebagai
        // string, dan inilah yang dulu menjatuhkan `ask()`.
        $this->post(route('eva.assistant.ask'), [
            'question' => 'masih belum bisa juga',
            'conversation_id' => (string) $conversationId,
        ])->assertOk();
    }

    public function test_draf_tiket_lewat_form_tidak_menjatuhkan_endpoint(): void
    {
        [$logId] = $this->bertanya('printer lantai 7 macet');

        $this->post(route('eva.assistant.ticket-draft'), [
            'answer_log_id' => (string) $logId,
            'question' => 'printer lantai 7 macet',
        ])->assertOk();

        $this->assertSame('printer lantai 7 macet', session('eva.ticket_draft.description'));
    }

    public function test_penilaian_lewat_form_tidak_menjatuhkan_endpoint(): void
    {
        [$logId] = $this->bertanya('printer lantai 7 macet');

        $this->post(route('eva.assistant.rate'), [
            'answer_log_id' => (string) $logId,
            'stars' => '4',
        ])->assertOk();
    }

    public function test_catatan_penilaian_lewat_form_tidak_menjatuhkan_endpoint(): void
    {
        [$logId] = $this->bertanya('printer lantai 7 macet');

        $this->post(route('eva.assistant.rate'), [
            'answer_log_id' => (string) $logId,
            'stars' => '2',
        ])->assertOk();

        $this->post(route('eva.assistant.note'), [
            'answer_log_id' => (string) $logId,
            'reason' => 'Jawaban tidak sesuai',
        ])->assertOk();
    }
}
