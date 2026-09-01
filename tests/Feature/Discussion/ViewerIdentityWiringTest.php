<?php

declare(strict_types=1);

namespace Tests\Feature\Discussion;

use App\Models\Role;
use App\Models\SlaPolicy;
use App\Models\Ticket;
use App\Models\User;
use App\Support\RoleRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Setiap layar yang menampilkan Forum Diskusi harus menitipkan IDENTITAS
 * pembacanya.
 *
 * Tanpa itu komponen kembali ke perataan berbasis peran — perilaku lama yang
 * membuat dua orang seperan menumpuk di sisi yang sama. Kegagalannya diam:
 * layarnya tetap terbuka, gelembungnya tetap muncul, hanya sisinya yang salah.
 *
 * Ditulis setelah kejadian nyata: penambahan `viewer` untuk layar Support
 * sempat mendarat di payload DAFTAR tiket, bukan halaman detail, karena pola
 * 'role' + 'currentUser' muncul dua kali di controller yang sama. Halaman
 * detailnya balas 500. Tes yang berhenti di lapisan service tidak melihatnya.
 */
final class ViewerIdentityWiringTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $roleKey): User
    {
        $user = User::factory()->create(['status' => 'active', 'helpdesk_access' => 'enabled']);
        $user->roles()->attach(Role::firstOrCreate(
            ['name' => RoleRegistry::roleNameFor($roleKey)],
            ['type' => 'system', 'status' => 'active'],
        )->id);

        return $user;
    }

    private function ticket(User $requester, ?User $approver = null): Ticket
    {
        $sla = SlaPolicy::create([
            'policy_name' => 'Uji Identitas Pembaca', 'priority' => 'Low', 'service_type' => 'Incident',
            'response_time_minutes' => 1440, 'resolution_time_minutes' => 7200,
            'warning_threshold_percent' => 80, 'status' => 'active',
        ]);

        return Ticket::create([
            'ticket_no' => 'INC-UJI-2026-'.random_int(1000, 9999),
            'title' => 'Tiket uji identitas pembaca',
            'requester_id' => $requester->id,
            'requester_name' => $requester->name,
            'approver_id' => $approver?->id,
            'status' => 'Open',
            'priority' => 'Low',
            'sla_policy_id' => $sla->id,
            'response_time_minutes' => 1440,
            'resolution_time_minutes' => 7200,
            'warning_threshold_percent' => 80,
            'response_due_at' => now()->addDay(),
            'resolution_due_at' => now()->addDays(5),
            'warning_at' => now()->addDays(4),
        ]);
    }

    public function test_layar_requester_menitipkan_identitas_pembacanya(): void
    {
        $requester = $this->user('requester');
        $ticket = $this->ticket($requester);

        $viewer = $this->actingAs($requester)
            ->get(route('requester.tickets.show', $ticket))
            ->assertOk()
            ->viewData('viewer');

        $this->assertSame($requester->id, $viewer['id']);
        $this->assertSame($requester->name, $viewer['name']);
    }

    public function test_layar_approver_menitipkan_identitas_pembacanya(): void
    {
        $requester = $this->user('requester');
        $approver = $this->user('approver');
        $ticket = $this->ticket($requester, $approver);

        $viewer = $this->actingAs($approver)
            ->get(route('approver.tickets.show', $ticket))
            ->assertOk()
            ->viewData('viewer');

        $this->assertSame($approver->id, $viewer['id']);
        $this->assertSame($approver->name, $viewer['name']);
    }
}
