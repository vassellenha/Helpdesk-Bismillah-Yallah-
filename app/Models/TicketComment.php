<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TicketComment extends Model
{
    protected $fillable = ['ticket_id', 'author_name', 'author_role', 'message'];

    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }
}
