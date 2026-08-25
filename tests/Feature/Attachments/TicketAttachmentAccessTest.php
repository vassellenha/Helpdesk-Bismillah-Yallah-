<?php

declare(strict_types=1);

namespace Tests\Feature\Attachments;

use App\Models\Role;
use App\Models\SlaPolicy;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\User;
use App\Support\RoleRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Lampiran tiket dulu disajikan langsung dari /storage/ticket-attachments/...
 * — disk publik, tanpa satu pun pemeriksaan hak akses. Halaman tiketnya
 * dijaga middleware `auth` dan dibalas 403 untuk orang yang bukan pemiliknya,
 * tetapi berkasnya sendiri menjawab 200 kepada siapa saja, termasuk pengunjung
 * yang sama sekali belum login.
 *
 * Nama berkasnya memang 40 karakter acak, jadi tidak bisa ditebak. Tapi itu
 * kerahasiaan yang bocor sekali lalu bocor selamanya: tautan tersimpan di
 * riwayat peramban, tertempel di percakapan, tercatat di log proxy. Sekali
 * tersebar ia tidak bisa dicabut — pegawai yang aksesnya sudah dimatikan
 * Administrator pun tetap bisa mengunduhnya, dan tidak ada catatan siapa
 * mengambil apa.
 *
 * Sekarang berkasnya duduk di disk privat dan hanya keluar lewat satu rute
 * yang menanyakan "siapa Anda, dan berhakkah Anda melihat tiket ini".
 */
final class TicketAttachmentAccessTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $email, ?string $roleKey = 'requester'): User
    {
        $user = User::factory()->create([
            'email' => $email,
            'username' => $email,
            'status' => 'active',
            'helpdesk_access' => 'enabled',
        ]);

        if ($roleKey !== null) {
            $user->roles()->attach(Role::firstOrCreate(
                ['name' => RoleRegistry::roleNameFor($roleKey)],
                ['type' => 'system', 'status' => 'active'],
            )->id);
        }

        return $user;
    }

    private function ticketWithAttachment(User $requester, ?User $approver = null): array
    {
        Storage::fake('local');

        $ticket = Ticket::create([
            'ticket_no' => 'INC-UJI-2026-'.random_int(1000, 9999),
            'title' => 'Tiket uji lampiran',
            'requester_id' => $requester->id,
            'requester_name' => $requester->name,
            'approver_id' => $approver?->id,
            'status' => 'Open',
            'priority' => 'Low',
            'sla_policy_id' => $this->slaPolicyId(),
            'response_time_minutes' => 1440,
            'resolution_time_minutes' => 7200,
            'warning_threshold_percent' => 80,
            'response_due_at' => now()->addDay(),
            'resolution_due_at' => now()->addDays(5),
            'warning_at' => now()->addDays(4),
        ]);

        Storage::disk('local')->put('ticket-attachments/rahasia.png', 'isi-berkas-rahasia');

        $attachment = $ticket->attachments()->create([
            'name' => 'rahasia.png',
            'path' => 'ticket-attachments/rahasia.png',
        ]);

        return [$ticket, $attachment];
    }

    private ?int $slaPolicyId = null;

    private function slaPolicyId(): int
    {
        return $this->slaPolicyId ??= SlaPolicy::create([
            'policy_name' => 'Uji Lampiran',
            'priority' => 'Low',
            'service_type' => 'Incident',
            'response_time_minutes' => 1440,
            'resolution_time_minutes' => 7200,
            'warning_threshold_percent' => 80,
            'status' => 'active',
        ])->id;
    }

    private function url(Ticket $ticket, TicketAttachment $attachment): string
    {
        return route('tickets.attachment.show', [$ticket, $attachment]);
    }

    public function test_pengunjung_yang_belum_login_tidak_bisa_mengunduh_lampiran(): void
    {
        [$ticket, $attachment] = $this->ticketWithAttachment($this->user('pemilik@adhi.co.id'));

        $this->get($this->url($ticket, $attachment))->assertRedirect(route('login'));
    }

    public function test_pemilik_tiket_bisa_mengunduh_lampirannya(): void
    {
        $pemilik = $this->user('pemilik@adhi.co.id');
        [$ticket, $attachment] = $this->ticketWithAttachment($pemilik);

        $response = $this->actingAs($pemilik)->get($this->url($ticket, $attachment));

        $response->assertOk();
        $this->assertSame('isi-berkas-rahasia', $response->streamedContent());
    }

    public function test_requester_lain_ditolak(): void
    {
        [$ticket, $attachment] = $this->ticketWithAttachment($this->user('pemilik@adhi.co.id'));
        $orangLain = $this->user('orang.lain@adhi.co.id');

        $this->actingAs($orangLain)->get($this->url($ticket, $attachment))->assertForbidden();
    }

    public function test_approver_tiket_boleh_melihat(): void
    {
        $pemilik = $this->user('pemilik@adhi.co.id');
        $approver = $this->user('approver@adhi.co.id', 'approver');
        [$ticket, $attachment] = $this->ticketWithAttachment($pemilik, $approver);

        $this->actingAs($approver)->get($this->url($ticket, $attachment))->assertOk();
    }

    public function test_administrator_boleh_melihat(): void
    {
        [$ticket, $attachment] = $this->ticketWithAttachment($this->user('pemilik@adhi.co.id'));
        $admin = $this->user('admin@adhi.co.id', 'admin');

        $this->actingAs($admin)->get($this->url($ticket, $attachment))->assertOk();
    }

    /**
     * Nomor tiket di URL harus benar-benar cocok dengan lampirannya. Tanpa
     * pemeriksaan ini, siapa pun yang punya satu tiket sendiri bisa memasang
     * nomor tiketnya sendiri di depan id lampiran milik orang lain, dan
     * gerbangnya akan meloloskannya.
     */
    public function test_lampiran_milik_tiket_lain_tidak_bisa_diambil_lewat_tiket_sendiri(): void
    {
        $korban = $this->user('korban@adhi.co.id');
        [, $attachmentKorban] = $this->ticketWithAttachment($korban);

        $penyerang = $this->user('penyerang@adhi.co.id');
        [$ticketPenyerang] = $this->ticketWithAttachment($penyerang);

        $this->actingAs($penyerang)
            ->get(route('tickets.attachment.show', [$ticketPenyerang, $attachmentKorban]))
            ->assertNotFound();
    }

    public function test_berkas_tidak_lagi_disimpan_di_disk_publik(): void
    {
        $pemilik = $this->user('pemilik@adhi.co.id');
        [, $attachment] = $this->ticketWithAttachment($pemilik);

        $this->assertStringNotContainsString('/storage/', route('tickets.attachment.show', [$attachment->ticket, $attachment]));
        $this->assertFalse(Storage::disk('public')->exists($attachment->path));
    }
}
