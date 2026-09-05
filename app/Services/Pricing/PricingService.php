<?php

namespace App\Services\Pricing;

use App\Enums\PromotionType;
use App\Models\Product;
use App\Models\Promotion;

/**
 * Nguồn sự thật DUY NHẤT để tính giá bán cuối cùng.
 *
 * Không view/controller nào được tự tính giảm giá. Tất cả đều gọi
 * resolve() để nếu sau này đổi luật (thêm combo, cộng dồn khuyến
 * mại, giá theo variant...) thì chỉ sửa một chỗ.
 *
 * Dùng bcmath cho phép tính tiền để tránh sai số dấu phẩy động —
 * base_price được cast 'decimal:2' nên luôn là string.
 */
class PricingService
{
    /**
     * @param  Product  $product  Nên đã eager-load quan hệ `promotions`
     *                            khi gọi trong vòng lặp danh sách, nếu
     *                            không sẽ sinh N+1.
     */
    public function resolve(Product $product): ProductPrice
    {
        $base = $product->base_price;

        // Chưa có giá gốc thì không thể tính giảm — sản phẩm dạng
        // "liên hệ báo giá" (hoa sự kiện, cây cỡ lớn...).
        if ($base === null) {
            return new ProductPrice(null, null);
        }

        $promotion = $this->bestPromotionFor($product, $base);

        if (! $promotion) {
            return new ProductPrice($base, $base);
        }

        $final = $this->applyDiscount($base, $promotion, $product);

        // Chốt chặn: giá sau KM không bao giờ âm, và không được
        // cao hơn giá gốc (cấu hình sai thì bỏ qua khuyến mại).
        if (bccomp($final, '0', 2) === -1) {
            $final = '0.00';
        }

        if (bccomp($final, $base, 2) !== -1) {
            return new ProductPrice($base, $base);
        }

        return new ProductPrice($base, $final, $promotion);
    }

    /**
     * Chương trình được áp dụng khi sản phẩm nằm trong nhiều chương
     * trình cùng lúc: chọn cái cho KHÁCH GIÁ THẤP NHẤT.
     * ============================================================
     * TRƯỚC ĐÂY CHỌN THEO `priority` CAO NHẤT — và đó là một lỗi thật,
     * đo được ngay khi thêm giá linh hoạt theo giờ:
     *
     *   Hoa hồng đỏ Ecuador, giá gốc 650.000đ
     *     "Giáng sinh"      ưu tiên 10 -> 250.000đ
     *     "Xả hàng cuối ngày" ưu tiên 50 -> 455.000đ (giảm 30%)
     *
     *   Kết quả cũ: trong khung giờ "xả hàng", khách trả 455.000đ thay
     *   vì 250.000đ. Chương trình khuyến mại LÀM GIÁ TĂNG LÊN.
     *
     * Lỗi này gần như không xảy ra khi mỗi sản phẩm chỉ nằm trong một
     * chương trình. Nhưng giá theo khung giờ khiến việc chồng chương
     * trình thành chuyện bình thường (một chương trình mùa vụ chạy nền,
     * một chương trình xả hàng chạy buổi tối) — nên phải sửa cùng lúc.
     *
     * `priority` VẪN CÒN TÁC DỤNG, chỉ đổi vai: nay nó phá hoà khi hai
     * chương trình cho ra cùng một giá, để admin quyết chương trình nào
     * được hiện tên và banner. Hoà tiếp thì lấy chương trình tạo sau.
     *
     * VẪN KHÔNG CỘNG DỒN: hàm này chọn ĐÚNG MỘT chương trình, đúng như
     * quyết định cũ. Cộng dồn là đường dẫn tới giá tụt về 0 vì cấu hình
     * sai.
     */
    private function bestPromotionFor(Product $product, string $base): ?Promotion
    {
        $candidates = $product->promotions
            ->filter(fn (Promotion $p) => $p->isRunning() && $p->type->isImplemented());

        if ($candidates->isEmpty()) {
            return null;
        }

        return $candidates
            ->sortBy(fn (Promotion $p) => [
                // Giá thấp nhất lên đầu.
                (float) $this->applyDiscount($base, $p, $product),
                // Hoà giá: ưu tiên cao thắng. Đảo dấu vì sortBy xếp tăng dần.
                -$p->priority,
                -$p->id,
            ])
            ->first();
    }

    private function applyDiscount(string $base, Promotion $promotion, Product $product): string
    {
        /*
         * $promotion đến từ quan hệ $product->promotions nên Eloquent
         * đã gắn sẵn dữ liệu pivot vào chính nó. Dùng thẳng
         * $promotion->pivot — KHÔNG truy cập $promotion->products,
         * vì quan hệ đó chưa được load và sẽ sinh một query lazy-load
         * cho mỗi chương trình của mỗi sản phẩm (N+1).
         */
        $pivot = $promotion->pivot;

        // Mức riêng của sản phẩm (nếu admin có ghi đè), nếu không thì
        // lấy mức chung của chương trình.
        $type = $pivot?->discount_type
            ? PromotionType::tryFrom($pivot->discount_type)
            : $promotion->type;

        $value = $pivot?->discount_value ?? $promotion->discount_value;

        if ($type === null || $value === null) {
            return $base;
        }

        return match ($type) {
            PromotionType::Percent => bcsub(
                $base,
                bcdiv(bcmul($base, (string) $value, 4), '100', 2),
                2
            ),
            PromotionType::FixedAmount => bcsub($base, (string) $value, 2),
            PromotionType::FixedPrice => bcadd((string) $value, '0', 2),

            // Combo / BuyXGetY chưa hỗ trợ tính giá — giữ nguyên giá
            // gốc thay vì đoán bừa.
            default => $base,
        };
    }

    /**
     * Tính giá sau KM cho một mức giảm cụ thể — dùng ở form admin để
     * hiển thị "giá trước / giá sau" ngay khi chọn sản phẩm, trước
     * khi lưu vào DB.
     */
    public function preview(?string $basePrice, PromotionType $type, ?float $value): ?string
    {
        if ($basePrice === null || $value === null || ! $type->isImplemented()) {
            return null;
        }

        $final = match ($type) {
            PromotionType::Percent => bcsub($basePrice, bcdiv(bcmul($basePrice, (string) $value, 4), '100', 2), 2),
            PromotionType::FixedAmount => bcsub($basePrice, (string) $value, 2),
            PromotionType::FixedPrice => bcadd((string) $value, '0', 2),
            default => $basePrice,
        };

        return bccomp($final, '0', 2) === -1 ? '0.00' : $final;
    }
}
