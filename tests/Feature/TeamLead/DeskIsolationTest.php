<?php

declare(strict_types=1);

namespace Tests\Feature\TeamLead;

use App\Models\SupportAgent;
use App\Models\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsRole;
use Tests\Concerns\MakesSupportDesks;
use Tests\TestCase;

/**
 * Team Lead kini ada dua — satu per desk Support — dan inilah tes yang menjaga
 * batas di antara keduanya.
 *
 * Selama hanya ada SATU Team Lead, beberapa panel mengambil data tanpa saringan
 * tim sama sekali (tabel eskalasi, lonceng, feed Riwayat) dan itu tidak pernah
 * terlihat salah: semua tiket memang miliknya. Begitu desk-nya dua, saringan
 * yang hilang itu berubah menjadi kebocoran — dan yang bocor bukan cuma angka
 * di layar, melainkan WEWENANG: menegur, memindahkan, dan menaikkan prioritas
 * pekerjaan orang yang bukan bawahannya.
 *
 * Karena itu tiap tindakan diuji dua arah, bukan satu.
 */
final class DeskIsolationTest extends TestCase
{
    use ActsAsRole, MakesSupportDesks, RefreshDatabase;

    public function test_dashboard_tiap_desk_hanya_memuat_tiket_timnya_sendiri(): void
    {
        [$tiketIt, $tiketBpo, $bpoAgent] = $this->duaDesk();

        $this->actingAsRole('team-lead');
        $it = $this->getJson(route('team-lead.data-feed'))->assertOk();

        $this->assertTrue($this->punyaTiket($it->json('monitorRows'), $tiketIt));
        $this->assertFalse(
            $this->punyaTiket($it->json('monitorRows'), $tiketBpo),
            'Tiket BPO tidak boleh muncul di dashboard Team Lead IT.'
        );

        $this->masukSebagaiLeadBpoYangMengawasi($bpoAgent);
        $bpo = $this->getJson(route('team-lead-bpo.data-feed'))->assertOk();

        $this->assertTrue($this->punyaTiket($bpo->json('monitorRows'), $tiketBpo));
        $this->assertFalse(
            $this->punyaTiket($bpo->json('monitorRows'), $tiketIt),
            'Tiket IT tidak boleh muncul di dashboard Team Lead BPO.'
        );
    }

    public function test_daftar_agent_tiap_desk_tidak_memuat_petugas_tim_lain(): void
    {
        [, , $bpoAgent] = $this->duaDesk();

        $this->actingAsRole('team-lead');
        $namaIt = collect($this->getJson(route('team-lead.data-feed'))->json('agentOptions'))->pluck('name');
        $this->assertContains('Agung Wijayanto', $namaIt->all());
        $this->assertNotContains('Denny Firmansyah', $namaIt->all());

        $this->masukSebagaiLeadBpoYangMengawasi($bpoAgent);
        $namaBpo = collect($this->getJson(route('team-lead-bpo.data-feed'))->json('agentOptions'))->pluck('name');
        $this->assertContains('Denny Firmansyah', $namaBpo->all());
        $this->assertNotContains(
            'Agung Wijayanto',
            $namaBpo->all(),
            'Team Lead BPO tidak boleh bisa memilih petugas IT sebagai tujuan pemindahan.'
        );
    }

    public function test_team_lead_bpo_ditolak_menindak_tiket_it(): void
    {
        [$tiketIt] = $this->duaDesk();
        $this->actingAsRole('team-lead-bpo');

        $this->postJson(route('team-lead-bpo.tickets.raise-priority', $tiketIt), ['priority' => 'High'])
            ->assertStatus(403);
        $this->postJson(route('team-lead-bpo.tickets.remind', $tiketIt), ['message' => 'tolong dikerjakan'])
            ->assertStatus(403);

        $this->assertSame('Medium', $tiketIt->fresh()->priority);
    }

    public function test_team_lead_it_ditolak_menindak_tiket_bpo(): void
    {
        [, $tiketBpo] = $this->duaDesk();
        $this->actingAsRole('team-lead');

        $this->postJson(route('team-lead.tickets.raise-priority', $tiketBpo), ['priority' => 'High'])
            ->assertStatus(403);
        $this->getJson(route('team-lead.tickets.data', $tiketBpo))->assertStatus(403);

        $this->assertSame('Medium', $tiketBpo->fresh()->priority);
    }

    public function test_tiap_desk_hanya_boleh_membuka_layarnya_sendiri(): void
    {
        $this->actingAsRole('team-lead');
        $this->get(route('dashboard.team-lead-bpo'))->assertStatus(403);

        $this->actingAsRole('team-lead-bpo');
        $this->get(route('dashboard.team-lead'))->assertStatus(403);
        $this->get(route('dashboard.team-lead-bpo'))->assertOk();
    }

