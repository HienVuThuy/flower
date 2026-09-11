<?php

namespace App\Enums;

/**
 * Ai trả cước cho GHN trên một vận đơn.
 * ============================================================
 * CỬA HÀNG TRẢ, không phải người nhận.
 *
 * Khách đã trả phí giao cho CỬA HÀNG ngay lúc đặt: `shipping_fee` nằm
 * trong `grand_total`, và `grand_total` là số khách chuyển qua MoMo hoặc
 * số shipper thu hộ. Để GHN thu cước của người nhận nữa là THU HAI LẦN:
 *
 *   đơn MoMo  khách trả phí ship qua MoMo, shipper tới cửa đòi thêm cước
 *   đơn COD   tiền thu hộ đã gồm phí ship, shipper thu thêm cước lần nữa
 *   miễn phí  cửa hàng hứa miễn, shipper vẫn thu của người nhận
 *
 * Bản đầu gửi GHN `payment_type_id = 2` (người nhận trả) với lý do "phí
 * giao đã cộng vào tổng tiền khách thanh toán" — đúng lý do để chọn 1.
 *
 * VÌ SAO LƯU LẠI TRÊN TỪNG ĐƠN thay vì chỉ sửa hằng số: những vận đơn
 * tạo trước khi sửa vẫn mang giá trị cũ ở phía GHN (xem bằng API chi
 * tiết vận đơn: `payment_type_id: 2`). Với chúng, cửa hàng KHÔNG trả GHN
 * đồng nào — báo cáo "cửa hàng bù ship" mà tính cả chúng là bịa ra một
 * khoản chi không có thật.
 */
enum GhnFeePayer: string
{
    case Shop = 'shop';
    case Buyer = 'buyer';

    /** Mã GHN dùng trong `payment_type_id`. */
    public function ghnCode(): int
    {
        return match ($this) {
            self::Shop => 1,
            self::Buyer => 2,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Shop => 'Cửa hàng trả cước',
            self::Buyer => 'Người nhận trả cước',
        };
    }
}
