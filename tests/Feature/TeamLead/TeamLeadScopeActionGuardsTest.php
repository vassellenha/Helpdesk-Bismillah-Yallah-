<?php

declare(strict_types=1);

namespace Tests\Feature\TeamLead;

use App\Models\AuditTrail;
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
 * Empat jalur yang tidak lewat penyaring utama, dan karena itu tetap terbuka
 * meski daftar di layar sudah menyempit. Menyaring tampilan tanpa menutup
 * jalur-jalur ini menghasilkan penyempitan yang cuma kosmetik: yang hilang
 * hanya namanya dari dropdown, sementara perintahnya masih diterima.
 *
 * Yang paling berbahaya `reassign()`: sebelum ini validasinya hanya
 * `exists:support_agents,id`, jadi satu id yang ditebak sudah cukup untuk
 * melempar tiket ke petugas tim mana pun — persis celah yang membuat orang
 * yang tidak terdaftar di katalog manapun tetap bisa menerima tiket.
 */
final class TeamLeadScopeActionGuardsTest extends TestCase
{
    use ActsAsRole, MakesSupportDesks, RefreshDatabase;

    public function test_pemindahan_pic_ke_agent_di_luar_cakupan_ditolak(): void
    {
        $d = $this->dunia();

        $this->actingAsUserWithRoles($d['leadA'], 'team-lead-bpo');

        $this->postJson(route('team-lead-bpo.tickets.reassign', $d['tiketA']), [
            'agent_id' => $d['picB']->id,
            'reason' => 'Mencoba menembus cakapan Subkategori.',
        ])->assertStatus(422);

        $this->assertSame(
            $d['picA']->id,
            $d['tiketA']->fresh()->assigned_agent_id,
            'tiket tidak boleh berpindah ke petugas di luar cakupan Team Lead',
        );
    }

    public function test_pemindahan_pic_di_dalam_cakupan_tetap_bisa(): void
    {
        $d = $this->dunia();
        $rekan = $this->deskAgent('bpo', 'Lutfi Ramadhan');
        $this->subjek($d['subA'], $rekan);

        $this->actingAsUserWithRoles($d['leadA'], 'team-lead-bpo');

        $this->postJson(route('team-lead-bpo.tickets.reassign', $d['tiketA']), [
            'agent_id' => $rekan->id,
            'reason' => 'Pemerataan beban di dalam Subkategori yang sama.',
        ])->assertOk();

        $this->assertSame($rekan->id, $d['tiketA']->fresh()->assigned_agent_id);
    }

    public function test_teguran_rating_ke_agent_di_luar_cakupan_ditolak(): void
    {
        $d = $this->dunia();

        $this->actingAsUserWithRoles($d['leadA'], 'team-lead-bpo');

        $this->postJson(route('team-lead-bpo.agents.remind-rating', $d['picB']), [
            'message' => 'Mohon perbaiki kualitas layanan.',
        ])->assertStatus(403);

        $this->assertDatabaseMissing('audit_trails', ['action' => 'remind_rating']);
    }

    /**
     * Kartu agent di modal Pemindahan PIC menyebut Subjek apa saja yang
     * dipegang seseorang, dan dari situ modal menghitung "PIC terdekat".
     * Kalau daftarnya diambil dari seluruh katalog, Team Lead membaca cakupan
     * yang bukan urusannya — dan rekomendasinya ikut condong ke sana.
     */
    public function test_kartu_agent_tidak_membocorkan_subjek_di_luar_cakupan(): void
    {
        $d = $this->dunia();

        // PIC yang sama juga memegang Subjek di Subkategori milik Team Lead lain.
        $subjekLuar = $this->subjek($d['subB'], $d['picA']);

        $this->actingAsUserWithRoles($d['leadA'], 'team-lead-bpo');
        $kartu = collect($this->getJson(route('team-lead-bpo.data-feed'))->json('agentOptions'))
            ->firstWhere('name', 'Genta Pratama');

        $this->assertNotContains(
            $subjekLuar->name,
            $kartu['subjects'] ?? [],
            'kartu agent tidak boleh menyebut Subjek dari Subkategori milik Team Lead lain',
        );
    }

