<?php

declare(strict_types=1);

namespace Tests\Feature\Discussion;

use App\Models\SlaPolicy;
use App\Models\Ticket;
use App\Models\User;
use App\Support\TicketDiscussion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Komentar Forum Diskusi harus tahu SIAPA yang menulisnya, bukan cuma perannya.
 *
 * Perataan gelembung dulu diputuskan dari `author_role`: apa pun yang berlabel
 * "Support" ditaruh di kanan. Support IT dan Support BPO sama-sama menyimpan
 * label itu — disengaja, karena keduanya memang "Support" di mata Requester —
 * sehingga di layar Support keduanya menumpuk di sisi yang sama. Support IT
 * melihat pesan Support BPO seolah tulisannya sendiri, dan percakapan dua pihak
 * terbaca seperti monolog.
 *
 * Nama tidak bisa dipakai sebagai penggantinya: direktori pegawai perusahaan
 * ini memuat nama yang benar-benar kembar. Yang membedakan orang hanya id-nya.
 */
final class CommentAuthorIdentityTest extends TestCase
{
    use RefreshDatabase;

    private function ticket(User $requester): Ticket
    {
        $sla = SlaPolicy::create([
            'policy_name' => 'Uji Identitas Komentar',
            'priority' => 'Low',
            'service_type' => 'Incident',
            'response_time_minutes' => 1440,
            'resolution_time_minutes' => 7200,
            'warning_threshold_percent' => 80,
            'status' => 'active',
        ]);

        return Ticket::create([
            'ticket_no' => 'INC-UJI-2026-'.random_int(1000, 9999),
            'title' => 'Tiket uji identitas komentar',
            'requester_id' => $requester->id,
            'requester_name' => $requester->name,
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

    public function test_komentar_menyimpan_id_penulisnya(): void
    {
        $requester = User::factory()->create();
        $penulis = User::factory()->create(['name' => 'Dummy SINTA 3']);

        $comment = TicketDiscussion::store(
            $this->ticket($requester),
            $penulis,
            'Support',
            'Support IT',
            ['message' => 'tes'],
            null,
        );

        $this->assertSame($penulis->id, $comment->author_id);
    }

    public function test_id_penulis_ikut_dikirim_ke_layar(): void
    {
        $requester = User::factory()->create();
        $penulis = User::factory()->create(['name' => 'Dummy SINTA 3']);

        $comment = TicketDiscussion::store(
            $this->ticket($requester),
            $penulis,
            'Support',
            'Support IT',
            ['message' => 'tes'],
            null,
        );

        $this->assertSame($penulis->id, TicketDiscussion::present($comment)['authorId']);
    }

    /**
     * INTI PERKARA: dua orang Support berbeda pada satu tiket harus terbaca
     * sebagai dua orang, bukan satu.
     */
    public function test_dua_support_berbeda_punya_id_berbeda_walau_labelnya_sama(): void
    {
        $requester = User::factory()->create();
        $ticket = $this->ticket($requester);

        $bpo = User::factory()->create(['name' => 'Dummy SINTA']);
        $it = User::factory()->create(['name' => 'Dummy SINTA 3']);

        $dariBpo = TicketDiscussion::store($ticket, $bpo, 'Support', 'Support BPO', ['message' => 'dari bpo'], null);
        $dariIt = TicketDiscussion::store($ticket, $it, 'Support', 'Support IT', ['message' => 'dari it'], null);

        // Labelnya memang sengaja sama — itu yang dilihat Requester.
        $this->assertSame('Support', $dariBpo->author_role);
        $this->assertSame('Support', $dariIt->author_role);

        // Tapi identitasnya harus berbeda, dan itulah yang dipakai layar.
        $this->assertNotSame(
            TicketDiscussion::present($dariBpo)['authorId'],
            TicketDiscussion::present($dariIt)['authorId'],
        );
    }

    /** Komentar lama tidak punya id penulis — itu sah, dan tidak boleh error. */
    public function test_komentar_lama_tanpa_id_penulis_tetap_bisa_ditampilkan(): void
    {
        $requester = User::factory()->create();

        $lama = $this->ticket($requester)->comments()->create([
            'author_name' => 'Dummy SINTA',
            'author_role' => 'Support',
            'message' => 'komentar sebelum kolom author_id ada',
        ]);

        $this->assertNull(TicketDiscussion::present($lama)['authorId']);
    }
}
