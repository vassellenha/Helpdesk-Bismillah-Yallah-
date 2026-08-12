<?php

namespace Tests\Feature\TeamLead;

use App\Models\SlaPolicy;
use App\Models\SupportAgent;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\ActsAsRole;
use Tests\TestCase;
use ZipArchive;

/**
 * Reporting Team Lead: pratinjau di layar dan berkas yang diunduh.
 *
 * Yang dijaga di sini adalah satu janji yang dulu tidak ditepati: apa pun
 * penyaring yang dipilih (periode, unit), tabel di layar dan isi berkas
 * unduhannya harus berasal dari kumpulan tiket yang sama.
 */
class ReportExportTest extends TestCase
{
    use ActsAsRole, RefreshDatabase;

    public function test_pratinjau_mengikuti_rentang_tanggal(): void
    {
        $this->ticket('SAP', Carbon::parse('2026-07-10'));
        $this->ticket('ADELE', Carbon::parse('2026-08-05'));
        $this->actingAsRole('team-lead');

        $rows = $this->preview(['from' => '2026-08-01', 'to' => '2026-08-31'])->json('rows');

        $this->assertSame([['ADELE', '1', '0', '0', '1']], $rows);
    }

    public function test_pratinjau_mengikuti_penyaring_unit(): void
    {
        $this->ticket('SAP', Carbon::parse('2026-08-05'), unit: 'Divisi Konstruksi');
        $this->ticket('ADELE', Carbon::parse('2026-08-06'), unit: 'Divisi TI');
        $this->actingAsRole('team-lead');

        $rows = $this->preview(['unit' => 'Divisi TI'])->json('rows');

        $this->assertSame([['ADELE', '1', '0', '0', '1']], $rows);
    }

    public function test_unduhan_excel_berisi_workbook_xlsx_sungguhan(): void
    {
        $this->ticket('SAP', Carbon::parse('2026-08-05'));
        $this->actingAsRole('team-lead');

        $response = $this->get($this->exportUrl(['format' => 'excel']))->assertOk();

        $response->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertStringContainsString('.xlsx"', $response->headers->get('Content-Disposition'));

        $sheet = $this->sheetXml($response->getContent());
        $this->assertStringContainsString('Aplikasi', $sheet);
        $this->assertStringContainsString('SAP', $sheet);
    }

    public function test_unduhan_pdf_berisi_berkas_pdf(): void
    {
        $this->ticket('SAP', Carbon::parse('2026-08-05'));
        $this->actingAsRole('team-lead');

        $response = $this->get($this->exportUrl(['format' => 'pdf']))->assertOk();

        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_isi_unduhan_sama_dengan_pratinjau(): void
    {
        $this->ticket('SAP', Carbon::parse('2026-08-05'));
        $this->ticket('SAP', Carbon::parse('2026-08-06'), category: 'Service Request');
        $this->ticket('ADELE', Carbon::parse('2026-07-01'));
        $this->actingAsRole('team-lead');

        $filters = ['from' => '2026-08-01', 'to' => '2026-08-31'];
        $preview = $this->preview($filters)->json('rows');
        $sheet = $this->sheetXml($this->get($this->exportUrl($filters + ['format' => 'excel']))->getContent());

        $this->assertSame([['SAP', '1', '1', '0', '2']], $preview);
        foreach ($preview[0] as $cell) {
            $this->assertStringContainsString($cell, $sheet);
        }
    }

    public function test_rentang_tanggal_terbalik_ditolak(): void
    {
        $this->actingAsRole('team-lead');

        // Unduhan berjalan lewat fetch (Accept: application/json), jadi
        // penolakannya sampai ke layar sebagai pesan, bukan halaman error.
        $this->preview(['from' => '2026-08-31', 'to' => '2026-08-01'])->assertStatus(422);
        $this->getJson($this->exportUrl(['from' => '2026-08-31', 'to' => '2026-08-01', 'format' => 'excel']))
            ->assertStatus(422);
    }

    public function test_role_lain_tidak_bisa_mengunduh_laporan_team_lead(): void
    {
        $this->actingAsRole('support');

        $this->get($this->exportUrl(['format' => 'excel']))->assertStatus(403);
        $this->getJson($this->previewUrl([]))->assertStatus(403);
    }

    /** @param array<string,string> $filters */
    private function preview(array $filters = []): TestResponse
    {
        return $this->getJson($this->previewUrl($filters));
    }

    /** @param array<string,string> $filters */
    private function previewUrl(array $filters): string
    {
        return route('team-lead.reports.preview', $this->filters($filters));
    }

    /** @param array<string,string> $filters */
    private function exportUrl(array $filters): string
    {
        return route('team-lead.reports.export', $this->filters($filters));
    }

    /**
     * @param  array<string,string>  $filters
     * @return array<string,string>
     */
    private function filters(array $filters): array
    {
        return array_merge([
            'type' => 'ticket_summary',
            'from' => '2026-07-01',
            'to' => '2026-08-31',
            'unit' => '__all',
        ], $filters);
    }

    /** Isi worksheet pertama dari berkas .xlsx yang diunduh. */
    private function sheetXml(string $binary): string
    {
        $path = tempnam(sys_get_temp_dir(), 'uji-xlsx');
        file_put_contents($path, $binary);

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path) === true, 'Berkas Excel bukan arsip yang bisa dibuka.');
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        @unlink($path);

        $this->assertIsString($sheet, 'Worksheet tidak ditemukan di dalam berkas .xlsx.');

        return $sheet;
    }

    private function ticket(string $service, Carbon $createdAt, string $category = 'Incident', ?string $unit = null): Ticket
    {
        $requester = User::factory()->create(['unit' => $unit, 'status' => 'active', 'helpdesk_access' => 'enabled']);

        $ticket = Ticket::create([
            'ticket_no' => 'INC-UJI-2026-'.random_int(1000, 9999),
            'title' => 'Tiket uji laporan',
            'requester_name' => $requester->name,
            'requester_id' => $requester->id,
            'status' => 'In Progress',
            'priority' => 'Medium',
            'issue_category' => $category,
            'service_name' => $service,
            'subcategory_name' => 'Account & Profile Issues',
            'subject_name' => 'Login dan Otorisasi SAP',
            'assigned_agent_id' => $this->itAgentId(),
            'sla_policy_id' => $this->slaPolicyId(),
            'response_time_minutes' => 480,
            'resolution_time_minutes' => 2880,
            'warning_threshold_percent' => 80,
            'response_due_at' => $createdAt->clone()->addHours(8),
            'resolution_due_at' => $createdAt->clone()->addDays(2),
            'warning_at' => $createdAt->clone()->addDays(1),
        ]);

        // created_at diisi otomatis "sekarang"; laporan menyaring kolom itu.
        $ticket->forceFill(['created_at' => $createdAt])->save();

        return $ticket;
    }

    private ?int $itAgentId = null;

    private function itAgentId(): int
    {
        return $this->itAgentId ??= SupportAgent::create([
            'name' => 'Febria Sahrina',
            'type' => 'it',
            'is_active' => true,
        ])->id;
    }

    private ?int $slaPolicyId = null;

    private function slaPolicyId(): int
    {
        return $this->slaPolicyId ??= SlaPolicy::create([
            'policy_name' => 'Uji Laporan',
            'priority' => 'Medium',
            'service_type' => 'Incident',
            'response_time_minutes' => 480,
            'resolution_time_minutes' => 2880,
            'warning_threshold_percent' => 80,
            'status' => 'active',
        ])->id;
    }
}
