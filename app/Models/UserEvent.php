<?php

namespace App\Models;

use App\Enums\UserEventType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;

class UserEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'session_id',
        'event_type',
        'product_id',
        'category_id',
        'meta',
        'created_at',
    ];

    protected $casts = [
        'event_type' => UserEventType::class,
        'meta' => 'array',
        'created_at' => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Ghi lại một hành vi người dùng. Không throw nếu ghi log lỗi
     * (vd DB tạm thời không sẵn sàng) — theo dõi hành vi không
     * bao giờ được phép làm hỏng trải nghiệm chính của khách.
     */
    public static function log(UserEventType $type, Request $request, array $attributes = []): void
    {
        try {
            static::query()->create(array_merge([
                'user_id' => $request->user()?->id,
                'session_id' => $request->session()->getId(),
                'event_type' => $type,
                'created_at' => now(),
            ], $attributes));
        } catch (\Throwable) {
            // im lặng bỏ qua — tracking không được làm hỏng trang chính
        }
    }
}
