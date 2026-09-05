<?php

namespace App\Services\Pricing;

use App\Models\Product;
use Illuminate\Support\Carbon;

/**
 * Số liệu nhu cầu của MỘT sản phẩm — chỉ là số, chưa có ý kiến nào.
 *
 * Bất biến: đã đo xong thì không ai sửa được nữa. `PricingAdvisor` đọc
 * đối tượng này để đưa ra đề xuất, và vì nó không sửa được nên đề xuất
 * luôn tính trên đúng con số đã hiện cho admin xem.
 */
final readonly class ProductDemand
{
    public function __construct(
        public Product $product,
        public int $views,
        public int $addToCarts,
        public int $unitsSold,
        public int $ordersWith,
        public ?Carbon $lastSoldAt,
        public int $windowDays,
    ) {
    }

    /**
     * Tỉ lệ xem → mua, tính theo SỐ ĐƠN chứ không theo số lượng.
     *
     * Một đơn 20 cành hoa là MỘT người quyết định mua, không phải hai
     * mươi. Chia số lượng cho lượt xem sẽ cho ra tỉ lệ chuyển đổi trên
     * 100% ở những món bán theo lô — một con số vô nghĩa mà vẫn trông
     * như đang hoạt động tốt.
     *
     * TRẢ null KHI CHƯA CÓ LƯỢT XEM NÀO. Không phải 0% — chưa ai xem thì
     * chưa có gì để chuyển đổi, và "0%" đọc ra là "có người xem mà không
     * ai mua", một kết luận sai hoàn toàn.
     */
    public function conversionRate(): ?float
    {
        if ($this->views <= 0) {
            return null;
        }

        return $this->ordersWith / $this->views * 100;
    }

    /** Tỉ lệ xem → thêm giỏ; null khi chưa có lượt xem nào. */
    public function cartRate(): ?float
    {
        if ($this->views <= 0) {
            return null;
        }

        return $this->addToCarts / $this->views * 100;
    }

    /**
     * Số ngày kể từ lần bán gần nhất; null nếu CHƯA BÁN LẦN NÀO.
     *
     * null ở đây không phải "thiếu dữ liệu" mà là một sự thật cụ thể:
     * món này chưa từng bán được. Giao diện phải nói đúng như vậy chứ
     * không hiện một dấu gạch ngang.
     */
    public function daysSinceLastSale(): ?int
    {
        return $this->lastSoldAt?->startOfDay()->diffInDays(now()->startOfDay());
    }

    public function neverSold(): bool
    {
        return $this->lastSoldAt === null;
    }

    /** Số ngày sản phẩm đã nằm trong cửa hàng. */
    public function ageInDays(): int
    {
        return (int) $this->product->created_at?->startOfDay()->diffInDays(now()->startOfDay());
    }

    /** Còn quản lý tồn kho và còn hàng thật. */
    public function stock(): ?int
    {
        return $this->product->track_inventory ? (int) $this->product->stock_quantity : null;
    }

    /**
     * Đang có chương trình khuyến mại chạy hay không.
     *
     * Cần quan hệ `promotions` đã nạp — nếu chưa thì trả về false thay vì
     * lặng lẽ sinh một truy vấn cho mỗi sản phẩm trong bảng.
     */
    public function isDiscounted(): bool
    {
        if (! $this->product->relationLoaded('promotions')) {
            return false;
        }

        return $this->product->price()->isDiscounted();
    }
}
