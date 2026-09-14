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
    /** @var \Illuminate\Support\Collection<int, Promotion>|null */
    private ?\Illuminate\Support\Collection $dangChay = null;

    public function featured(): ?Promotion
    {
        if ($this->resolved) {
            return $this->promotion;
        }

        $this->resolved = true;

        $this->promotion = $this->dangChay()->first();

        return $this->promotion;
    }

    /**
     * Mọi chương trình ĐANG CHẠY THẬT, ưu tiên cao trước — cho băng chuyền banner.
     * ============================================================
     * Lỗi đã sửa: bản cũ chỉ lọc activeNow() — trạng thái và khoảng ngày.
     * Chương trình "Giờ vàng 19:00–21:00" hiện trên thanh thông báo của MỌI
     * trang lúc 3 giờ chiều, trong khi giá lúc đó chưa giảm (PricingService
     * lọc bằng isRunning()). Banner hứa một mức giá giỏ hàng không cho.
     *
     * Chương trình không gắn sản phẩm nào thì không có gì để khách bấm vào
     * xem — coi như không có chiến dịch.
     *
     * Tính MỘT lần mỗi request: thanh thông báo, banner và băng chuyền cùng
     * đọc, không truy vấn ba lần.
     *
     * @return \Illuminate\Support\Collection<int, Promotion>
     */
    public function dangChay(int $toiDa = 3): \Illuminate\Support\Collection
    {
        $this->dangChay ??= Promotion::query()
            ->activeNow()
            ->withCount('products')
            ->orderByDesc('priority')
            ->orderByDesc('id')
            ->limit(10)
            ->get()
            ->filter(fn (Promotion $km) => $km->products_count > 0 && $km->isRunning())
            ->values();

        return $this->dangChay->take($toiDa)->values();
    }
}
