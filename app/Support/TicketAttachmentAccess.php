<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Ticket;
use App\Models\User;

/**
 * Siapa yang boleh membuka lampiran sebuah tiket.
 *
 * Sebelum ini pertanyaannya tidak pernah diajukan: berkas duduk di disk publik
 * dan disajikan langsung dari /storage/..., jadi siapa pun yang memegang
 * tautannya bisa mengunduh — termasuk yang belum login sama sekali. Nama
 * berkasnya acak 40 karakter, tapi itu kerahasiaan yang bocor sekali lalu
 * bocor selamanya, dan tidak bisa dicabut seperti mencabut akses akun.
 *
 * Aturannya sengaja ditulis ulang di sini, bukan menumpang gerbang milik
 * masing-masing layar. Gerbang di RequesterController hanya tahu requester,
 * gerbang di SupportController hanya tahu agent — sedangkan satu berkas bisa
 * sah dibuka oleh keduanya. Menaruhnya di satu tempat berarti hanya ada satu
 * daftar yang perlu diperiksa saat orang bertanya "siapa saja yang bisa lihat
 * lampiran ini".
 */
final class TicketAttachmentAccess
{
    public static function allows(Ticket $ticket, ?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        // Akun yang sudah dinonaktifkan Administrator tidak boleh mengambil
        // apa pun, walau sesinya masih hidup.
        if (! $user->isActive()) {
            return false;
        }

        if ($ticket->requester_id === $user->id) {
            return true;
        }

        if ($ticket->approver_id !== null && $ticket->approver_id === $user->id) {
            return true;
        }

        // Administrator dan Team Lead memang punya layar yang menampilkan
        // seluruh tiket, jadi menahan lampirannya hanya akan membuat layar itu
        // berlubang tanpa menambah keamanan apa pun.
        if (self::hasAnyRole($user, ['admin', 'team-lead', 'team-lead-bpo'])) {
            return true;
        }

        return self::isHandlingSupport($ticket, $user);
    }

    /**
     * Petugas Support: yang sudah memegang tiketnya, atau yang termasuk kumpulan
     * PIC yang berhak mengklaimnya. Keduanya hanya berlaku setelah tiket benar-
     * benar diteruskan ke Support — selama masih Draft/Returned/Waiting for
     * Approval/Rejected, tiket itu belum pernah sampai ke meja mereka.
     */
    private static function isHandlingSupport(Ticket $ticket, User $user): bool
    {
        if (in_array($ticket->status, Ticket::NOT_YET_RELEASED_STATUSES, true)) {
            return false;
        }

        if ($ticket->assignedAgent && $ticket->assignedAgent->user_id === $user->id) {
            return true;
        }

        return TicketBroadcast::eligiblePics($ticket)->contains('user_id', $user->id);
    }

    /** @param array<int,string> $roleKeys */
    private static function hasAnyRole(User $user, array $roleKeys): bool
    {
        $names = array_map(fn (string $key) => RoleRegistry::roleNameFor($key), $roleKeys);

        return $user->roles->contains(fn ($role) => in_array($role->name, $names, true));
    }
}
