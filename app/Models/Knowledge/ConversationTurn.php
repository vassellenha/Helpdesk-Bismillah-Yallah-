<?php

namespace App\Models\Knowledge;

use Illuminate\Database\Eloquent\Model;

class ConversationTurn extends Model
{
    public const ROLE_USER = 'user';
    public const ROLE_EVA = 'eva';

    protected $table = 'kb_conversation_turns';

    protected $fillable = [
        'conversation_id', 'ordinal', 'role', 'message',
        'source_type', 'source_id', 'confidence', 'is_clarifying',
    ];

    protected $casts = [
        'ordinal' => 'integer',
        'confidence' => 'integer',
        'is_clarifying' => 'boolean',
    ];

    public function conversation()
    {
        return $this->belongsTo(Conversation::class, 'conversation_id');
    }
}
