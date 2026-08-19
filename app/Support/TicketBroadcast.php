<?php

namespace App\Support;

use App\Models\AuditTrail;
use App\Models\SupportAgent;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Tiket "Lainnya" (requester memilih Layanan tapi tidak ada Subcategory yang
 * cocok) dilempar ke SEMUA PIC BPO Layanan itu, bukan satu agent tetap —
 * beda dari tiket katalog biasa yang langsung dapat satu
 * assigned_agent_id lewat TicketController::resolveAssignedAgentId().
 *
 * Tiket broadcast tetap `assigned_agent_id = null` sampai salah satu PIC
 * benar-benar bertindak (membalas, mulai mengerjakan, dst — lihat
 * SupportBpoController). Siapa pun yang pertama, otomatis jadi pemiliknya;
 * PIC lain diberi tahu supaya tidak dua orang mengerjakan tiket yang sama.
 *
 * Pola yang SAMA PERSIS dipakai lagi untuk tahap kedua: kalau tiket "Lainnya"
 * ini dieskalasi BPO ke IT tanpa ada Subject yang menentukan satu it_agent_id
 * spesifik, dia broadcast lagi — kali ini ke semua PIC IT Layanan itu (lihat
 * escalateBroadcast() di bawah). `escalated_at` adalah penandanya: null berarti
 * masih tahap BPO, terisi berarti sudah tahap IT — assigned_agent_id sendiri
 * dipakai ulang untuk kedua tahap (null = belum diklaim SIAPA PUN di tahap
 * yang sedang berjalan).
 */
class TicketBroadcast
{
    /**
     * PIC yang berhak atas tiket ini di tahap SEKARANG — kosong kalau bukan
     * tiket "Lainnya" (catalog_subject_id terisi) atau Layanannya belum
     * punya PIC di Subject manapun untuk tahap itu.
     *
     * Sumbernya SENGAJA bukan daftar terpisah: PIC broadcast adalah
     * kumpulan PIC unik yang sudah tertaut ke Subject-Subject aktif
     * Layanan ini (ServiceCatalogService::activeBpoAgents()/
     * activeItAgents()) — data yang sama persis dengan yang Admin lihat di
     * tabel Service Catalog, supaya tidak ada dua sumber PIC yang bisa
     * berbeda pendapat.
     *
     * @return Collection<int,SupportAgent>
     */
    public static function eligiblePics(Ticket $ticket): Collection
    {
        // escalated_at menentukan tahap mana yang sedang berjalan — belum
        // dieskalasi berarti masih giliran BPO, sudah berarti giliran IT.
        return $ticket->escalated_at !== null ? self::itPics($ticket) : self::bpoPics($ticket);
    }

    /**
     * PIC IT Layanan tiket ini, TANPA melihat escalated_at — dipakai
     * SupportBpoController::escalate() untuk memastikan ada tujuan SEBELUM
     * memutuskan broadcast. Tanpa pengecekan itu, tiket "Lainnya" di Layanan
     * yang semua Subject aktifnya tidak punya it_agent_id (Level 1,
     * BPO-only) akan hilang total begitu dieskalasi: assigned_agent_id null,
     * escalated_at terisi, tidak ada satu pun PIC IT yang bisa melihat atau
     * membukanya.
     *
     * @return Collection<int,SupportAgent>
     */
    public static function itPics(Ticket $ticket): Collection
    {
        return self::picsFrom($ticket, fn ($service) => $service->activeItAgents());
    }

    /** @return Collection<int,SupportAgent> */
    private static function bpoPics(Ticket $ticket): Collection
    {
        return self::picsFrom($ticket, fn ($service) => $service->activeBpoAgents());
    }

    /** @return Collection<int,SupportAgent> */
    private static function picsFrom(Ticket $ticket, callable $pool): Collection
    {
        if ($ticket->catalog_subject_id !== null || $ticket->service_catalog_service_id === null) {
            return collect();
        }

        $service = $ticket->catalogService;

        if (! $service) {
            return collect();
        }

        // unique('user_id'), bukan cuma get(): orang dobel peran (BPO & IT,
        // lihat SupportBpoController::agentFor()) punya DUA baris SupportAgent
        // untuk akun yang sama — tanpa ini dia bisa muncul dua kali dan
        // dinotifikasi dua kali untuk tiket yang sama.
        return $pool($service)->get()->unique('user_id');
    }

    /**
     * Boleh melihat/bertindak atas tiket ini: sudah jadi miliknya, ATAU
     * tiketnya broadcast yang belum diklaim siapa pun dan dia salah satu
     * PIC yang berhak.
     */
    public static function canAct(Ticket $ticket, SupportAgent $agent): bool
    {
        if ($ticket->assigned_agent_id === $agent->id) {
            return true;
        }

        if ($ticket->assigned_agent_id !== null) {
            return false;
        }

        // user_id, bukan id baris SupportAgent — lihat catatan di
        // eligiblePics(): orang dobel peran punya dua baris, dan yang
        // dipakai claim/akses bisa jadi baris lain dari yang muncul di
        // daftar PIC (unique() cuma menyimpan salah satu).
        return self::eligiblePics($ticket)->contains('user_id', $agent->user_id);
    }

