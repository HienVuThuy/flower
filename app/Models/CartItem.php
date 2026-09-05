<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartItem extends Model
{
    /*
     * `is_selected` CỐ Ý NẰM NGOÀI $fillable.
     *
     * Nó chỉ được đổi qua đúng hai đường ở CartService (chọn một dòng /
     * chọn tất cả), cả hai đều kiểm tra dòng đó có thuộc giỏ của người
     * đang thao tác không. Cho vào fillable là mở đường để bất cứ chỗ nào
     * nhận mảng dữ liệu người dùng cũng ghi đè được.
     */
    protected $fillable = ['cart_id', 'product_id', 'product_variant_id', 'quantity'];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'is_selected' => 'boolean',
        ];
    }

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    /**
     * Đơn giá HIỆN TẠI của dòng này.
     *
     * Biến thể có giá riêng thì lấy giá biến thể; ngược lại lấy giá đã
     * áp khuyến mại của sản phẩm. Cố ý tính lại mỗi lần thay vì lưu:
     * giỏ hàng phải phản ánh giá đang chạy, không phải giá lúc bỏ vào.
     */
    public function unitPrice(): string
    {
        if ($this->variant && $this->variant->price !== null) {
            return (string) $this->variant->price;
        }

        return $this->product->price()->finalPrice;
    }

    public function lineTotal(): string
    {
        return bcmul($this->unitPrice(), (string) $this->quantity, 2);
    }

    /** Số lượng tối đa còn có thể mua, null nếu không quản lý tồn kho. */
    public function availableStock(): ?int
    {
        if ($this->variant) {
            return $this->variant->track_inventory ? (int) $this->variant->stock_quantity : null;
        }

        return $this->product->track_inventory ? (int) $this->product->stock_quantity : null;
    }
}
