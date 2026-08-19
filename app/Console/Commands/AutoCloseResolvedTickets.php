<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Ticket;
use App\Support\TicketAutoClose;
use Illuminate\Console\Command;

/**
 * Menutup tiket yang sudah Resolved tapi tidak dikonfirmasi requester dalam
 * tenggang yang berlaku (bawaan 3 hari).
 *
 * Aman dijalankan berkali-kali: tiket yang sudah Closed tidak lagi masuk
 * saringan, jadi jalan kedua pada menit yang sama tidak menutup apa pun dua
 * kali dan tidak menghasilkan notifikasi kembar.
 *
 * Logikanya sendiri ada di App\Support\TicketAutoClose — perintah ini hanya
 * pintu konsolnya.
 */
final class AutoCloseResolvedTickets extends Command
{
    protected $signature = 'tickets:auto-close';

    protected $description = 'Tutup tiket Resolved yang tidak dikonfirmasi requester setelah tenggang berlalu';

    public function handle(): int
    {
        $days = Ticket::autoCloseAfterDays();

        if ($days <= 0) {
            $this->info('Penutupan otomatis dimatikan (helpdesk.auto_close_resolved_after_days = '.$days.').');

            return self::SUCCESS;
        }

        $ditutup = TicketAutoClose::sweep();

        $this->info($ditutup === 0
            ? "Tidak ada tiket Resolved yang melewati tenggang {$days} hari."
            : "{$ditutup} tiket ditutup otomatis setelah {$days} hari tanpa konfirmasi.");

        return self::SUCCESS;
    }
}
