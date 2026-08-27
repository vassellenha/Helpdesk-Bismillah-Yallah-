<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\IssueCategory;
use App\Models\Role;
use App\Models\ServiceCatalogService;
use App\Models\ServiceCatalogSubcategory;
use App\Models\ServiceCatalogSubject;
use App\Models\SlaPolicy;
use App\Models\SupportAgent;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * BPO dan IT punya jam responsnya masing-masing.
 *
 * Sebelumnya tiket cuma punya satu jam, dan menekan "Eskalasi IT" sudah
 * dihitung sebagai respons — jadi begitu tiket sampai ke IT, jamnya sudah
 * beku "sudah direspons" dan tim IT tidak pernah punya tenggat sendiri.
 * Tiket bisa menganggur berhari-hari tanpa satu pun indikator berubah warna.
 */
class TicketEscalateResponseClockTest extends TestCase
{
    use RefreshDatabase;

    private function agent(string $name, string $type, string $roleName): SupportAgent
    {
        $role = Role::firstOrCreate(['name' => $roleName]);
        $user = User::factory()->create(['name' => $name, 'status' => 'active', 'helpdesk_access' => 'enabled']);
        $user->roles()->attach($role->id);

        return SupportAgent::create(['name' => $name, 'type' => $type, 'is_active' => true, 'user_id' => $user->id]);
    }

    private function policy(): SlaPolicy
    {
        return SlaPolicy::create([
            'policy_name' => 'Uji Jam Respons', 'priority' => 'Medium', 'service_type' => 'Incident',
            'response_time_minutes' => 480, 'resolution_time_minutes' => 2880,
            'warning_threshold_percent' => 80, 'status' => 'active',
        ]);
    }

    /** Tiket katalog biasa — Subject-nya menentukan satu PIC IT, jadi jalur tunggal. */
    private function catalogTicket(SupportAgent $bpo, SupportAgent $it): Ticket
    {
        $issueCategory = IssueCategory::firstOrCreate(['name' => 'Incident']);
        $service = ServiceCatalogService::create(['name' => 'AKUN APLIKASI']);
        $subcategory = ServiceCatalogSubcategory::create(['service_id' => $service->id, 'name' => 'Akun']);
        $subject = ServiceCatalogSubject::create([
            'issue_category_id' => $issueCategory->id, 'service_id' => $service->id, 'subcategory_id' => $subcategory->id,
            'name' => 'Penonaktifan akun', 'requires_approval' => false,
            'support_agent_id' => $bpo->id, 'it_agent_id' => $it->id, 'support_level' => 2, 'is_active' => true,
        ]);

        $now = Carbon::now();
        $policy = $this->policy();

        return Ticket::create([
            'ticket_no' => 'AR-UJI-2026-'.random_int(1000, 9999),
            'title' => 'Penonaktifan akun', 'requester_name' => 'Marcell', 'status' => 'Open', 'priority' => 'Medium',
            'sla_policy_id' => $policy->id, 'service_name' => 'AKUN APLIKASI', 'service_catalog_service_id' => $service->id,
            'catalog_subject_id' => $subject->id, 'assigned_agent_id' => $bpo->id,
            'response_time_minutes' => 480, 'resolution_time_minutes' => 2880, 'warning_threshold_percent' => 80,
            'response_due_at' => $now->clone()->addHours(8),
            'resolution_due_at' => $now->clone()->addDays(2),
            'warning_at' => $now->clone()->addDays(1),
        ]);
    }

    public function test_tiket_katalog_kembali_open_supaya_pic_it_dapat_popup(): void
    {
        $bpo = $this->agent('Genta Pratama', 'bpo', 'Support BPO');
        $it = $this->agent('Aditya Dwi Nugraha', 'it', 'Support IT');
        $ticket = $this->catalogTicket($bpo, $it);

        // BPO mengerjakannya dulu -> In Progress.
        $this->actingAs(User::find($bpo->user_id))
            ->postJson(route('support-bpo.tickets.start', $ticket))->assertOk();
        $this->assertSame('In Progress', $ticket->fresh()->status);

        $this->actingAs(User::find($bpo->user_id))
            ->postJson(route('support-bpo.tickets.escalate', $ticket), ['note' => 'perlu IT'])->assertOk();

        $ticket->refresh();
        $this->assertSame('Open', $ticket->status, 'Tahap IT belum dikerjakan siapa pun -> harus Open lagi.');
        $this->assertSame($it->id, $ticket->assigned_agent_id);

        // Tombol "Kerjakan Sekarang" milik PIC IT benar-benar bisa dipakai.
        $this->actingAs(User::find($it->user_id))
            ->postJson(route('support.tickets.start', $ticket))->assertOk();
        $this->assertSame('In Progress', $ticket->fresh()->status);
    }

