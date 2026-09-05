<?php

namespace App\Services\Shipping;

use App\Services\Checkout\CheckoutBasket;
use Illuminate\Support\Facades\Log;

/**
 * Hỏi GHN "giao tới đây tốn bao nhiêu" — và tự tính lại ở phía máy chủ.
 * ============================================================
 * ĐÂY LÀ ĐIỂM KHÁC QUAN TRỌNG NHẤT SO VỚI TÀI LIỆU HƯỚNG DẪN.
 *
 * Tài liệu cho JavaScript tính phí rồi ghi tổng tiền vào một ô ẩn
 * (`total_price_input`), và máy chủ lấy con số đó làm tiền phải trả.
 * Cách đó nghĩa là TRÌNH DUYỆT QUYẾT ĐỊNH GIÁ: mở DevTools sửa một dòng
 * là được giao miễn phí đi Cà Mau, và không có bản ghi nào cho thấy
 * chuyện đã xảy ra.
 *
 * Ở đây, con số hiện trên màn hình chỉ để khách XEM TRƯỚC. Lúc ghi đơn,
 * máy chủ hỏi lại GHN bằng chính mã quận/phường đã lưu và dùng câu trả
 * lời đó. Cùng nguyên tắc với mã giảm giá: trình duyệt gửi lên MÃ, không
 * bao giờ gửi lên SỐ TIỀN.
 *
 * CÓ ĐƯỜNG LÙI KHI GHN KHÔNG TRẢ LỜI.
 * GHN sập, hết hạn mức, địa chỉ chưa hỗ trợ — cả ba đều có thật. Khi đó
 * lùi về bảng phí theo tỉnh (ShippingRates) thay vì chặn khách đặt hàng
 * hoặc cho giao miễn phí. Bảng phí phẳng kém chính xác hơn, nhưng nó
 * luôn có một con số, và một con số gần đúng vẫn tốt hơn một trang lỗi.
 */
class ShippingQuote
{
    public function __construct(
        private readonly GHNService $ghn,
        private readonly ShippingRates $rates,
    ) {
    }

    /**
     * Phí giao cho một giỏ hàng tới một địa chỉ GHN cụ thể.
     *
     * Trả về CHUỖI thập phân 2 số cho bcmath — mọi phép tính tiền trong
     * dự án dùng bcmath, và trộn float vào giữa là mở đường cho sai số
     * lẻ, đúng thứ bcmath sinh ra để tránh.
     */
    public function feeFor(CheckoutBasket $basket, ?int $districtId, ?string $wardCode): string
    {
        $fee = $this->ghnFee($basket, $districtId, $wardCode);

        if ($fee !== null) {
            return number_format($fee, 2, '.', '');
        }

        /*
         * Không hỏi được GHN thì dùng bảng phí theo tỉnh.
         *
         * Ghi log ở tầng GHNService, không ghi lại ở đây: lùi về bảng
         * phí là hành vi BÌNH THƯỜNG khi khách chưa chọn xong địa chỉ,
         * và ghi log mỗi lần dựng lại trang giỏ hàng thì log đầy tới mức
         * không ai đọc.
         */
        return $this->rates->feeFor($basket->province);
    }

    /**
     * Phí GHN dạng số nguyên VNĐ, hoặc null nếu không hỏi được.
     *
     * Tách riêng vì đơn hàng cần LƯU LẠI đúng con số GHN báo
     * (`orders.ghn_total_fee`) để sau này đối soát — tách biệt với con
     * số cửa hàng thu của khách.
     */
    public function ghnFee(CheckoutBasket $basket, ?int $districtId, ?string $wardCode): ?int
    {
        if (! $districtId || ! $wardCode) {
            return null;
        }

        $from = (int) config('services.ghn.from_district_id');

        if ($from <= 0) {
            Log::warning('Chưa khai GHN_FROM_DISTRICT_ID nên không tính được phí GHN.');

            return null;
        }

        $response = $this->ghn->calculateFee(array_merge([
            'from_district_id' => $from,
            'to_district_id' => $districtId,
            'to_ward_code' => $wardCode,
        ], $this->ghn->packageParameters($this->weightOf($basket))));

        if (($response['code'] ?? null) !== 200) {
            return null;
        }

        $total = $response['data']['total'] ?? null;

        return is_numeric($total) ? (int) $total : null;
    }

    /**
     * Tổng khối lượng giỏ hàng, tính bằng gram.
     *
     * Nhân với SỐ LƯỢNG: mua mười chậu thì nặng gấp mười, và quên nhân
     * là báo phí bằng một phần mười thực tế — phần chênh cửa hàng chịu.
     */
    public function weightOf(CheckoutBasket $basket): int
    {
        $tong = 0;

        foreach ($basket->lines as $line) {
            $tong += $line->product->shippingWeight($line->variant) * $line->quantity;
        }

        /*
         * Giỏ rỗng hoặc mọi món chưa khai cân nặng vẫn phải ra một con
         * số dương: GHN từ chối thẳng đơn có khối lượng 0.
         */
        return max($tong, (int) config('services.ghn.default_weight', 200));
    }
}
