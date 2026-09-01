<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Models\Role;
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
