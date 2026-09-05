<?php

namespace App\Models;

use App\Enums\CouponType;
use App\Enums\PaymentMethod;
use App\Enums\PromotionStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Mã giảm giá khách tự nhập.
 *
 * Dùng lại PromotionStatus cho cột status: vòng đời giống hệt
 * (nháp → hẹn lịch → đang chạy → tạm dừng → kết thúc), tạo thêm một
 * enum trùng ý nghĩa chỉ làm hệ thống rối.
 */
class Coupon extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'description',
        'type',
        'value',
        'min_order_amount',
        'max_discount_amount',
        'usage_limit',
        'starts_at',
        'ends_at',
        'status',
        'is_public',
        'per_user_limit',
        'promotion_id',
        'payment_methods',
    ];

    /*
     * used_count nằm ngoài $fillable: chỉ được tăng bởi
     * CouponService::redeem() khi đơn hàng tạo thành công.
     */

    protected function casts(): array
    {
        return [
            'type' => CouponType::class,
            'status' => PromotionStatus::class,
            'value' => 'decimal:2',
            'min_order_amount' => 'decimal:2',
            'max_discount_amount' => 'decimal:2',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_public' => 'boolean',
            'per_user_limit' => 'integer',
            'payment_methods' => 'array',
        ];
    }

    /**
     * Chương trình khuyến mại mà mã này thuộc về, nếu có.
     *
     * Mã "của sự kiện" chỉ hiện ở trang sự kiện đó. Mã không thuộc sự
     * kiện nào (chào bạn mới, sinh nhật) trả về null và hiện ở trang
     * Voucher chung.
     */
    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }

    /**
     * Hình thức thanh toán được phép, dạng enum.
     *
     * Mảng RỖNG nghĩa là không giới hạn — cùng ý nghĩa với null. Gộp hai
     * trường hợp lại ở đây để nơi gọi không phải nhớ phân biệt.
     *
     * @return list<PaymentMethod>
     */
    public function allowedPaymentMethods(): array
    {
        if (! is_array($this->payment_methods) || $this->payment_methods === []) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn ($v) => PaymentMethod::tryFrom((string) $v),
            $this->payment_methods,
        )));
    }

    /**
     * Mã CÓ khai giới hạn hình thức thanh toán hay không.
     *
     * Hỏi trên DỮ LIỆU THÔ, không hỏi trên danh sách đã lọc — và đây là
     * cả lý do hàm này tồn tại.
     *
     * LỖI ĐÃ SỬA: bản trước coi "danh sách sau khi lọc rỗng" là "không
     * có giới hạn nào". Hai thứ đó khác nhau. Một mã lưu
     * `["bank_transfer"]` sau khi hình thức chuyển khoản bị gỡ khỏi hệ
     * thống sẽ lọc ra mảng rỗng — và mã vốn CHỈ dành cho đơn trả trước
     * bỗng áp dụng được cho MỌI đơn, kể cả COD. Nới lỏng một điều kiện
     * về tiền, âm thầm, không lỗi, không cảnh báo.
     *
     * Nay: có khai giới hạn mà không giá trị nào còn hiệu lực thì mã
     * KHÔNG dùng được với hình thức nào cả. Chặt hơn là hướng an toàn —
     * cùng lắm là một mã không dùng được và có người báo; hướng kia là
     * mất tiền mà không ai biết.
     */
    public function hasPaymentRestriction(): bool
    {
        return is_array($this->payment_methods) && $this->payment_methods !== [];
    }

    /** Mã này có dùng được với hình thức thanh toán đó không. */
    public function acceptsPayment(PaymentMethod $method): bool
    {
        // Không khai giới hạn nào = chấp nhận tất cả.
        if (! $this->hasPaymentRestriction()) {
            return true;
        }

        return in_array($method, $this->allowedPaymentMethods(), true);
    }

    /** Mã luôn lưu và so sánh ở dạng CHỮ HOA để khách gõ kiểu gì cũng khớp. */
    public function setCodeAttribute(string $value): void
    {
        $this->attributes['code'] = mb_strtoupper(trim($value));
    }

    /** Đang trong thời gian hiệu lực và đang bật. */
    public function scopeUsableNow(Builder $query): Builder
    {
        return $query
            ->where('status', PromotionStatus::Active)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()));
    }

    public function isRunning(): bool
    {
        if ($this->status !== PromotionStatus::Active) {
            return false;
        }

        if ($this->starts_at && $this->starts_at->isFuture()) {
            return false;
        }

        return ! ($this->ends_at && $this->ends_at->isPast());
    }

    public function isExhausted(): bool
    {
        return $this->usage_limit !== null && $this->used_count >= $this->usage_limit;
    }

    /** Số lượt còn lại, null nghĩa là không giới hạn. */
    public function remainingUses(): ?int
    {
        return $this->usage_limit === null
            ? null
            : max(0, $this->usage_limit - $this->used_count);
    }

    /** Mô tả điều kiện cho khách đọc. */
    public function conditionText(): string
    {
        $parts = [];

        if ($this->min_order_amount) {
            $parts[] = 'Đơn tối thiểu ' . number_format((float) $this->min_order_amount, 0, ',', '.') . 'đ';
        }

        if ($this->type === CouponType::Percent && $this->max_discount_amount) {
            $parts[] = 'Giảm tối đa ' . number_format((float) $this->max_discount_amount, 0, ',', '.') . 'đ';
        }

        return $parts ? implode(' · ', $parts) : 'Không kèm điều kiện';
    }

    /**
     * Mô tả giới hạn lượt dùng của MỘT khách, cho giao diện đọc.
     *
     * Tách khỏi conditionText() vì hai câu trả lời hai câu hỏi khác nhau:
     * conditionText nói về ĐƠN HÀNG (tối thiểu bao nhiêu, giảm tối đa
     * bao nhiêu), câu này nói về NGƯỜI DÙNG.
     */
    public function perUserText(): ?string
    {
        return match ($this->per_user_limit) {
            null => null,
            1 => 'Mỗi tài khoản dùng 1 lần',
            default => 'Mỗi tài khoản dùng tối đa '.$this->per_user_limit.' lần',
        };
    }

    public function getRouteKeyName(): string
    {
        return 'code';
    }
}
