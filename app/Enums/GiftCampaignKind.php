<?php

namespace App\Enums;

/** Hai nhánh quà tặng. Xem migration create_gift_tables. */
enum GiftCampaignKind: string
{
    case KemSanPham = 'kem_san_pham';
    case ChuongTrinh = 'chuong_trinh';

    public function label(): string
    {
        return match ($this) {
            self::KemSanPham => 'Tặng kèm khi mua sản phẩm',
            self::ChuongTrinh => 'Chương trình quà tặng',
        };
    }

    public function hint(): string
    {
        return match ($this) {
            self::KemSanPham => 'Mua đủ số lượng một sản phẩm thì đơn có thêm quà — hiện ngay trên trang sản phẩm đó.',
            self::ChuongTrinh => 'Quà theo sự kiện: giới hạn số suất, thời gian, hạng thành viên, đơn đầu tiên, đơn từ một số tiền.',
        };
    }
}
