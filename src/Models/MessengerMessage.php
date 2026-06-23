<?php

namespace Shudhuiami\MessengerCommerceBot\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessengerMessage extends Model
{
    protected $fillable = [
        'messenger_conversation_id',
        'message_id',
        'sender_type',
        'message_text',
        'raw_payload',
    ];

    protected function casts(): array
    {
        return [
            'raw_payload' => 'array',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(MessengerConversation::class, 'messenger_conversation_id');
    }
}
