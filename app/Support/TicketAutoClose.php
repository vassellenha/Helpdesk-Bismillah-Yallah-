<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\AuditTrail;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Menutup tiket yang sudah Resolved tapi tidak dikonfirmasi requester dalam
 * tenggang yang berlaku (config `helpdesk.auto_close_resolved_after_days`).
 *
 * Terpisah dari perintah artisan-nya supaya bisa diuji tanpa lewat konsol, dan
 * supaya kalau nanti ada pemicu lain (tombol "tutup semua yang menggantung" di
 * konsol Admin, misalnya) aturannya tidak ditulis ulang di tempat kedua.
 */
final class TicketAutoClose
{
    /**
     * @return int Jumlah tiket yang benar-benar ditutup pada jalan ini.
     */
    public static function sweep(): int
    {
        $days = Ticket::autoCloseAfterDays();

        if ($days <= 0) {
            return 0;
        }

        $batas = Carbon::now()->subDays($days);

        // Penyaringan dilakukan di basis data, bukan dengan memuat semua tiket
        // lalu menyaring di PHP: tabel ini tumbuh terus dan penyapu berjalan
        // tiap jam. `whereNotNull` bukan sekadar kehati-hatian — tiket lama
        // hasil migrasi bisa berstatus Resolved tanpa resolved_at, dan tanpa
        // penjagaan ini perbandingan tanggalnya akan memperlakukan mereka
        // sebagai "sudah lama lewat".
        $tickets = Ticket::where('status', 'Resolved')
            ->whereNotNull('resolved_at')
            ->where('resolved_at', '<=', $batas)
            ->get();

        $actor = self::auditActor();
        $ditutup = 0;

        foreach ($tickets as $ticket) {
            self::close($ticket, $days, $actor);
            $ditutup++;
        }

        return $ditutup;
    }

    private static function close(Ticket $ticket, int $days, ?User $actor): void
    {
        // satisfaction_rating sengaja TIDAK diisi — lihat config/helpdesk.php.
        $ticket->update(['status' => 'Closed']);

        $keterangan = "Tiket {$ticket->ticket_no} ditutup otomatis setelah {$days} hari tanpa konfirmasi requester.";

        if ($ticket->requester_id) {
            NotificationService::notify(
                $ticket->requester,
                'requester',
                $ticket,
                'ticket_closed',
                'Tiket Ditutup Otomatis',
                "Tiket {$ticket->ticket_no} ditutup otomatis karena sudah {$days} hari selesai tanpa konfirmasi. Hubungi Support bila masalahnya kembali muncul."
            );
        }

        if (! $actor) {
            // Tidak ada Administrator untuk diatribusikan — hanya mungkin pada
            // instalasi yang belum di-seed. Penutupannya tetap terjadi, tapi
            // TIDAK diam-diam: jejaknya pindah ke log.
            Log::warning('[TicketAutoClose] Audit trail dilewati: tidak ada akun ber-role Administrator.', [
                'ticket_no' => $ticket->ticket_no,
            ]);

            return;
        }

        AuditTrail::record($actor, [
            'module' => 'ticket_support',
            'action' => 'auto_close',
            'target_type' => 'ticket',
            'target_id' => $ticket->id,
            'target_name' => $ticket->ticket_no,
            'old_value' => ['status' => 'Resolved'],
            'new_value' => ['status' => 'Closed', 'tenggang_hari' => $days],
            'description' => $keterangan,
        ]);
    }

    /**
     * Aksi otomatis tidak punya pelaku manusia, tapi AuditTrail::record()
     * menuntut satu. Mengikuti pola yang sudah dipakai EmployeeSync: atribusikan
     * ke akun Administrator pertama, dan deskripsinya yang menjelaskan bahwa
     * ini penutupan otomatis — bukan Admin itu yang menekan tombol.
     */
    private static function auditActor(): ?User
    {
        return User::whereHas('roles', fn ($q) => $q->where('name', 'Administrator'))
            ->orderBy('id')
            ->first();
    }
}
