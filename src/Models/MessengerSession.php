<?php

namespace Shudhuiami\MessengerCommerceBot\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessengerSession extends Model
{
    protected $fillable = [
        'messenger_customer_id',
        'current_step',
        'product_id',
        'quantity',
        'delivery_address',
        'customer_phone',
        'cart_data',
    ];

    protected function casts(): array
    {
        return [
            'cart_data' => 'array',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(MessengerCustomer::class, 'messenger_customer_id');
    }

    public function reset(): void
    {
        $this->update([
            'current_step' => null,
            'product_id' => null,
            'quantity' => null,
            'delivery_address' => null,
            'customer_phone' => null,
            'cart_data' => null,
        ]);
    }
}
