<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\AuditTrail;
use App\Models\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsRole;
use Tests\Concerns\MakesSupportDesks;
use Tests\TestCase;

/**
 * Jejak audit untuk tindakan yang menyentuh TIKET.
 *
 * Sebelum ini seluruh sisi Requester diam: membuat, mengubah, menutup,
 * membuka kembali, dan MENGHAPUS tiket tidak meninggalkan satu baris pun.
 * Begitu juga setiap komentar dan catatan internal dari kelima layar.
 *
 * Yang diuji di sini perilakunya, bukan keberadaan kodenya: tiap tes menembak
 * endpoint sungguhan lalu memeriksa barisnya ada. Menguji "ada pemanggilan
 * AuditTrail::record di berkas X" akan tetap hijau walau barisnya gagal
 * tersimpan karena nilai enum-nya ditolak — yang justru terjadi saat modul
 * `ticket_requester` belum ada.
 */
final class AuditTrailCoverageTest extends TestCase
{
    use ActsAsRole, MakesSupportDesks, RefreshDatabase;

    public function test_catatan_internal_team_lead_tercatat(): void
    {
        $it = $this->deskAgent('it', 'Agung Wijayanto');
        $tiket = $this->deskTicket($it);

        $lead = $this->actingAsRole('team-lead');
        $this->postJson(route('team-lead.tickets.note', $tiket), ['message' => 'Tolong dikejar hari ini.'])
            ->assertCreated();

        $baris = AuditTrail::where('module', 'team_lead')->where('action', 'comment')->latest('id')->first();

        $this->assertNotNull($baris, 'Catatan internal Team Lead harus meninggalkan jejak audit.');
        $this->assertSame($lead->id, $baris->actor_id);
        $this->assertSame($tiket->ticket_no, $baris->target_name);
        $this->assertStringContainsString('Tolong dikejar hari ini.', $baris->description);
    }

    public function test_penghapusan_tiket_tercatat_lengkap_dengan_judulnya(): void
    {
        $requester = $this->actingAsRole('requester');
        $tiket = $this->deskTicket($this->deskAgent('it', 'Agung Wijayanto'), [
            'requester_id' => $requester->id,
            'status' => 'Draft',
            'title' => 'Tiket yang akan dihapus',
        ]);

        $this->deleteJson(route('requester.tickets.destroy', $tiket))->assertOk();

        $baris = AuditTrail::where('module', 'ticket_requester')->where('action', 'delete')->latest('id')->first();

        $this->assertNotNull($baris, 'Penghapusan tiket wajib meninggalkan jejak — tiketnya sendiri sudah tidak ada.');
        $this->assertSame($tiket->ticket_no, $baris->target_name);
        $this->assertSame('Tiket yang akan dihapus', $baris->new_value['judul'] ?? null);
        $this->assertNull(Ticket::find($tiket->id), 'Prasyarat: tiketnya memang terhapus.');
    }

    public function test_penutupan_tiket_menyimpan_nilai_ratingnya(): void
    {
        $requester = $this->actingAsRole('requester');
        $tiket = $this->deskTicket($this->deskAgent('it', 'Agung Wijayanto'), [
            'requester_id' => $requester->id,
            'status' => 'Resolved',
            'resolved_at' => now(),
        ]);

        $this->postJson(route('requester.tickets.close', $tiket), ['rating' => 4, 'note' => 'Sudah beres.'])
            ->assertOk();

        $baris = AuditTrail::where('module', 'ticket_requester')->where('action', 'close')->latest('id')->first();

        $this->assertNotNull($baris);
        $this->assertSame(4, $baris->new_value['rating'] ?? null);
    }

    public function test_komentar_requester_tercatat_pada_modul_kursinya_sendiri(): void
    {
        $requester = $this->actingAsRole('requester');
        $tiket = $this->deskTicket($this->deskAgent('it', 'Agung Wijayanto'), [
            'requester_id' => $requester->id,
        ]);

        $this->post(route('requester.tickets.comments.store', $tiket), ['message' => 'Ada perkembangan?'])
            ->assertCreated();

        $baris = AuditTrail::where('action', 'comment')->latest('id')->first();

        $this->assertNotNull($baris);
        $this->assertSame(
            'ticket_requester',
            $baris->module,
            'Modul mengikuti kursi tempat komentar ditulis, bukan objek yang disentuh.'
        );
    }
}
