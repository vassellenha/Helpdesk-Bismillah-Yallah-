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
 */
class TicketBroadcast
{
    /**
     * PIC BPO yang berhak atas tiket ini — kosong kalau bukan tiket
     * "Lainnya" (catalog_subject_id terisi) atau Layanannya belum punya
     * PIC BPO di Subject manapun.
     *
     * Sumbernya SENGAJA bukan daftar terpisah: PIC broadcast adalah
     * kumpulan PIC BPO unik yang sudah tertaut ke Subject-Subject aktif
     * Layanan ini (ServiceCatalogService::activeBpoAgents()) — data yang
     * sama persis dengan yang Admin lihat di tabel Service Catalog, supaya
     * tidak ada dua sumber PIC yang bisa berbeda pendapat.
     *
     * @return Collection<int,SupportAgent>
     */
    public static function eligiblePics(Ticket $ticket): Collection
    {
        if ($ticket->catalog_subject_id !== null || $ticket->service_catalog_service_id === null) {
            return collect();
        }

        // unique('user_id'), bukan cuma get(): orang dobel peran (BPO & IT,
        // lihat SupportBpoController::agentFor()) punya DUA baris SupportAgent
        // untuk akun yang sama — tanpa ini dia bisa muncul dua kali dan
        // dinotifikasi dua kali untuk tiket yang sama.
        return $ticket->catalogService?->activeBpoAgents()->get()->unique('user_id') ?? collect();
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
     * mengerjakan tiket yang sama.
     *
     * Aman dipanggil untuk tiket yang bukan broadcast atau sudah diklaim —
     * tidak melakukan apa-apa, jadi bisa ditaruh di awal setiap aksi Support
     * BPO tanpa perlu pengecekan tambahan di pemanggilnya.
     */
    public static function claimIfUnclaimed(Ticket $ticket, User $bpoUser, SupportAgent $agent): void
    {
        if ($ticket->assigned_agent_id !== null) {
            return;
        }

        $pics = self::eligiblePics($ticket);

        if (! $pics->contains('user_id', $agent->user_id)) {
            return;
        }

        $ticket->update(['assigned_agent_id' => $agent->id]);

        AuditTrail::record($bpoUser, [
            'module' => 'ticket_support',
            'action' => 'claim',
            'target_type' => 'ticket',
            'target_id' => $ticket->id,
            'target_name' => $ticket->ticket_no,
            'old_value' => ['assigned_agent' => null],
            'new_value' => ['assigned_agent' => $agent->name],
            'description' => "{$bpoUser->name} mengklaim tiket \"{$ticket->ticket_no}\" (broadcast) dari ".count($pics).' PIC.',
        ]);

        $others = $pics->reject(fn (SupportAgent $pic) => $pic->user_id === $agent->user_id);

        $others->each(function (SupportAgent $pic) use ($ticket, $agent) {
            if (! $pic->user_id) {
                return;
            }

            NotificationService::notify(
                User::find($pic->user_id),
                $ticket,
                'ticket_claimed_by_other',
                'Tiket Sudah Ditangani',
                "Tiket {$ticket->ticket_no} \"{$ticket->title}\" sudah ditangani oleh {$agent->name}."
            );
        });
    }
}
