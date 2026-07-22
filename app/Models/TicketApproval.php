<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TicketApproval extends Model
{
    protected $fillable = ['ticket_id', 'approver_id', 'decision', 'note', 'forwarded_to'];

    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approver_id');
    }
}
