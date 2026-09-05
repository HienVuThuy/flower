<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Một dòng trong đơn hàng.
 *
 * Mọi thông tin hiển thị (tên, giá, tên chương trình khuyến mại) đều
 * đọc từ CỘT CỦA CHÍNH BẢNG NÀY — bản chụp lúc đặt hàng. Quan hệ
 * product() chỉ để dẫn link sang trang sản phẩm nếu nó còn tồn tại,
 * TUYỆT ĐỐI không dùng để lấy giá.
 */
class OrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'product_id',
        'product_variant_id',
        'product_name',
        'product_sku',
        'variant_name',
        'promotion_name',
        'unit_base_price',
        'unit_price',
        'quantity',
        'line_total',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_base_price' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Quy cách đã mua, nếu có.
     *
     * Cần cho việc tính cước GHN: "Chậu đá 18cm" nặng hơn hẳn "Chậu đá
     * 12cm", nên cân nặng phải lấy theo quy cách chứ không theo sản
     * phẩm. Tên quy cách thì đã chụp sẵn ở cột `variant_name` cho việc
     * hiển thị — quan hệ này chỉ dùng khi cần dữ liệu SỐNG.
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** Dòng này có được giảm giá lúc đặt không. */
    public function wasDiscounted(): bool
    {
        return bccomp((string) $this->unit_price, (string) $this->unit_base_price, 2) < 0;
    }
}
