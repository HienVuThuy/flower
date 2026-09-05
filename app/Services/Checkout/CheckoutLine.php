<?php

namespace App\Services\Checkout;

use App\Models\Product;
use App\Models\ProductVariant;

/**
 * Một dòng hàng sắp được thanh toán.
 * ============================================================
 * Trung gian giữa "nguồn hàng" (giỏ hàng, hoặc mua ngay) và
 * OrderService. Nhờ nó, OrderService không cần biết khách đến từ đâu.
 *
 * KHÔNG mang giá do client gửi lên: giá luôn tính lại từ Product/
 * ProductVariant tại thời điểm gọi.
 */
final readonly class CheckoutLine
{
    public function __construct(
        public Product $product,
        public ?ProductVariant $variant,
        public int $quantity,
    ) {
    }

    /**
     * Biến thể có giá riêng thì dùng giá đó và KHÔNG áp khuyến mại cấp
     * sản phẩm — cùng quy tắc với CartItem::unitPrice(), giữ nguyên
     * hành vi đang chạy.
     */
    public function hasVariantPrice(): bool
    {
        return $this->variant !== null && $this->variant->price !== null;
    }

    public function unitBasePrice(): string
    {
        return $this->hasVariantPrice()
            ? (string) $this->variant->price
            : $this->product->price()->basePrice;
    }

    public function unitPrice(): string
    {
        return $this->hasVariantPrice()
            ? (string) $this->variant->price
            : $this->product->price()->finalPrice;
    }

    public function lineTotal(): string
    {
        return bcmul($this->unitPrice(), (string) $this->quantity, 2);
    }

    public function promotionName(): ?string
    {
        return $this->hasVariantPrice()
            ? null
            : $this->product->price()->promotion?->name;
    }

    /** Số lượng tối đa còn mua được; null nghĩa là không quản lý tồn kho. */
    public function availableStock(): ?int
    {
        if ($this->variant) {
            return $this->variant->track_inventory ? (int) $this->variant->stock_quantity : null;
        }

        return $this->product->track_inventory ? (int) $this->product->stock_quantity : null;
    }
}
