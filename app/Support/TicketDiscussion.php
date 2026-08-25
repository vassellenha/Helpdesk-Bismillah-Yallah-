<?php

namespace App\Support;

use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

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
class TicketDiscussion
{
    /** Laravel's `max` file rule counts in kilobytes — this is 5 MB. */
    public const MAX_ATTACHMENT_KB = 5120;

    public const ALLOWED_ATTACHMENT_MIMES = 'png,jpg,jpeg,pdf,doc,docx,xls,xlsx,mp4,mov,webm';

    public const STORAGE_DIR = 'ticket-comment-attachments';

    public static function rules(): array
    {
        return [
            'message' => 'required_without:file|nullable|string|max:3000',
            'file' => 'nullable|file|mimes:'.self::ALLOWED_ATTACHMENT_MIMES.'|max:'.self::MAX_ATTACHMENT_KB,
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
            'author_name' => $author->name,
            'author_role' => $dbAuthorRole,
            'message' => $data['message'] ?? '',
        ];

        if ($file) {
            $attributes['attachment_path'] = $file->store(self::STORAGE_DIR, 'local');
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
            'authorName' => $c->author_name,
            'authorRole' => $c->author_role,
            'message' => $c->message,
            'at' => $c->created_at->translatedFormat('j M · H:i'),
            'attachment' => $c->attachment_path ? [
                'name' => $c->attachment_name,
                'url' => route('tickets.comment.attachment.show', [$c->ticket_id, $c]),
                'sizeBytes' => $c->attachment_size_bytes,
            ] : null,
        ];
    }
}
