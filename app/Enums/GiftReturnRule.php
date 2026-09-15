<?php

namespace App\Enums;

/**
 * Khách trả món chính thì quà đi đâu — chọn riêng cho từng món quà.
 * Chỉ là giá trị ĐIỀN SẴN trên phiếu trả; người lập phiếu vẫn sửa được.
 */
enum GiftReturnRule: string
{
    case KemQua = 'kem_qua';
    case KhongThuHoi = 'khong_thu_hoi';

    public function label(): string
    {
        return match ($this) {
            self::KemQua => 'Trả kèm quà',
            self::KhongThuHoi => 'Không thu hồi quà',
        };
    }

    public function hint(): string
    {
        return match ($this) {
            self::KemQua => 'Phiếu trả tự điền số quà ứng với số món chính trả về (không trừ tiền).',
            self::KhongThuHoi => 'Khách giữ quà — sticker, thiệp, thứ không ai đòi lại.',
        };
    }
}
