<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TicketNotification extends Model
{
    protected $fillable = ['user_id', 'ticket_id', 'type', 'title', 'message', 'read_at'];

    protected $casts = ['read_at' => 'datetime'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }
}
