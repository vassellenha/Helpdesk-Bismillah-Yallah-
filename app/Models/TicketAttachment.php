<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $ticket_id
 * @property string $name
 * @property string $path
 * @property-read Ticket $ticket
 */
class TicketAttachment extends Model
{
    public const MAX_PER_TICKET = 5;

    protected $fillable = ['ticket_id', 'name', 'path'];

    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }
}
