<?php

declare(strict_types=1);

namespace Tests\Feature\Ticket;

use App\Models\ServiceCatalogService;
use App\Models\SlaPolicy;
use App\Models\Ticket;
use App\Support\PriorityRegistry;
use PHPUnit\Framework\Attributes\DataProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsRole;
use Tests\TestCase;

/**
 * Kelengkapan data wajib pada pembuatan tiket — UAT test case 7 (FR-R05).
 *
 * Layar Requester memang sudah mencegah pengiriman yang belum lengkap, tapi
 * pencegahan di layar bukan validasi: siapa pun yang memanggil endpoint-nya
 * langsung melewatinya. Berkas ini mengunci aturannya di sisi server, tempat
 * ia tidak bisa dilangkahi.
 *
 * Draf sengaja dikecualikan. Draf ADALAH pekerjaan setengah jadi — mewajibkan
 * kelengkapan di situ akan menghapus gunanya.
 */
final class TicketRequiredFieldValidationTest extends TestCase
{
    use ActsAsRole;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        PriorityRegistry::flush();
    }

    private function policy(): SlaPolicy
    {
        return SlaPolicy::create([
            'policy_name' => 'Low Standard',
            'priority' => 'Low',
            'response_time_minutes' => 1440,
            'resolution_time_minutes' => 7200,
            'escalation_extension_minutes' => 120,
            'warning_threshold_percent' => 80,
            'status' => 'active',
        ]);
    }

    private function service(): ServiceCatalogService
    {
        return ServiceCatalogService::create([
            'name' => 'SAP',
            'is_active' => true,
        ]);
    }

    /** Muatan yang lengkap — dipakai sebagai titik awal, lalu dilubangi per tes. */
    private function completePayload(): array
    {
        return [
            'title' => 'Tidak bisa masuk SAP',
            'sla_policy_id' => $this->policy()->id,
            'service_id' => $this->service()->id,
            'service_name' => 'SAP',
            'subcategory_name' => 'LOGIN SAP',
            'subject_name' => 'Tidak bisa login',
            'issue_category' => 'Incident',
            'description' => 'Kredensial ditolak sejak pagi.',
        ];
    }

    public function test_kiriman_kosong_ditolak_dan_menyebut_semua_kolom_wajib(): void
    {
        $this->actingAsRole('requester');

        $this->postJson('/api/tickets', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'title',
                'sla_policy_id',
                'service_name',
                'subcategory_name',
                'subject_name',
                'issue_category',
            ]);

        $this->assertSame(0, Ticket::count());
    }

    public function test_pesan_galat_terbaca_manusia_bukan_kunci_terjemahan(): void
    {
        $this->actingAsRole('requester');

        $errors = $this->postJson('/api/tickets', [])
            ->assertStatus(422)
            ->json('errors');

        foreach ($errors as $field => $messages) {
            foreach ($messages as $message) {
                $this->assertStringNotContainsString(
                    'validation.',
                    $message,
                    "Pesan untuk '{$field}' masih berupa kunci terjemahan mentah: {$message}",
                );
            }
        }
    }

    #[DataProvider('kolomWajib')]
    public function test_kolom_wajib_yang_dikosongkan_menahan_pembuatan_tiket(string $field): void
    {
        $this->actingAsRole('requester');

        $payload = $this->completePayload();
        unset($payload[$field]);

        $this->postJson('/api/tickets', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors([$field]);

        $this->assertSame(0, Ticket::count());
    }

    public static function kolomWajib(): array
    {
        return [
            'tanpa layanan' => ['service_name'],
            'tanpa sub kategori' => ['subcategory_name'],
            'tanpa subjek' => ['subject_name'],
            'tanpa kategori masalah' => ['issue_category'],
            'tanpa judul' => ['title'],
            'tanpa prioritas' => ['sla_policy_id'],
        ];
    }

    public function test_approver_wajib_ketika_tiket_butuh_persetujuan(): void
    {
        $this->actingAsRole('requester');

        $payload = $this->completePayload();
        $payload['requires_approval'] = true;

        $this->postJson('/api/tickets', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['approver_id']);

        $this->assertSame(0, Ticket::count());
    }

    public function test_draf_boleh_disimpan_meski_belum_lengkap(): void
    {
        $this->actingAsRole('requester');

        $this->postJson('/api/tickets', [
            'title' => 'Draf tanpa subjek',
            'sla_policy_id' => $this->policy()->id,
            'service_id' => $this->service()->id,
            'service_name' => 'SAP',
            'is_draft' => true,
        ])->assertCreated();

        $this->assertSame(1, Ticket::count());
        $this->assertSame('Draft', Ticket::first()->status);
    }

    /**
     * Lubang yang sama, pintu yang berbeda.
     *
     * Draf boleh setengah jadi, tapi mengirimkannya berarti ia berhenti jadi
     * draf. Kalau jalur penyuntingan tidak menuntut kelengkapan, aturan di
     * jalur pembuatan hanya memindahkan lubangnya, bukan menutupnya.
     */
    public function test_draf_setengah_jadi_tidak_bisa_dikirim_lewat_penyuntingan(): void
    {
        $this->actingAsRole('requester');

        $draft = $this->postJson('/api/tickets', [
            'title' => 'Draf tanpa subjek',
            'sla_policy_id' => $this->policy()->id,
            'service_id' => $this->service()->id,
            'service_name' => 'SAP',
            'is_draft' => true,
        ])->assertCreated()->json();

        $this->putJson("/requester/tickets/{$draft['ticket_no']}", [
            'title' => $draft['title'],
            'sla_policy_id' => $draft['sla_policy_id'],
            'service_id' => $draft['service_catalog_service_id'],
            'service_name' => 'SAP',
            'is_draft' => false,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['subcategory_name', 'subject_name', 'issue_category']);

        $this->assertSame('Draft', Ticket::first()->fresh()->status);
    }

    public function test_kiriman_lengkap_tetap_diterima(): void
    {
        $this->actingAsRole('requester');

        $this->postJson('/api/tickets', $this->completePayload())
            ->assertCreated();

        $this->assertSame(1, Ticket::count());
        $this->assertSame('Open', Ticket::first()->status);
    }
}
