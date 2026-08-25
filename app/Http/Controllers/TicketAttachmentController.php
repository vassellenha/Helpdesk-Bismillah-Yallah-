<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\TicketComment;
use App\Support\TicketAttachmentAccess;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Satu-satunya pintu keluar berkas lampiran.
 *
 * Berkasnya sendiri disimpan di disk privat (storage/app/private), di luar
 * document root, jadi tidak ada jalan lain menuju ke sana selain lewat sini.
 */
class TicketAttachmentController extends Controller
{
    public function show(Ticket $ticket, TicketAttachment $attachment): StreamedResponse
    {
        // Nomor tiket di URL harus benar-benar memiliki lampiran ini. Tanpa
        // baris ini, siapa pun yang punya satu tiket sendiri bisa memasang
        // nomor tiketnya sendiri di depan id lampiran milik orang lain, dan
        // gerbang di bawah akan meloloskannya — karena yang diperiksa adalah
        // tiket miliknya, bukan tiket asal berkasnya.
        abort_unless($attachment->ticket_id === $ticket->id, 404);

        abort_unless(TicketAttachmentAccess::allows($ticket, Auth::user()), 403);

        abort_unless(Storage::disk('local')->exists($attachment->path), 404);

        // inline, bukan attachment: pratinjau gambar dan PDF di halaman detail
        // menampilkannya langsung, bukan mengunduhnya.
        return Storage::disk('local')->response($attachment->path, $attachment->name, [
            'Content-Disposition' => 'inline; filename="'.addslashes($attachment->name).'"',
            // Tautan ini sudah diperiksa per permintaan; menyimpannya di cache
            // bersama (proxy kantor) berarti orang berikutnya bisa menerima
            // berkas yang bukan haknya tanpa pernah menyentuh gerbang ini.
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /**
     * Berkas yang ditempel pada satu komentar Forum Diskusi. Aturan siapa boleh
     * membuka persis sama dengan lampiran tiketnya — komentar tidak punya
     * lingkaran pembaca sendiri, ia ikut tiket tempatnya menempel.
     */
    public function showComment(Ticket $ticket, TicketComment $comment): StreamedResponse
    {
        abort_unless($comment->ticket_id === $ticket->id, 404);
        abort_if(blank($comment->attachment_path), 404);

        abort_unless(TicketAttachmentAccess::allows($ticket, Auth::user()), 403);

        abort_unless(Storage::disk('local')->exists($comment->attachment_path), 404);

        return Storage::disk('local')->response($comment->attachment_path, $comment->attachment_name, [
            'Content-Disposition' => 'inline; filename="'.addslashes((string) $comment->attachment_name).'"',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
