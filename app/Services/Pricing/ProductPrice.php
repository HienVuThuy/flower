<?php

namespace App\Services\Pricing;

use App\Models\Promotion;

/**
 * Kết quả tính giá của một sản phẩm tại thời điểm hiện tại.
 *
 * Là object bất biến (readonly) để không nơi nào lỡ tay sửa giá sau
 * khi đã tính. Mọi chỗ hiển thị giá đều nhận object này thay vì tự
 * đọc base_price/sale_price rồi tính lại theo cách riêng.
 */
final readonly class ProductPrice
{
    public function __construct(
        public ?string $basePrice,
        public ?string $finalPrice,
        public ?Promotion $promotion = null,
    ) {}

    /** Có đang được giảm giá thật sự không (giá cuối < giá gốc). */
    public function isDiscounted(): bool
    {
        return $this->promotion !== null
            && $this->basePrice !== null
            && $this->finalPrice !== null
            && bccomp($this->finalPrice, $this->basePrice, 2) === -1;
    }

    /** Số tiền được giảm; null nếu không có giảm giá. */
    public function discountAmount(): ?string
    {
        if (! $this->isDiscounted()) {
            return null;
        }

        return bcsub($this->basePrice, $this->finalPrice, 2);
    }

    /** Phần trăm giảm đã làm tròn, dùng cho nhãn "-20%". */
    public function discountPercent(): ?int
    {
        if (! $this->isDiscounted() || bccomp($this->basePrice, '0', 2) !== 1) {
            return null;
        }

        return (int) round(((float) $this->discountAmount() / (float) $this->basePrice) * 100);
    }

    /** Sản phẩm chưa đặt giá — hiển thị "Liên hệ báo giá". */
    public function isContactForPrice(): bool
    {
        return $this->basePrice === null;
    }
}
