<?php

declare(strict_types=1);

namespace Tests\Feature\Discussion;

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
use Tests\TestCase;

/**
 * Layar Support harus bisa membedakan gelembung MILIKNYA dari milik lawan
 * bicaranya.
 *
 * Dilaporkan dari produksi: pada satu tiket yang ditangani Support BPO lalu
 * dieskalasi ke Support IT, kedua pesan tampil di sisi kanan. Support IT
 * membaca pesan Support BPO seolah tulisannya sendiri, dan percakapan dua
 * pihak terbaca seperti monolog.
 *
 * Sebabnya perataan membaca `author_role`, dan kedua peran itu memang
 * menyimpan "Support" — disengaja, karena di mata Requester keduanya satu
 * pihak. Yang keliru bukan labelnya, melainkan memakai label untuk menjawab
 * pertanyaan "ini tulisan siapa".
 *
 * Yang dikunci di sini adalah RANGKAIANNYA sampai ke layar: controller
 * menitipkan identitas pembaca, dan tiap komentar membawa id penulisnya.
 * Tanpa keduanya, komponen tidak punya bahan untuk membedakan siapa pun.
 */
final class SupportBubbleAlignmentTest extends TestCase
{
    use RefreshDatabase;

    private function agent(string $name, string $type, string $roleName): SupportAgent
    {
        $role = Role::firstOrCreate(['name' => $roleName]);
        $user = User::factory()->create(['name' => $name, 'status' => 'active', 'helpdesk_access' => 'enabled']);
        $user->roles()->attach($role->id);

        return SupportAgent::create(['name' => $name, 'type' => $type, 'is_active' => true, 'user_id' => $user->id]);
    }

    private function catalogTicket(SupportAgent $bpo, SupportAgent $it): Ticket
    {
        $policy = SlaPolicy::create([
            'policy_name' => 'Uji Perataan Gelembung', 'priority' => 'Medium', 'service_type' => 'Incident',
            'response_time_minutes' => 480, 'resolution_time_minutes' => 2880,
            'warning_threshold_percent' => 80, 'status' => 'active',
        ]);

        $issueCategory = IssueCategory::firstOrCreate(['name' => 'Incident']);
        $service = ServiceCatalogService::create(['name' => 'ERISKA']);
        $subcategory = ServiceCatalogSubcategory::create(['service_id' => $service->id, 'name' => 'Risk Data & Reporting']);
        $subject = ServiceCatalogSubject::create([
            'issue_category_id' => $issueCategory->id, 'service_id' => $service->id, 'subcategory_id' => $subcategory->id,
            'name' => 'Kendala laporan', 'requires_approval' => false,
            'support_agent_id' => $bpo->id, 'it_agent_id' => $it->id, 'support_level' => 2, 'is_active' => true,
        ]);

        return Ticket::create([
            'ticket_no' => 'INC-UJI-2026-'.random_int(1000, 9999),
            'title' => 'Kendala laporan', 'requester_name' => 'Marcell', 'status' => 'Open', 'priority' => 'Medium',
            'sla_policy_id' => $policy->id, 'service_name' => 'ERISKA', 'service_catalog_service_id' => $service->id,
            'catalog_subject_id' => $subject->id, 'assigned_agent_id' => $bpo->id,
            'response_time_minutes' => 480, 'resolution_time_minutes' => 2880, 'warning_threshold_percent' => 80,
            'response_due_at' => now()->addHours(8),
            'resolution_due_at' => now()->addDays(2),
            'warning_at' => now()->addDay(),
        ]);
    }

    public function test_layar_support_it_menerima_identitas_pembaca_dan_id_tiap_penulis(): void
    {
        $bpo = $this->agent('Dummy SINTA', 'bpo', 'Support BPO');
        $it = $this->agent('Dummy SINTA 3', 'it', 'Support IT');
        $ticket = $this->catalogTicket($bpo, $it);

        $bpoUser = User::findOrFail($bpo->user_id);
        $itUser = User::findOrFail($it->user_id);

        $this->actingAs($bpoUser)
            ->postJson(route('support-bpo.tickets.comments.store', $ticket), ['message' => 'dari bpo'])
            ->assertCreated();

        $this->actingAs($bpoUser)
            ->postJson(route('support-bpo.tickets.escalate', $ticket), ['note' => 'perlu IT'])
            ->assertOk();

        $this->actingAs($itUser)
            ->postJson(route('support.tickets.comments.store', $ticket), ['message' => 'dari it'])
            ->assertCreated();

        $props = $this->actingAs($itUser)
            ->get(route('support.tickets.show', $ticket))
            ->assertOk()
            ->viewData('viewer');

        // 1. Layar tahu SIAPA yang sedang membaca.
        $this->assertSame($itUser->id, $props['id']);

        // 2. Tiap komentar membawa id penulisnya, dan keduanya BERBEDA
        //    walaupun labelnya sama-sama "Support".
        $comments = $ticket->fresh()->comments;
        $penulis = $comments->pluck('author_id')->filter()->unique();

        $this->assertTrue($penulis->contains($bpoUser->id), 'komentar BPO tidak membawa id penulisnya');
        $this->assertTrue($penulis->contains($itUser->id), 'komentar IT tidak membawa id penulisnya');
        $this->assertGreaterThanOrEqual(2, $penulis->count(), 'dua orang berbeda harus punya dua id berbeda');
    }
}
