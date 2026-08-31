<?php

namespace App\Support;

use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
/**
 * Shared body of "post a discussion reply" — Requester, Support IT, Support
 * BPO, and Approver each have their own addComment() (different auth/status
 * guards per role), but validation, optional attachment storage, the
 * notification fan-out, and the JSON shape sent back to the browser were
 * identical, copy-pasted four times. Pulled out here so attachment support
 * only had to be written once.
 *
 * Team Lead's own addNote() is deliberately NOT wired to this: its frontend
 * has no reply UI at all yet, a pre-existing gap this change doesn't touch.
 */
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class TicketDiscussion
{
    /** Aturan `max` milik Laravel dihitung dalam kilobyte — ini 30 MB. */
    public const MAX_ATTACHMENT_KB = 30720;

    public const STORAGE_DIR = 'ticket-comment-attachments';

    public static function rules(): array
    {
        return [
            'message' => 'required_without:file|nullable|string|max:3000',
            /*
             | Tanpa daftar putih ekstensi: forum diskusi tiket dipakai
             | melampirkan apa pun yang menjelaskan masalah — log, arsip, berkas
             | ekspor, rekaman layar. Daftar lama justru menolak berkas yang sah
             | tanpa memberi tahu apa yang boleh.
             |
             | Yang menjaga tetap ada, dan tidak dilonggarkan: batas ukuran,
             | jumlah lampiran per tiket, gerbang siapa yang boleh membacanya,
             | serta penyimpanan di disk PRIVAT — berkasnya tidak pernah bisa
             | dijangkau langsung dari web, hanya lewat rute yang memeriksa hak
             | akses. Berkas yang tidak aman ditampilkan di layar dikirim sebagai
             | unduhan, bukan dirender — lihat TicketAttachmentController.
            */
            'file' => 'nullable|file|max:'.self::MAX_ATTACHMENT_KB,
        ];
    }

    /**
     * $dbAuthorRole is the value stored on the row (drives the chat bubble's
     * left/right alignment client-side — Support IT and Support BPO both
     * store 'Support' here on purpose). $notifyRoleLabel is the human label
     * used in the notification text, where the two must read differently.
     */
    public static function store(
        Ticket $ticket,
        User $author,
        string $dbAuthorRole,
        string $notifyRoleLabel,
        array $data,
        ?UploadedFile $file,
    ): TicketComment {
        $attributes = [
            'ticket_id' => $ticket->id,
            'author_id' => $author->id,
            'author_name' => $author->name,
            'author_role' => $dbAuthorRole,
            'message' => $data['message'] ?? '',
        ];

        if ($file) {
            $path = $file->store(self::STORAGE_DIR, 'local');

            // store() memulangkan FALSE saat penulisan gagal dan tidak melempar
            // apa pun. Dibiarkan lolos, komentarnya tersimpan dengan lampiran
            // yang menunjuk ke tempat kosong — dan yang terbaca di layar hanya
            // "berkas hilang", tanpa jejak kapan ia hilang.
            if ($path === false) {
                Log::error('Lampiran komentar gagal disimpan ke disk.', [
                    'ticket' => $ticket->ticket_no,
                    'name' => $file->getClientOriginalName(),
                ]);

                throw ValidationException::withMessages([
                    'file' => 'Berkas gagal disimpan di server. Coba kirim ulang; bila berulang, hubungi administrator.',
                ]);
            }

            $attributes['attachment_path'] = $path;
            $attributes['attachment_name'] = $file->getClientOriginalName();
            $attributes['attachment_mime_type'] = $file->getMimeType();
            $attributes['attachment_size_bytes'] = $file->getSize();
        }

        $comment = TicketComment::create($attributes);

        // A message-less, attachment-only reply still needs a non-empty
        // preview for the notification text.
        $preview = $data['message'] ?? '📎 '.($attributes['attachment_name'] ?? 'Lampiran');
        NotificationService::notifyDiscussionParticipants($ticket, $author, $notifyRoleLabel, $preview);

        return $comment;
    }

    public static function present(TicketComment $c): array
    {
        return [
            'id' => $c->id,
            // Identitas, bukan peran. Layar memakainya untuk memutuskan
            // gelembung mana milik pembacanya — lihat migrasi
            // 2026_08_31_090000. Null untuk komentar lama; di sana layar
            // kembali memakai perbandingan nama.
            'authorId' => $c->author_id,
            'authorName' => $c->author_name,
            'authorRole' => $c->author_role,
            'message' => $c->message,
            'at' => $c->created_at->translatedFormat('j M · H:i'),
            'attachment' => $c->attachment_path ? [
                'name' => $c->attachment_name,
                'url' => route('tickets.comment.attachment.show', [$c->ticket, $c]),
                'sizeBytes' => $c->attachment_size_bytes,
            ] : null,
        ];
    }
}
