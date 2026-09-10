<?php

declare(strict_types=1);

namespace Tests\Feature\TeamLead;

use App\Models\IssueCategory;
use App\Models\Role;
use App\Models\ServiceCatalogService;
use App\Models\ServiceCatalogSubcategory;
use App\Models\ServiceCatalogSubject;
use App\Models\SupportAgent;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsRole;
use Tests\Concerns\MakesSupportDesks;
use Tests\TestCase;

/**
 * Dua Team Lead BPO membagi SATU Layanan yang sama lewat dua Subkategori
 * berbeda. Ini inti dari keputusan mengikat cakupan di level Subkategori:
 * Layanan terbesar di katalog (SAP) memuat 44% seluruh Subjek, dan tanpa
 * pembelahan seperti ini ia akan jatuh utuh ke satu orang.
 *
 * Yang diuji bukan cuma "A melihat miliknya", tapi juga "A TIDAK melihat
 * milik B" — dua arah, karena kebocoran cakupan tidak pernah memunculkan
 * error. Layarnya tetap tampil rapi, hanya saja memuat orang dan tiket yang
 * bukan urusan pembacanya.
 */
final class TeamLeadSubcategoryScopeTest extends TestCase
{
    use ActsAsRole, MakesSupportDesks, RefreshDatabase;

    public function test_dashboard_hanya_memuat_agent_dan_tiket_subkategorinya(): void
    {
        $dunia = $this->duaSubkategoriSatuLayanan();

        $feedA = $this->feedSebagai($dunia['leadA']);
        $namaA = collect($feedA->json('agentOptions'))->pluck('name')->all();

        $this->assertContains('Genta Pratama', $namaA);
        $this->assertNotContains(
            'Rio Saputra',
            $namaA,
            'PIC Subkategori milik Team Lead lain tidak boleh muncul di dropdown pemindahan PIC',
        );
        $this->assertTrue($this->punyaTiket($feedA->json('monitorRows'), $dunia['tiketA']));
        $this->assertFalse(
            $this->punyaTiket($feedA->json('monitorRows'), $dunia['tiketB']),
            'tiket Subkategori milik Team Lead lain tidak boleh muncul di SLA Monitoring',
        );

        $feedB = $this->feedSebagai($dunia['leadB']);
        $namaB = collect($feedB->json('agentOptions'))->pluck('name')->all();

        $this->assertContains('Rio Saputra', $namaB);
        $this->assertNotContains('Genta Pratama', $namaB, 'kebocoran ke arah sebaliknya juga harus tertutup');
        $this->assertTrue($this->punyaTiket($feedB->json('monitorRows'), $dunia['tiketB']));
        $this->assertFalse($this->punyaTiket($feedB->json('monitorRows'), $dunia['tiketA']));
    }

    public function test_workload_dan_pic_per_subjek_ikut_menyempit(): void
    {
        $dunia = $this->duaSubkategoriSatuLayanan();

        $feedA = $this->feedSebagai($dunia['leadA']);

        $this->assertNotContains(
            'Rio Saputra',
            collect($feedA->json('workload'))->pluck('name')->all(),
            'tabel Workload harus mengikuti cakupan yang sama dengan dropdown',
        );
        $this->assertNotContains(
            'Rio Saputra',
            collect($feedA->json('picRows'))->pluck('pic')->all(),
            'tabel PIC per Subjek harus mengikuti cakupan yang sama',
        );
        $this->assertNotContains(
            'Rio Saputra',
            collect($feedA->json('monitorFilters.pics'))->all(),
            'dropdown filter PIC di SLA Monitoring harus mengikuti cakupan yang sama',
        );
        $this->assertSame(1, $feedA->json('metrics.agents'));
    }

    /**
     * Subkategori yang belum ditugaskan tidak memberi wewenang kepada siapa
     * pun. Kalau ini terbalik, kelalaian Admin berubah jadi izin: tiap baris
     * yang lupa diisi bisa diawasi — dan PIC-nya bisa dipindahkan tiketnya —
     * oleh setiap Team Lead BPO sekaligus.
     */
    public function test_subkategori_yang_belum_ditugaskan_tidak_terlihat_siapa_pun(): void
    {
        $dunia = $this->duaSubkategoriSatuLayanan();

        $bebas = $this->subkategori('Konsultasi Proses', $dunia['service']);
        $pic = $this->deskAgent('bpo', 'Lutfi Ramadhan');
        $this->subjek($bebas, $pic);

        foreach (['leadA', 'leadB'] as $siapa) {
            $this->assertNotContains(
                'Lutfi Ramadhan',
                collect($this->feedSebagai($dunia[$siapa])->json('agentOptions'))->pluck('name')->all(),
                'Subkategori yang belum ditugaskan tidak boleh memberi wewenang kepada Team Lead manapun',
            );
        }
    }

    /**
     * Sampai Admin membagi katalog, dashboard memang kosong — dan layarnya
     * harus mengatakan kenapa, bukan sekadar menampilkan angka nol yang tidak
     * bisa dibedakan dari "timnya sedang tidak punya pekerjaan".
     */
    public function test_lead_tanpa_subkategori_diberi_tahu_lewat_penanda_cakupan_kosong(): void
    {
        $this->duaSubkategoriSatuLayanan();
        $tanpaJatah = $this->lead('Rizal Tanpa Jatah');

        $feed = $this->feedSebagai($tanpaJatah);

        $this->assertTrue($feed->json('scopeEmpty'));
        $this->assertSame(0, $feed->json('metrics.agents'));
    }

