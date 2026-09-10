<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Models\IssueCategory;
use App\Models\Role;
use App\Models\ServiceCatalogService;
use App\Models\ServiceCatalogSubcategory;
use App\Models\ServiceCatalogSubject;
use App\Models\SlaPolicy;
use App\Models\SupportAgent;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Perkakas bersama untuk tes yang butuh DUA desk sekaligus (Support IT dan
 * Support BPO) beserta tiketnya masing-masing.
 *
 * Dipisahkan ke trait, bukan disalin per berkas tes seperti helper `agent()`
 * yang sudah terlanjur digandakan di tes-tes Team Lead lama: begitu satu desk
 * bertambah kolom wajib, versi yang tidak ikut diperbarui akan gagal dengan
 * pesan basis data, bukan dengan pesan yang menjelaskan apa yang diuji.
 */
trait MakesSupportDesks
{
    /** Seorang petugas pada satu desk, lengkap dengan user dan role-nya. */
    protected function deskAgent(string $type, string $name): SupportAgent
    {
        $role = Role::firstOrCreate(
            ['name' => $type === 'bpo' ? 'Support BPO' : 'Support IT'],
            ['type' => 'system', 'status' => 'active'],
        );

        $user = User::factory()->create([
            'name' => $name,
            'status' => 'active',
            'helpdesk_access' => 'enabled',
        ]);
        $user->roles()->syncWithoutDetaching([$role->id]);

        return SupportAgent::create([
            'name' => $name,
            'type' => $type,
            'is_active' => true,
            'user_id' => $user->id,
        ])->load('user');
    }

    /**
     * Memberi seorang Team Lead cakupan atas seorang petugas: satu Subkategori
     * yang ditugaskan kepadanya, berisi satu Subjek yang PIC-nya petugas itu.
     *
     * Wajib dipanggil di tes Team Lead BPO manapun yang mengharapkan
     * petugasnya terlihat. Sejak cakupan dibagi per Subkategori
     * (App\Support\TeamLeadScope), Subkategori yang belum ditugaskan tidak
     * terbaca Team Lead manapun — jadi dunia tes yang hanya berisi agent
     * tanpa katalog menghasilkan dashboard kosong, bukan dashboard penuh.
     */
    protected function deskScope(User $lead, SupportAgent $agent, string $nama = 'Cakupan Uji'): ServiceCatalogSubcategory
    {
        $service = ServiceCatalogService::firstOrCreate(['name' => 'Layanan Uji']);

        $subcategory = ServiceCatalogSubcategory::firstOrCreate(
            ['service_id' => $service->id, 'name' => $nama],
            ['team_lead_bpo_user_id' => $lead->id],
        );
        $subcategory->update(['team_lead_bpo_user_id' => $lead->id]);

        $picColumn = $agent->type === 'it' ? 'it_agent_id' : 'support_agent_id';

        ServiceCatalogSubject::create([
            'issue_category_id' => IssueCategory::firstOrCreate(['name' => 'Incident'])->id,
            'service_id' => $service->id,
            'subcategory_id' => $subcategory->id,
            'name' => 'Subjek '.$agent->name.' '.random_int(1000, 9999),
            'requires_approval' => false,
            $picColumn => $agent->id,
            'support_level' => 1,
            'is_active' => true,
        ]);

        return $subcategory;
    }

    protected function deskSlaPolicy(string $priority = 'Medium'): SlaPolicy
    {
        return SlaPolicy::firstOrCreate(
            ['policy_name' => 'Uji Desk '.$priority],
            [
                'priority' => $priority,
                'service_type' => 'Incident',
                'response_time_minutes' => 480,
                'resolution_time_minutes' => 2880,
                'warning_threshold_percent' => 80,
                'status' => 'active',
            ],
        );
    }

    /**
     * Tiket aktif milik seorang petugas, dengan jam SLA yang masih berjalan
     * supaya ia ikut terhitung di setiap panel Team Lead.
     *
     * @param  array<string,mixed>  $overrides
     */
    protected function deskTicket(SupportAgent $agent, array $overrides = []): Ticket
    {
        $now = Carbon::now();
        $policy = $this->deskSlaPolicy();

        return Ticket::create(array_merge([
            'ticket_no' => 'INC-'.strtoupper($agent->type).'-'.random_int(100000, 999999),
            'title' => 'Kendala pada desk '.strtoupper($agent->type),
            'requester_name' => 'Andi Requester',
            'status' => 'In Progress',
            'priority' => 'Medium',
            'sla_policy_id' => $policy->id,
            'service_name' => 'ELISA',
            'catalog_subject_id' => null,
            'assigned_agent_id' => $agent->id,
            'sla_started_at' => $now->clone(),
            'response_time_minutes' => 480,
            'resolution_time_minutes' => 2880,
            'warning_threshold_percent' => 80,
            'response_due_at' => $now->clone()->addHours(8),
            'resolution_due_at' => $now->clone()->addDays(2),
            'warning_at' => $now->clone()->addDays(1),
        ], $overrides));
    }
}
