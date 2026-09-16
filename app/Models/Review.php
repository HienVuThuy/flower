<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Đánh giá sản phẩm. */
class Review extends Model
{
    protected $fillable = [
        'product_id',
        'user_id',
        'order_id',
        'rating',
        'comment',
    ];

    protected $attributes = [
        'is_visible' => true,
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'is_visible' => 'boolean',
            'admin_replied_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function hasReply(): bool
    {
        return filled($this->admin_reply);
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_visible', true);
    }

    public function authorName(): string
    {
        $name = trim((string) $this->user?->name);

        if ($name === '') {
            return 'Khách hàng';
        }

        $parts = preg_split('/\s+/', $name);

        if (count($parts) < 2) {
            return $name;
        }

        $last = array_pop($parts);

        $middle = array_map(
            static fn (string $p) => mb_substr($p, 0, 1) . '.',
            array_slice($parts, 1),
        );

        return implode(' ', array_merge([$parts[0]], $middle, [$last]));
    }

    public static function eligibleOrders(int $userId, int $productId)
    {
        return Order::query()
            ->where('user_id', $userId)
            ->where('status', OrderStatus::Completed)
            ->whereHas('items', fn (Builder $q) => $q->where('product_id', $productId))
            ->orderByDesc('completed_at')
            ->get();
    }

    public static function pendingOrderFor(int $userId, int $productId): ?Order
    {
        $reviewed = self::query()
            ->where('user_id', $userId)
            ->where('product_id', $productId)
            ->pluck('order_id')
            ->all();

        return self::eligibleOrders($userId, $productId)
            ->first(fn (Order $order) => ! in_array($order->id, $reviewed, strict: true));
    }
}
