<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $ticket_id
 * @property string $author_name
 * @property string $author_role
 * @property string $message
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property-read Ticket $ticket
 */
class TicketComment extends Model
{
    protected $fillable = ['ticket_id', 'author_name', 'author_role', 'message'];

    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }
}
