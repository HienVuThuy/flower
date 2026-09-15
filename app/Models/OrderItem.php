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

        /*
         * BẢN CHỤP THUẾ CỦA DÒNG NÀY — xem migration
         * add_tax_snapshot_to_order_items_table.
         *
         * `tax_rate` NULL nghĩa là "không có số liệu thuế": hàng không
         * thuộc diện chịu VAT, hoặc đơn đặt lúc tính thuế đang tắt. KHÁC
         * với 0 ("chịu thuế suất 0%").
         */
        'discount_amount',
        'tax_rate',
        'tax_amount',
        'is_gift',
        'gift_campaign_id',
        'gift_item_id',
        'parent_item_id',
        'product_gift_id',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_base_price' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'line_total' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'tax_rate' => 'decimal:5',
            'tax_amount' => 'decimal:2',
            'is_gift' => 'boolean',
        ];
    }

    /**
     * CHỈ HÀNG BÁN — bỏ dòng quà tặng.
     *
     * Mọi nơi đếm "đã bán", "bán chạy", "tốc độ bán" dùng scope này: dòng quà
     * 0đ mà được đếm thì túi phân bón đem tặng trông như bán chạy, và Đề xuất
     * giá / báo cáo nhập hàng đọc sai nhu cầu.
     */
    public function scopeHangBan(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('order_items.is_gift', false);
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

    /** Tiền hàng CHƯA thuế của dòng này — con số hoá đơn phải ghi. */
    public function netTotal(): string
    {
        return bcsub(
            bcsub((string) $this->line_total, (string) $this->discount_amount, 2),
            (string) ($this->tax_amount ?? '0.00'),
            2,
        );
    }

    /** Dòng này thuộc diện chịu VAT không (khác với "chịu 0%"). */
    public function isTaxed(): bool
    {
        return $this->tax_rate !== null;
    }

    /** Dòng này có được giảm giá lúc đặt không. */
    public function wasDiscounted(): bool
    {
        return bccomp((string) $this->unit_price, (string) $this->unit_base_price, 2) < 0;
    }
}
