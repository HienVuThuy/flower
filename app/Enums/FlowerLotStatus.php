<?php

namespace App\Enums;

/**
 * Lô hoa đang dùng hay đã dùng hết.
 * ============================================================
 * CHỈ HAI TRẠNG THÁI, và ranh giới là một hành động có thật: đóng lô.
 *
 * Đóng lô là lúc người bán nói "lô này hết rồi" — và cũng là lúc duy
 * nhất ghi hao hụt. Giá vốn hoa của một kỳ chỉ đếm lô ĐÃ ĐÓNG trong kỳ
 * đó: lô còn đang dùng thì hoa vẫn nằm trong xô, chưa thành giá vốn.
 */
enum FlowerLotStatus: string
{
    case DangDung = 'dang_dung';
    case DaDong = 'da_dong';

    public function label(): string
    {
        return match ($this) {
            self::DangDung => 'Đang dùng',
            self::DaDong => 'Đã đóng lô',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::DangDung => 'warning',
            self::DaDong => 'success',
        };
    }
}