    public function test_jam_respons_it_terpisah_dari_bpo(): void
    {
        $bpo = $this->agent('Genta Pratama', 'bpo', 'Support BPO');
        $it = $this->agent('Aditya Dwi Nugraha', 'it', 'Support IT');
        $ticket = $this->catalogTicket($bpo, $it);
        $batasBpo = $ticket->response_due_at;

        Carbon::setTestNow(Carbon::now()->addHours(2));

        $this->actingAs(User::find($bpo->user_id))
            ->postJson(route('support-bpo.tickets.escalate', $ticket), ['note' => 'perlu IT'])->assertOk();

        $ticket->refresh();

        // Respons BPO tercatat dan TIDAK tertimpa — TeamLeadController membaca
        // kolom ini untuk rata-rata waktu respons tim.
        $this->assertNotNull($ticket->first_response_at, 'Menekan Eskalasi tetap dihitung sebagai respons BPO.');
        $this->assertTrue($batasBpo->equalTo($ticket->response_due_at), 'Batas respons BPO tidak boleh bergeser.');

        // Tahap IT mulai dari nol: belum direspons, dengan tenggatnya sendiri.
        $this->assertNull($ticket->it_first_response_at, 'IT belum menanggapi apa pun.');
        $this->assertNotNull($ticket->it_response_due_at, 'IT harus punya batas responsnya sendiri.');
        $this->assertTrue(
            $ticket->it_response_due_at->greaterThan($ticket->response_due_at),
            'Batas IT dihitung dari waktu eskalasi, jadi jatuh setelah batas BPO.'
        );

        // Yang ditampilkan di panel adalah jam tahap yang sedang berjalan.
        $this->assertSame('it', $ticket->slaPayload()['response']['stage']);
        $this->assertStringContainsString('Belum direspons', $ticket->response_label);

        Carbon::setTestNow();
    }

    public function test_balasan_pertama_it_menghentikan_jam_it_bukan_jam_bpo(): void
    {
        $bpo = $this->agent('Genta Pratama', 'bpo', 'Support BPO');
        $it = $this->agent('Aditya Dwi Nugraha', 'it', 'Support IT');
        $ticket = $this->catalogTicket($bpo, $it);

        $this->actingAs(User::find($bpo->user_id))
            ->postJson(route('support-bpo.tickets.escalate', $ticket), ['note' => 'perlu IT'])->assertOk();

        $responsBpo = $ticket->fresh()->first_response_at;

        Carbon::setTestNow(Carbon::now()->addHour());

        $this->actingAs(User::find($it->user_id))
            ->postJson(route('support.tickets.comments.store', $ticket), ['message' => 'Saya cek dulu.'])
            ->assertCreated();

        $ticket->refresh();

        $this->assertNotNull($ticket->it_first_response_at, 'Balasan IT harus menghentikan jam IT.');
        $this->assertTrue(
            $responsBpo->equalTo($ticket->first_response_at),
            'Respons BPO tidak boleh ikut berubah karena IT membalas.'
        );

        // Kedua tahap terbaca berdampingan di panel SLA.
        $tahap = collect($ticket->slaPayload()['responseStages']);
        $this->assertSame(['BPO', 'IT'], $tahap->pluck('label')->all());
        $this->assertNotNull($tahap->firstWhere('label', 'BPO')['at']);
        $this->assertNotNull($tahap->firstWhere('label', 'IT')['at']);

        Carbon::setTestNow();
    }

    public function test_tiket_belum_dieskalasi_tetap_memakai_jam_bpo(): void
    {
        $bpo = $this->agent('Genta Pratama', 'bpo', 'Support BPO');
        $it = $this->agent('Aditya Dwi Nugraha', 'it', 'Support IT');
        $ticket = $this->catalogTicket($bpo, $it);

        $payload = $ticket->slaPayload();

        $this->assertSame('bpo', $payload['response']['stage']);
        $this->assertNull($payload['responseStages'], 'Rincian dua tahap baru muncul setelah eskalasi.');
        $this->assertNull($ticket->it_response_due_at);
    }
}
