<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\AuditTrail;
use App\Models\Ticket;
use App\Models\User;

/**
 * Pencatatan Audit Trail untuk tindakan atas TIKET.
 *
 * Ada supaya bentuk barisnya seragam. Lima layar menulis komentar ke tiket yang
 * sama — Requester, Support IT, Support BPO, Approver, Team Lead — dan sebelum
 * ini tidak satu pun dari mereka mencatat apa pun. Kalau kelimanya diperbaiki
 * dengan menyalin blok AuditTrail::record, yang lahir adalah lima deskripsi
 * yang sedikit berbeda satu sama lain, dan penelusuran audit jadi bergantung
 * pada layar mana yang kebetulan dipakai.
 *
 * MODUL DITENTUKAN PEMANGGIL, bukan disimpulkan di sini: satu orang bisa
 * memegang beberapa peran, jadi yang menentukan bukan siapa dia melainkan dari
 * kursi mana ia bertindak.
 */
final class TicketAudit
{
    /** Panjang komentar yang ikut disalin ke deskripsi audit. */
    private const CUPLIKAN = 120;

    /**
     * Komentar atau catatan internal pada sebuah tiket.
     *
     * Isi lengkapnya masuk ke `new_value`, sementara `description` hanya
     * memuat cuplikan: kolom deskripsi dibaca sebagai satu baris di tabel
     * Audit Trail, dan komentar 3.000 karakter akan merusak barisnya.
     */
    public static function comment(User $actor, string $module, Ticket $ticket, string $peran, string $pesan): void
    {
        AuditTrail::record($actor, [
            'module' => $module,
            'action' => 'comment',
            'target_type' => 'ticket',
            'target_id' => $ticket->id,
            'target_name' => $ticket->ticket_no,
            'new_value' => ['peran' => $peran, 'pesan' => $pesan],
            'description' => "{$actor->name} ({$peran}) menulis catatan pada tiket \"{$ticket->ticket_no}\": ".self::cuplik($pesan),
        ]);
    }

    /**
     * Tindakan lain atas tiket yang bentuknya sederhana — satu aksi, satu
     * kalimat. Yang butuh old_value/new_value menulis AuditTrail::record
     * sendiri supaya perubahannya terbaca utuh.
     *
     * @param  array<string,mixed>|null  $baru
     */
    public static function action(User $actor, string $module, string $action, Ticket $ticket, string $description, ?array $baru = null): void
    {
        AuditTrail::record($actor, [
            'module' => $module,
            'action' => $action,
            'target_type' => 'ticket',
            'target_id' => $ticket->id,
            'target_name' => $ticket->ticket_no,
            'new_value' => $baru,
            'description' => $description,
        ]);
    }

    private static function cuplik(string $pesan): string
    {
        $rapi = trim(preg_replace('/\s+/', ' ', $pesan) ?? $pesan);

        return mb_strlen($rapi) > self::CUPLIKAN
            ? mb_substr($rapi, 0, self::CUPLIKAN).'…'
            : $rapi;
    }
}