    public function test_tiket_subkategori_lain_ditolak_lewat_url_langsung(): void
    {
        $dunia = $this->duaSubkategoriSatuLayanan();

        $this->actingAsUserWithRoles($dunia['leadB'], 'team-lead-bpo');

        $this->getJson(route('team-lead-bpo.tickets.data', $dunia['tiketA']))->assertStatus(403);
        $this->get(route('team-lead-bpo.tickets.show', $dunia['tiketA']))->assertStatus(403);

        $this->postJson(route('team-lead-bpo.tickets.raise-priority', $dunia['tiketA']), [
            'priority' => 'High',
            'reason' => 'Coba menembus cakupan.',
        ])->assertStatus(403);

        $this->assertSame(
            'Medium',
            $dunia['tiketA']->fresh()->priority,
            'tiket di luar cakupan tidak boleh berubah sedikit pun oleh Team Lead lain',
        );
    }

    public function test_laporan_ikut_menyempit(): void
    {
        $dunia = $this->duaSubkategoriSatuLayanan();

        $this->actingAsUserWithRoles($dunia['leadA'], 'team-lead-bpo');
        $preview = $this->getJson(route('team-lead-bpo.reports.preview', [
            'type' => 'support_perf',
            'from' => now()->subMonth()->toDateString(),
            'to' => now()->addDay()->toDateString(),
            'unit' => '__all',
        ]))->assertOk();

        // Baris laporan berbentuk array posisional; kolom pertama nama agent.
        $nama = collect($preview->json('rows'))->map(fn (array $row) => $row[0])->all();

        $this->assertContains('Genta Pratama', $nama);
        $this->assertNotContains(
            'Rio Saputra',
            $nama,
            'laporan yang bisa diekspor tidak boleh memuat petugas di luar cakupan',
        );
    }

    /**
     * Desk IT belum dibagi-bagi. Membagi sisi BPO tidak boleh menyentuhnya
     * sedikit pun — kalau agentIds() mengembalikan [] alih-alih null untuk
     * desk IT, seluruh dashboard Tim IT jadi kosong tanpa satu pun error.
     */
    public function test_desk_it_tidak_terpengaruh(): void
    {
        $this->duaSubkategoriSatuLayanan();

        $itAgent = $this->deskAgent('it', 'Agung Wijayanto');
        $tiketIt = $this->deskTicket($itAgent);

        $this->actingAsRole('team-lead');
        $feed = $this->getJson(route('team-lead.data-feed'))->assertOk();

        $this->assertContains('Agung Wijayanto', collect($feed->json('agentOptions'))->pluck('name')->all());
        $this->assertTrue(
            $this->punyaTiket($feed->json('monitorRows'), $tiketIt),
            'pembagian cakupan di sisi BPO tidak boleh mengosongkan dashboard Tim IT',
        );
    }

    /**
     * @return array{leadA:User, leadB:User, service:ServiceCatalogService, tiketA:Ticket, tiketB:Ticket}
     */
    private function duaSubkategoriSatuLayanan(): array
    {
        $leadA = $this->lead('Rizky Hidayat');
        $leadB = $this->lead('Denny Firmansyah');

        $service = ServiceCatalogService::firstOrCreate(['name' => 'SAP']);

        $picA = $this->deskAgent('bpo', 'Genta Pratama');
        $picB = $this->deskAgent('bpo', 'Rio Saputra');

        $this->subjek($this->subkategori('Akses & Otorisasi', $service, $leadA), $picA);
        $this->subjek($this->subkategori('Data & Laporan', $service, $leadB), $picB);

        return [
            'leadA' => $leadA,
            'leadB' => $leadB,
            'service' => $service,
            'tiketA' => $this->deskTicket($picA),
            'tiketB' => $this->deskTicket($picB),
        ];
    }

    private function feedSebagai(User $lead): \Illuminate\Testing\TestResponse
    {
        $this->actingAsUserWithRoles($lead, 'team-lead-bpo');

        return $this->getJson(route('team-lead-bpo.data-feed'))->assertOk();
    }

    private function punyaTiket(?array $rows, Ticket $ticket): bool
    {
        return collect($rows ?? [])->contains(fn ($row) => ($row['id'] ?? null) === $ticket->ticket_no);
    }

    private function lead(string $nama): User
    {
        $role = Role::firstOrCreate(
            ['name' => 'Team Lead BPO'],
            ['type' => 'system', 'status' => 'active'],
        );

        $user = User::factory()->create([
            'name' => $nama,
            'status' => 'active',
            'helpdesk_access' => 'enabled',
        ]);
        $user->roles()->syncWithoutDetaching([$role->id]);

        return $user;
    }

    private function subkategori(string $nama, ServiceCatalogService $service, ?User $lead = null): ServiceCatalogSubcategory
    {
        return ServiceCatalogSubcategory::create([
            'service_id' => $service->id,
            'name' => $nama,
            'team_lead_bpo_user_id' => $lead?->id,
        ]);
    }

    private function subjek(ServiceCatalogSubcategory $sub, SupportAgent $pic): ServiceCatalogSubject
    {
        return ServiceCatalogSubject::create([
            'issue_category_id' => IssueCategory::firstOrCreate(['name' => 'Incident'])->id,
            'service_id' => $sub->service_id,
            'subcategory_id' => $sub->id,
            'name' => 'Subjek '.$pic->name.' '.random_int(1000, 9999),
            'requires_approval' => false,
            'support_agent_id' => $pic->id,
            'support_level' => 1,
            'is_active' => true,
        ]);
    }
}