    /**
     * Kalau tiket ini broadcast dan belum diklaim, dan agent ini berhak —
     * jadikan miliknya, dan beri tahu PIC lain supaya tidak dua orang
     * mengerjakan tiket yang sama. Dipakai di kedua tahap (BPO maupun IT
     * setelah eskalasi) — eligiblePics() sendiri yang memilih pool-nya.
     *
     * Aman dipanggil untuk tiket yang bukan broadcast atau sudah diklaim —
     * tidak melakukan apa-apa, jadi bisa ditaruh di awal setiap aksi Support
     * tanpa perlu pengecekan tambahan di pemanggilnya.
     */
    public static function claimIfUnclaimed(Ticket $ticket, User $actorUser, SupportAgent $agent): void
    {
        if ($ticket->assigned_agent_id !== null) {
            return;
        }

        $pics = self::eligiblePics($ticket);

        if (! $pics->contains('user_id', $agent->user_id)) {
            return;
        }

        $ticket->update(['assigned_agent_id' => $agent->id]);

        AuditTrail::record($actorUser, [
            'module' => 'ticket_support',
            'action' => 'claim',
            'target_type' => 'ticket',
            'target_id' => $ticket->id,
            'target_name' => $ticket->ticket_no,
            'old_value' => ['assigned_agent' => null],
            'new_value' => ['assigned_agent' => $agent->name],
            'description' => "{$actorUser->name} mengklaim tiket \"{$ticket->ticket_no}\" (broadcast) dari ".count($pics).' PIC.',
        ]);

        $others = $pics->reject(fn (SupportAgent $pic) => $pic->user_id === $agent->user_id);

        $others->each(function (SupportAgent $pic) use ($ticket, $agent) {
            if (! $pic->user_id) {
                return;
            }

            NotificationService::notify(
                User::find($pic->user_id),
                NotificationService::roleForAgent($pic),
                $ticket,
                'ticket_claimed_by_other',
                'Tiket Sudah Ditangani',
                "Tiket {$ticket->ticket_no} \"{$ticket->title}\" sudah ditangani oleh {$agent->name}."
            );
        });
    }

    /**
     * BPO mengeskalasi tiket "Lainnya" yang tidak punya satu it_agent_id
     * spesifik (tidak ada Subject yang menentukannya) — daripada menebak
     * satu agent IT, lempar ke SEMUA PIC IT Layanan ini, persis pola yang
     * sama dengan broadcast BPO di awal. Tiket kembali `assigned_agent_id
     * = null` (sekarang berarti "belum diklaim IT manapun", bukan "belum
     * diklaim BPO" lagi — dibedakan lewat escalated_at yang baru diisi).
     *
     * STATUS IKUT KEMBALI KE "Open": tahap IT baru dimulai, belum ada satu
     * pun orang IT yang mengerjakannya. Status warisan tahap BPO ("In
     * Progress" kalau BPO sempat menekan Kerjakan Sekarang) berbohong di
     * layar PIC IT — tiketnya tampak sedang dikerjakan padahal tidak ada
     * pemiliknya, tepat di sebelah tulisan "belum ada PIC". Akibatnya lebih
     * dari sekadar label: popup "Mulai kerjakan tiket ini?" cuma muncul
     * untuk tiket Open, dan SupportController::start() menolak status selain
     * Open dengan 422 — tanpa baris ini PIC IT tidak punya jalan untuk
     * mengklaim tiketnya lewat tombolnya sendiri.
     */
    public static function escalateBroadcast(Ticket $ticket, User $bpoUser, SupportAgent $bpoAgent, string $note): void
    {
        $ticket->update([
            'assigned_agent_id' => null,
            'status' => 'Open',
            'escalated_at' => now(),
            'escalation_note' => $note,
        ]);

        AuditTrail::record($bpoUser, [
            'module' => 'ticket_support',
            'action' => 'escalate',
            'target_type' => 'ticket',
            'target_id' => $ticket->id,
            'target_name' => $ticket->ticket_no,
            'old_value' => ['assigned_agent' => $bpoAgent->name],
            'new_value' => ['assigned_agent' => 'Broadcast PIC IT', 'catatan' => $note],
            'description' => "{$bpoUser->name} mengeskalasi tiket \"{$ticket->ticket_no}\" (broadcast) ke semua PIC IT Layanan {$ticket->service_name}: {$note}",
        ]);

        self::eligiblePics($ticket)->each(function (SupportAgent $pic) use ($ticket) {
            if (! $pic->user_id) {
                return;
            }

            NotificationService::notify(
                User::find($pic->user_id),
                NotificationService::roleForAgent($pic),
                $ticket,
                'ticket_incoming_escalation',
                'Tiket Eskalasi Menunggu PIC IT',
                "Tiket {$ticket->ticket_no} \"{$ticket->title}\" ({$ticket->service_name}) dieskalasi dari Support BPO — belum ada yang menangani, siapa pun dari tim IT bisa mengambilnya."
            );
        });
    }
}
