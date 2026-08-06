<?php

namespace App\Console\Commands;

use App\Models\AuditTrail;
use App\Models\Ticket;
use App\Models\TicketComment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Menyisipkan sapaan otomatis Support ke tiket yang SUDAH dikerjakan sebelum
 * fitur sapaan itu ada.
 *
 * Latar belakangnya: sapaan ditulis di dalam SupportController::start(), jadi
 * hanya lahir untuk tiket yang tombol "Kerjakan Sekarang"-nya ditekan setelah
 * fitur ini masuk. Tiket yang sudah berstatus In Progress sejak sebelumnya
 * tidak pernah melewati jalur itu, dan di layar requester forum diskusinya
 * tampak kosong — persis yang terlihat pada INC-2026-0008.
 *
 * Waktunya di-backdate ke saat tiket benar-benar mulai dipegang, bukan saat
 * perintah ini dijalankan. Memakai waktu sekarang akan menaruh sapaan "silakan
 * menunggu" di bawah balasan-balasan yang sebenarnya sudah terjadi berhari-hari
 * sebelumnya, dan urutan percakapan jadi tidak masuk akal.
 *
 * Aman diulang: tiket yang sudah punya komentar peran "Sistem" dilewati.
 */
class BackfillSupportGreeting extends Command
{
    protected $signature = 'support:backfill-greeting
                            {--apply : Tulis ke database. Tanpa ini perintah hanya menampilkan rencana.}
                            {--status=In Progress : Status tiket yang disasar.}';

    protected $description = 'Sisipkan sapaan otomatis Support ke tiket lama yang belum punya.';

    public function handle(): int
    {
        $status = (string) $this->option('status');
        $apply = (bool) $this->option('apply');

        $targets = Ticket::where('status', $status)->orderBy('id')->get()
            ->reject(fn (Ticket $t) => TicketComment::where('ticket_id', $t->id)->where('author_role', 'Sistem')->exists());

        if ($targets->isEmpty()) {
            $this->info("Tidak ada tiket berstatus \"{$status}\" yang perlu diisi. Tidak ada yang dikerjakan.");

            return self::SUCCESS;
        }

        $rows = [];
        $skipped = [];

        foreach ($targets as $ticket) {
            $at = $this->startedAt($ticket);

            if (! $at) {
                $skipped[] = $ticket;

                continue;
            }

            $rows[] = ['ticket' => $ticket, 'at' => $at];
        }

        $this->table(
            ['Tiket', 'Requester', 'Sapaan diberi tanggal'],
            collect($rows)->map(fn (array $r) => [
                $r['ticket']->ticket_no,
                optional($r['ticket']->requester)->name ?? '—',
                $r['at']->format('d M Y H:i'),
            ])->all(),
        );

        foreach ($skipped as $ticket) {
            $this->warn("Dilewati {$ticket->ticket_no}: tidak ada jejak kapan mulai dikerjakan, jadi tidak ada tanggal yang bisa dipakai.");
        }

        if (! $apply) {
            $this->newLine();
            $this->info('Ini baru rencana. Jalankan ulang dengan --apply untuk menulisnya.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($rows) {
            foreach ($rows as $r) {
                // Lewat kelas yang sama dengan start(), supaya kalimat sapaannya
                // tidak bisa berbeda antara tiket baru dan tiket susulan.
                // Notifikasinya dimatikan: ini pengisian arsip, bukan kabar baru.
                SupportGreeting::post($r['ticket'], null, $r['at'], notify: false);
            }
        });

        $this->newLine();
        $this->info(count($rows).' sapaan disisipkan.');

        return self::SUCCESS;
    }

    /**
     * Kapan tiket ini mulai dipegang Support.
     *
     * first_response_at adalah jawaban paling tepat karena itu yang disetel
     * start(). Kalau kosong, jejak audit "start" dipakai sebagai cadangan.
     * Kalau dua-duanya tidak ada, tiketnya dilewati — menebak tanggal lebih
     * buruk daripada tidak menyisipkan apa pun.
     *
     * Hasilnya lalu dipaksa mendahului komentar tertua yang sudah ada. Pada
     * INC-2026-0008, first_response_at justru disetel OLEH balasan Support
     * pertama, jadi keduanya jatuh di detik yang sama dan sapaan "silakan
     * menunggu" muncul setelah jawaban yang ditunggu — urutan yang membingungkan
     * requester. Sapaan pembuka harus selalu memimpin utas.
     */
    private function startedAt(Ticket $ticket): ?\Illuminate\Support\Carbon
    {
        $audit = AuditTrail::where('target_type', 'ticket')
            ->where('target_id', $ticket->id)
            ->where('action', 'start')
            ->latest('created_at')
            ->first();

        $at = $ticket->first_response_at ?? $audit?->created_at;

        if (! $at) {
            return null;
        }

        $earliest = TicketComment::where('ticket_id', $ticket->id)->min('created_at');

        if ($earliest && $at->greaterThanOrEqualTo($earliest)) {
            return \Illuminate\Support\Carbon::parse($earliest)->subSecond();
        }

        return $at;
    }
}
