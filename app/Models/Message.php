<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Một tin nhắn trong hội thoại của một khách với cửa hàng. */
class Message extends Model
{
    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function tuKhach(): bool
    {
        return $this->sender_id !== null && (int) $this->sender_id === (int) $this->customer_id;
    }
}
