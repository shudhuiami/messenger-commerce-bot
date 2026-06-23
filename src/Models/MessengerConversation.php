<?php

namespace Shudhuiami\MessengerCommerceBot\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MessengerConversation extends Model
{
    protected $fillable = [
        'messenger_customer_id',
        'order_id',
        'status',
        'last_message_at',
    ];

    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(MessengerCustomer::class, 'messenger_customer_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(MessengerMessage::class);
    }
}
