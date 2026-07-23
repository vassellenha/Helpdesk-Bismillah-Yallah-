<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $ticket_id
 * @property int $approver_id
 * @property string $decision
 * @property string $note
 * @property string|null $forwarded_to
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property-read Ticket $ticket
 * @property-read User $approver
 */
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
