<?php

namespace App\Models;

use App\Enums\GiftCampaignKind;
use App\Enums\PromotionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Một chương trình quà tặng. Xem migration create_gift_tables.
 *
 * `used_count` KHÔNG nằm trong $fillable: chỉ tăng khi đơn có quà được tạo,
 * giảm khi đơn đó bị huỷ — cùng luật với lượt mã giảm giá.
 */
class GiftCampaign extends Model
{
    protected $fillable = [
        'name',
        'kind',
        'gift_item_id',
        'gift_quantity',
        'trigger_product_id',
        'trigger_min_quantity',
        'min_order_amount',
        'min_member_tier_id',
        'first_order_only',
        'per_user_limit',
        'total_limit',
        'starts_at',
        'ends_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'kind' => GiftCampaignKind::class,
            'status' => PromotionStatus::class,
            'gift_quantity' => 'integer',
            'trigger_min_quantity' => 'integer',
            'min_order_amount' => 'decimal:2',
            'first_order_only' => 'boolean',
            'per_user_limit' => 'integer',
            'total_limit' => 'integer',
            'used_count' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function giftItem(): BelongsTo
    {
        return $this->belongsTo(GiftItem::class);
    }

    public function triggerProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'trigger_product_id');
    }

    public function minMemberTier(): BelongsTo
    {
        return $this->belongsTo(MemberTier::class, 'min_member_tier_id');
    }

    /** Đang chạy: bật, trong thời gian, còn suất. Chưa xét điều kiện của từng đơn. */
    public function isRunning(): bool
    {
        if ($this->status !== PromotionStatus::Active) {
            return false;
        }

        if ($this->starts_at && $this->starts_at->isFuture()) {
            return false;
        }

        if ($this->ends_at && $this->ends_at->isPast()) {
            return false;
        }

        return $this->total_limit === null || $this->used_count < $this->total_limit;
    }

    /** Còn bao nhiêu suất; null = không giới hạn. */
    public function conSuat(): ?int
    {
        return $this->total_limit === null ? null : max(0, $this->total_limit - $this->used_count);
    }
}
