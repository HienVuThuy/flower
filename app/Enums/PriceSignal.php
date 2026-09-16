<?php

namespace App\Enums;

/** Các tình huống giá mà số liệu nhu cầu có thể chỉ ra. */
enum PriceSignal: string
{
    case InterestNoSale = 'interest_no_sale';

    case CartNotCheckout = 'cart_not_checkout';

    case StaleStock = 'stale_stock';

    case DiscountNotWorking = 'discount_not_working';

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

    public function suggestion(): string
    {
        return match ($this) {
            self::InterestNoSale => 'Cân nhắc một chương trình giảm giá thử trong 1–2 tuần, '
                .'hoặc xem lại ảnh và mô tả — khách quan tâm nhưng dừng lại ở trang sản phẩm.',

            self::CartNotCheckout => 'Giá có vẻ KHÔNG phải vấn đề — khách đã bỏ vào giỏ. '
                .'Kiểm tra phí vận chuyển và các bước thanh toán trước khi nghĩ tới giảm giá.',

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