    /**
     * Sebelum ini daftar Eskalasi desk BPO tidak disaring sama sekali —
     * setiap tiket yang pernah dieskalasi terbaca semua Team Lead BPO.
     * Cakupannya tidak boleh diambil dari PIC sekarang (sudah berpindah ke
     * Tim IT), melainkan dari PIC BPO asal yang tersimpan di
     * `escalated_by_agent_id`.
     */
    public function test_eskalasi_hanya_yang_berasal_dari_agent_dalam_cakupan(): void
    {
        $d = $this->dunia();

        $dariA = $this->tiketEskalasi($d['picA']);
        $dariB = $this->tiketEskalasi($d['picB']);

        $this->actingAsUserWithRoles($d['leadA'], 'team-lead-bpo');
        $nomor = collect($this->getJson(route('team-lead-bpo.data-feed'))->json('escalations'))->pluck('id')->all();

        $this->assertContains($dariA->ticket_no, $nomor);
        $this->assertNotContains(
            $dariB->ticket_no,
            $nomor,
            'eskalasi yang berangkat dari petugas Team Lead lain tidak boleh terbaca di sini',
        );
    }

    /**
     * Tiket yang dieskalasi sebelum kolom `escalated_by_agent_id` ada tidak
     * punya jejak PIC asal sama sekali, jadi tidak ada cara mengetahui tim
     * mana yang mengirimnya. Menampilkannya ke semua Team Lead berarti
     * membocorkan tiket tim lain atas dasar ketidaktahuan. Riwayatnya tetap
     * utuh dan terbaca Administrator lewat Ticket Management.
     */
    public function test_eskalasi_lama_tanpa_jejak_pic_asal_tidak_ditampilkan(): void
    {
        $d = $this->dunia();
        $warisan = $this->tiketEskalasi($d['picB'], jejakAsal: false);

        $this->actingAsUserWithRoles($d['leadA'], 'team-lead-bpo');
        $nomor = collect($this->getJson(route('team-lead-bpo.data-feed'))->json('escalations'))->pluck('id')->all();

        $this->assertNotContains(
            $warisan->ticket_no,
            $nomor,
            'eskalasi tanpa PIC asal yang bisa ditunjuk tidak boleh muncul di layar Team Lead manapun',
        );
    }

    /**
     * Panel "Teguran Terkirim" membaca notifikasi sla_teguran terakhir tanpa
     * saringan apa pun — bocor lintas Team Lead, bahkan lintas desk.
     */
    public function test_riwayat_teguran_ikut_menyempit(): void
    {
        $d = $this->dunia();

        $this->teguran($d['picA'], 'Teguran untuk cakupan A');
        $this->teguran($d['picB'], 'Teguran untuk cakupan B');

        $this->actingAsUserWithRoles($d['leadA'], 'team-lead-bpo');
        $pesan = collect($this->getJson(route('team-lead-bpo.data-feed'))->json('reminderLog'))
            ->pluck('agent')->all();

        $this->assertContains('Genta Pratama', $pesan);
        $this->assertNotContains(
            'Rio Saputra',
            $pesan,
            'riwayat teguran petugas Team Lead lain tidak boleh terbaca di sini',
        );
    }

    /**
     * @return array{leadA:User, leadB:User, subA:ServiceCatalogSubcategory, subB:ServiceCatalogSubcategory, picA:SupportAgent, picB:SupportAgent, tiketA:Ticket}
     */
    private function dunia(): array
    {
        $leadA = $this->lead('Rizky Hidayat');
        $leadB = $this->lead('Denny Firmansyah');

        $service = ServiceCatalogService::firstOrCreate(['name' => 'SAP']);

        $picA = $this->deskAgent('bpo', 'Genta Pratama');
        $picB = $this->deskAgent('bpo', 'Rio Saputra');

        $subA = $this->subkategori('Akses & Otorisasi', $service, $leadA);
        $subB = $this->subkategori('Data & Laporan', $service, $leadB);

        $this->subjek($subA, $picA);
        $this->subjek($subB, $picB);

        return [
            'leadA' => $leadA,
            'leadB' => $leadB,
            'subA' => $subA,
            'subB' => $subB,
            'picA' => $picA,
            'picB' => $picB,
            'tiketA' => $this->deskTicket($picA),
        ];
    }

    private function tiketEskalasi(SupportAgent $asal, bool $jejakAsal = true): Ticket
    {
        $it = $this->deskAgent('it', 'Agung '.random_int(1000, 9999));

        return $this->deskTicket($it, [
            'escalated_at' => now(),
            'escalation_note' => 'Perlu penanganan Tim IT.',
            'escalated_by_agent_id' => $jejakAsal ? $asal->id : null,
        ]);
    }

    private function teguran(SupportAgent $agent, string $pesan): void
    {
        \App\Models\TicketNotification::create([
            'user_id' => $agent->user_id,
            'role' => 'support-bpo',
            'ticket_id' => $this->deskTicket($agent)->id,
            'type' => 'sla_teguran',
            'title' => 'Teguran SLA',
            'message' => $pesan,
        ]);
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

    private function subkategori(string $nama, ServiceCatalogService $service, ?User $lead): ServiceCatalogSubcategory
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
