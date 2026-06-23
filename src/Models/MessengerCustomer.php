<?php

namespace Shudhuiami\MessengerCommerceBot\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MessengerCustomer extends Model
{
    protected $fillable = [
        'psid',
        'name',
        'phone',
        'email',
        'status',
    ];

    public function conversations(): HasMany
    {
        return $this->hasMany(MessengerConversation::class);
    }

    public function session(): HasOne
    {
        return $this->hasOne(MessengerSession::class);
    }
}
