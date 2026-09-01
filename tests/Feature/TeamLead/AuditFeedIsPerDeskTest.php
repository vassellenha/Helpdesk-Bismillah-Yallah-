<?php

declare(strict_types=1);

namespace Tests\Feature\TeamLead;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsRole;
use Tests\Concerns\MakesSupportDesks;
use Tests\TestCase;

/**
 * Tab Riwayat menampilkan jejak tindakan korektif. Cabang `module = 'team_lead'`
 * pada kueri-nya tidak menyaring apa pun — dengan satu Team Lead itu benar,
 * dengan dua desk itu berarti tindakan atas tim lain ikut terbaca.
 *
 * Yang bocor di sini bukan angka melainkan penilaian kinerja: teguran dan
 * pemindahan PIC adalah catatan tentang orang, dan atasan desk sebelah tidak
 * berkepentingan membacanya.
 */
final class AuditFeedIsPerDeskTest extends TestCase
{
    use ActsAsRole, MakesSupportDesks, RefreshDatabase;

    public function test_tindakan_team_lead_it_tidak_muncul_di_riwayat_team_lead_bpo(): void
    {
        $it = $this->deskAgent('it', 'Agung Wijayanto');
        $this->deskAgent('bpo', 'Denny Firmansyah');
        $tiketIt = $this->deskTicket($it);

        $this->deskSlaPolicy('High')->update(['status' => 'active']);

        $this->actingAsRole('team-lead');
        $this->postJson(route('team-lead.tickets.raise-priority', $tiketIt), ['priority' => 'High'])->assertOk();

        $riwayatIt = collect($this->getJson(route('team-lead.data-feed'))->json('auditRows'))->pluck('ticket');
        $this->assertContains($tiketIt->ticket_no, $riwayatIt->all());

        $this->actingAsRole('team-lead-bpo');
        $riwayatBpo = collect($this->getJson(route('team-lead-bpo.data-feed'))->json('auditRows'))->pluck('ticket');
        $this->assertNotContains(
            $tiketIt->ticket_no,
            $riwayatBpo->all(),
            'Riwayat Team Lead BPO tidak boleh memuat tindakan atas tiket Tim IT.'
        );
    }
}
