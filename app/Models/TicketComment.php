<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $ticket_id
 * @property string $author_name
 * @property string $author_role
 * @property string $message
 * @property string|null $attachment_name
 * @property string|null $attachment_path
 * @property string|null $attachment_mime_type
 * @property int|null $attachment_size_bytes
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property-read Ticket $ticket
 */
class TicketComment extends Model
{
    protected $fillable = [
        'ticket_id', 'author_name', 'author_role', 'message',
        'attachment_name', 'attachment_path', 'attachment_mime_type', 'attachment_size_bytes',
    ];

    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }
}
