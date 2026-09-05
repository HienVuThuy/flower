<?php

namespace App\Enums;

/**
 * Các tình huống giá mà số liệu nhu cầu có thể chỉ ra.
 * ============================================================
 * MỖI TÌNH HUỐNG LÀ MỘT CÂU HỎI KHÁC NHAU, KHÔNG PHẢI MỘT MỨC ĐỘ.
 *
 * Cám dỗ là gộp tất cả thành "nên giảm giá nhiều / vừa / ít". Nhưng bốn
 * trong năm tình huống dưới đây KHÔNG dẫn tới giảm giá, và một cái còn
 * dẫn tới điều ngược lại. Gộp chúng vào một thang là biến công cụ này
 * thành cái máy khuyên giảm giá — thứ mà admin sẽ bỏ qua sau tuần đầu.
 */
enum PriceSignal: string
{
    /** Nhiều người xem, không ai đặt. Giá là nghi phạm số một. */
    case InterestNoSale = 'interest_no_sale';

    /** Thêm giỏ nhiều nhưng ít đơn — nghẽn ở bước thanh toán, không phải ở giá. */
    case CartNotCheckout = 'cart_not_checkout';

    /** Còn hàng trong kho mà lâu không bán được. */
    case StaleStock = 'stale_stock';

    /** Đang giảm giá rồi mà vẫn không bán được. */
    case DiscountNotWorking = 'discount_not_working';

    /** Bán tốt và đang rẻ hơn mặt bằng danh mục. */
    case UnderpricedBestSeller = 'underpriced_best_seller';

    public function label(): string
    {
        return match ($this) {
            self::InterestNoSale => 'Nhiều người xem, chưa ai đặt',
            self::CartNotCheckout => 'Thêm giỏ nhiều, ít đơn',
            self::StaleStock => 'Tồn kho nằm lâu',
            self::DiscountNotWorking => 'Đang giảm giá mà vẫn không bán',
            self::UnderpricedBestSeller => 'Bán tốt, giá dưới mặt bằng',
        };
    }

    /**
     * Việc admin nên cân nhắc — VIẾT NHƯ MỘT ĐỀ XUẤT, không như một lệnh.
     *
     * Công cụ này nhìn thấy lượt xem và đơn hàng. Nó KHÔNG nhìn thấy giá
     * vốn, hợp đồng với nhà vườn, hàng sắp về, hay việc admin đang giữ
     * giá cao có chủ đích. Viết "hãy giảm 20%" là giả vờ biết những thứ
     * đó.
     */
    public function suggestion(): string
    {
        return match ($this) {
            self::InterestNoSale => 'Cân nhắc một chương trình giảm giá thử trong 1–2 tuần, '
                .'hoặc xem lại ảnh và mô tả — khách quan tâm nhưng dừng lại ở trang sản phẩm.',

            self::CartNotCheckout => 'Giá có vẻ KHÔNG phải vấn đề — khách đã bỏ vào giỏ. '
                .'Kiểm tra phí vận chuyển và các bước thanh toán trước khi nghĩ tới giảm giá.',

            /*
             * CÂU CẢNH BÁO MÙA VỤ LÀ BẮT BUỘC, KHÔNG PHẢI TRANG TRÍ.
             *
             * Lỗi thật đã thấy trên dữ liệu của cửa hàng: tháng 9, công
             * cụ khuyên xả "Cành đào phai chơi Tết" vì 57 ngày không bán
             * được và còn 5 cành trong kho. Cả hai con số đều đúng, và
             * kết luận thì sai hoàn toàn — đào không bán được vào tháng 9
             * là chuyện đương nhiên, không phải dấu hiệu ế.
             *
             * Không sửa được bằng dữ liệu: cần lịch sử bán ít nhất qua
             * một vòng năm mới nhìn ra chu kỳ, mà cửa hàng chưa có. Sửa
             * bằng cách ĐOÁN theo tên sản phẩm thì càng tệ — nó sẽ đúng
             * vài lần rồi sai một lần không ai kiểm được.
             *
             * Nên nói thẳng ra giới hạn. Admin biết món nào theo mùa;
             * công cụ thì không, và nó phải thừa nhận điều đó ngay trong
             * câu khuyên chứ không giấu xuống chú thích cuối trang.
             */
            self::StaleStock => 'Cân nhắc xả hàng: một chương trình có khung giờ, '
                .'hoặc bán kèm với sản phẩm đang chạy. '
                .'Lưu ý: công cụ KHÔNG biết hàng nào bán theo mùa — '
                .'đào, quất, hoa Tết nằm im trái vụ là bình thường.',

            self::DiscountNotWorking => 'Giảm sâu thêm nhiều khả năng cũng không giải quyết được. '
                .'Xem lại ảnh, mô tả, hoặc cân nhắc ngừng nhập món này.',

            self::UnderpricedBestSeller => 'Có thể nâng giá về sát mặt bằng danh mục, '
                .'hoặc giữ giá và dùng nó làm món kéo khách.',
        };
    }

    /** Màu thẻ trạng thái — dùng chung hệ với các trang quản trị khác. */
    public function badge(): string
    {
        return match ($this) {
            self::InterestNoSale => 'warning',
            self::CartNotCheckout => 'info',
            self::StaleStock => 'danger',
            self::DiscountNotWorking => 'danger',
            self::UnderpricedBestSeller => 'success',
        };
    }

    /**
     * Thứ tự ưu tiên hiển thị — số nhỏ lên trước.
     *
     * "Nhiều người xem chưa ai đặt" đứng đầu vì đó là tình huống mất tiền
     * rõ nhất và sửa được nhanh nhất: khách đã tự tìm tới tận nơi rồi.
     */
    public function rank(): int
    {
        return match ($this) {
            self::InterestNoSale => 1,
            self::DiscountNotWorking => 2,
            self::StaleStock => 3,
            self::CartNotCheckout => 4,
            self::UnderpricedBestSeller => 5,
        };
    }
}
