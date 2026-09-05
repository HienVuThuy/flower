<?php

namespace App\Services\Pricing;

use App\Enums\PriceSignal;

/**
 * Một đề xuất giá cho admin, kèm ĐÚNG những con số đã sinh ra nó.
 * ============================================================
 * `evidence` không phải phần trang trí. Đây là công cụ khuyên người ta
 * đổi giá bán — thứ ảnh hưởng thẳng tới doanh thu — nên admin phải kiểm
 * lại được kết luận mà không cần tin vào mã nguồn.
 *
 * Một đề xuất không nêu được bằng chứng thì không được phép tồn tại:
 * xem `PricingAdvisor`, mọi nhánh đều dựng `evidence` cùng lúc với việc
 * quyết định tín hiệu.
 */
final readonly class PriceSuggestion
{
    /**
     * @param  list<string>  $evidence  các con số thật, viết cho người đọc
     * @param  ?string  $anchor  mốc giá tham chiếu có thật (trung vị danh
     *                           mục), hoặc null khi không tính được
     */
    public function __construct(
        public ProductDemand $demand,
        public PriceSignal $signal,
        public array $evidence,
        public ?string $anchor = null,
    ) {
    }

    public function product()
    {
        return $this->demand->product;
    }
}
