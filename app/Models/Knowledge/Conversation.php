<?php

namespace App\Models\Knowledge;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Percakapan karyawan dengan EVA.
 *
 * ticket_reference adalah catatan nomor tiket yang diterbitkan sistem Helpdesk
 * SETELAH karyawan mengirim drafnya sendiri — EVA tidak menulis ke tabel tiket.
 */
class Conversation extends Model
{
    public const OUTCOME_OPEN = 'open';
    public const OUTCOME_ANSWERED = 'answered';
    public const OUTCOME_TICKET = 'ticket';
    public const OUTCOME_ABANDONED = 'abandoned';

    public const OUTCOMES = [
        self::OUTCOME_OPEN, self::OUTCOME_ANSWERED, self::OUTCOME_TICKET, self::OUTCOME_ABANDONED,
    ];

    protected $table = 'kb_conversations';

    protected $fillable = [
        'user_id', 'requester_name', 'department', 'outcome', 'ticket_reference', 'started_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function turns()
    {
        return $this->hasMany(ConversationTurn::class, 'conversation_id')->orderBy('ordinal');
    }

    public function answerLogs()
    {
        return $this->hasMany(AnswerLog::class, 'conversation_id');
    }
}