    /**
     * Setelah BPO mengeskalasi, tiketnya jadi milik Tim IT. Team Lead BPO masih
     * MELIHAT-nya sebagai catatan bahwa timnya pernah memegangnya, tapi tidak
     * boleh lagi menindaknya — kalau boleh, satu tiket punya dua atasan.
     *
     * `escalated_by_agent_id` yang menentukan "timnya": setelah eskalasi, PIC
     * tiket sudah orang IT, jadi hanya jejak PIC BPO asal yang bisa menjawab
     * Team Lead BPO mana yang berhak membacanya.
     */
    public function test_tiket_yang_sudah_dieskalasi_hanya_bisa_dilihat_team_lead_bpo(): void
    {
        [$tiketIt, , $bpoAgent] = $this->duaDesk();
        $tiketIt->update([
            'escalated_at' => now(),
            'escalation_note' => 'perlu penanganan IT',
            'escalated_by_agent_id' => $bpoAgent->id,
        ]);

        $this->masukSebagaiLeadBpoYangMengawasi($bpoAgent);
        $data = $this->getJson(route('team-lead-bpo.data-feed'))->assertOk();

        $this->assertContains(
            $tiketIt->ticket_no,
            collect($data->json('escalations'))->pluck('id')->all(),
            'Eskalasi yang dilepas timnya harus tetap terlihat sebagai catatan.'
        );
        $this->assertSame('out', $data->json('escalationDirection'));

        $this->postJson(route('team-lead-bpo.tickets.reassign', $tiketIt), [
            'agent_id' => 1, 'reason' => 'coba ambil alih',
        ])->assertStatus(403);
    }

    /**
     * Klausa "tiket siaran yang belum diklaim" adalah mekanisme milik Tim IT
     * saja: BPO tidak punya padanannya. Kalau klausa itu ikut berlaku untuk
     * desk BPO, yang ditambahkannya bukan tiket BPO melainkan tiket IT yang
     * kebetulan belum berpemilik — persis kelompok tiket yang paling butuh
     * ditangani, muncul di layar atasan yang tidak berwenang atasnya.
     */
    public function test_tiket_siaran_it_yang_belum_diklaim_tidak_bocor_ke_desk_bpo(): void
    {
        $this->deskAgent('it', 'Agung Wijayanto');
        $bpo = $this->deskAgent('bpo', 'Denny Firmansyah');

        $siaran = $this->deskTicket($bpo, [
            'assigned_agent_id' => null,
            'catalog_subject_id' => null,
            'escalated_at' => now(),
        ]);

        $this->actingAsRole('team-lead');
        $this->assertTrue(
            $this->punyaTiket($this->getJson(route('team-lead.data-feed'))->json('monitorRows'), $siaran),
            'Prasyarat: siaran IT yang belum diklaim memang milik Team Lead IT.'
        );

        $this->actingAsRole('team-lead-bpo');
        $this->assertFalse(
            $this->punyaTiket($this->getJson(route('team-lead-bpo.data-feed'))->json('monitorRows'), $siaran),
            'Siaran ke Tim IT tidak boleh muncul di dashboard Team Lead BPO.'
        );
        $this->getJson(route('team-lead-bpo.tickets.data', $siaran))->assertStatus(403);
    }

    /** @return array{0:Ticket,1:Ticket,2:SupportAgent} */
    private function duaDesk(): array
    {
        $it = $this->deskAgent('it', 'Agung Wijayanto');
        $bpo = $this->deskAgent('bpo', 'Denny Firmansyah');

        return [$this->deskTicket($it), $this->deskTicket($bpo), $bpo];
    }

    /**
     * Masuk sebagai Team Lead BPO YANG PUNYA CAKUPAN atas petugas ini.
     *
     * Sejak cakupan dibagi per Subkategori (App\Support\TeamLeadScope),
     * Subkategori yang belum ditugaskan tidak terbaca Team Lead manapun —
     * jadi tanpa penugasan, dashboardnya kosong dan tes ini menguji layar
     * kosong alih-alih pemisahan desk.
     */
    private function masukSebagaiLeadBpoYangMengawasi(SupportAgent $bpo): void
    {
        $lead = $this->actingAsRole('team-lead-bpo');
        $this->deskScope($lead, $bpo);
        $this->actingAsUserWithRoles($lead, 'team-lead-bpo');
    }

    /** @param  array<int,array<string,mixed>>|null  $rows */
    private function punyaTiket(?array $rows, Ticket $ticket): bool
    {
        return collect($rows ?? [])->pluck('id')->contains($ticket->ticket_no);
    }
}
