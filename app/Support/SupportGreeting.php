<?php

namespace App\Support;

use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Sapaan otomatis begitu Support mulai mengerjakan sebuah tiket.
 *
 * Dikumpulkan di satu kelas karena isinya dipakai di empat tempat:
 * SupportController::start(), SupportBpoController::start(), perintah
 * backfill, dan pengujiannya. Sebelum ini teks pesannya diketik ulang di
 * tiap tempat — cukup satu kali seseorang memperbaiki kalimatnya di salah
 * satu berkas untuk membuat requester menerima dua kalimat berbeda
 * tergantung tim mana yang memegang tiketnya.
 *
 * Penulisnya "Helpdesk" dengan peran "Sistem", bukan nama agen: keempat layar
 * detail merender balon biru rata-kanan untuk peran si pembaca sendiri, jadi
 * memakai peran "Support" membuat pesan otomatis ini tampak seperti diketik
 * manual oleh agen yang bersangkutan.
 */
class SupportGreeting
{
    public const AUTHOR_NAME = 'Helpdesk';

    public const AUTHOR_ROLE = 'Sistem';

    public const MESSAGE = 'Tiket sedang diperiksa oleh kami, silakan menunggu feedback berikutnya.';

    /**
     * Tulis sapaan ke forum diskusi dan bunyikan lonceng requester.
     *
     * Dipanggil DI DALAM transaksi start(): kalau perubahan statusnya batal,
     * requester tidak boleh tertinggal notifikasi untuk pekerjaan yang tidak
     * pernah dimulai.
     *
     * `$notify` bisa dimatikan untuk pengisian susulan tiket lama. Membunyikan
     * lonceng hari ini untuk tiket yang mulai dikerjakan minggu lalu bukan
     * kabar, cuma gangguan — dan requester akan mengira ada yang baru terjadi.
     */
    public static function post(Ticket $ticket, ?User $actor = null, ?Carbon $at = null, bool $notify = true): TicketComment
    {
        $comment = new TicketComment([
            'ticket_id' => $ticket->id,
            'author_name' => self::AUTHOR_NAME,
            'author_role' => self::AUTHOR_ROLE,
            'message' => self::MESSAGE,
        ]);

        // created_at sengaja TIDAK ada di $fillable — backdate hanya boleh
        // terjadi lewat parameter yang jelas, bukan tercecer lewat mass
        // assignment dari tempat lain.
        if ($at) {
            $comment->created_at = $at;
            $comment->updated_at = $at;
        }

        $comment->save();

        if ($notify) {
            self::notifyRequester($ticket, $actor);
        }

        return $comment;
    }

    /**
     * Hanya requester yang dikabari, bukan seluruh peserta diskusi.
     *
     * Approver tidak dilibatkan: perannya sudah selesai begitu tiket lolos
     * persetujuan, dan "tiket Anda mulai diperiksa" bukan kabar yang menuntut
     * tindakan apa pun darinya. Agen yang baru saja menekan tombolnya juga
     * dilewati — termasuk kalau kebetulan dialah requester-nya, hal yang
     * mungkin terjadi karena staf IT juga bisa mengajukan tiket sendiri.
     */
    private static function notifyRequester(Ticket $ticket, ?User $actor): void
    {
        $requester = $ticket->requester;

        if (! $requester || $requester->id === $actor?->id) {
            return;
        }

        NotificationService::notify(
            $requester,
            $ticket,
            'discussion_message',
            'Tiket Anda Mulai Diperiksa',
            self::AUTHOR_NAME.' ('.self::AUTHOR_ROLE.") di tiket {$ticket->ticket_no}: ".self::MESSAGE,
        );
    }
}
