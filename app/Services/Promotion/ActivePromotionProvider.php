<?php

namespace App\Services\Promotion;

use App\Models\Promotion;

/**
 * Cung cấp chương trình khuyến mại đang chạy cho các khối giao diện
 * (thanh thông báo, banner chiến dịch ở trang chủ).
 *
 * Nội dung chiến dịch lấy từ DB, KHÔNG hard-code trong Blade — nhờ
 * vậy sang năm admin chỉ cần tạo chương trình mới, không phải sửa
 * code như trước.
 *
 * Kết quả được nhớ trong phạm vi request vì cả announcement bar lẫn
 * campaign banner đều hỏi cùng một dữ liệu trên một trang.
 */
class ActivePromotionProvider
{
    private bool $resolved = false;

    private ?Promotion $promotion = null;

    /**
     * Chương trình nổi bật đang chạy: ưu tiên cao nhất trước, hoà
     * thì lấy chương trình tạo sau.
     */
    public function featured(): ?Promotion
    {
        if ($this->resolved) {
            return $this->promotion;
        }

        $this->resolved = true;

        $this->promotion = Promotion::query()
            ->activeNow()
            ->withCount('products')
            ->orderByDesc('priority')
            ->orderByDesc('id')
            ->first();

        // Chương trình không gắn sản phẩm nào thì không có gì để
        // khách bấm vào xem — coi như không có chiến dịch.
        if ($this->promotion && $this->promotion->products_count === 0) {
            $this->promotion = null;
        }

        return $this->promotion;
    }
}
