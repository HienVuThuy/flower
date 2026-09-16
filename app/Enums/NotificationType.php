<?php

namespace App\Enums;

/** Loại thông báo trong trang. */
enum NotificationType: string
{
    case BinhLuanBai = 'binh_luan_bai';
    case TraLoi = 'tra_loi';
    case BaiDuocDuyet = 'bai_duoc_duyet';
    case BaiTuChoi = 'bai_tu_choi';
    case BaiBiAn = 'bai_bi_an';

    public function label(): string
    {
        return match ($this) {
            self::BinhLuanBai => 'đã bình luận bài của bạn',
            self::TraLoi => 'đã trả lời bình luận của bạn',
            self::BaiDuocDuyet => 'Bài của bạn đã được duyệt và đang hiển thị',
            self::BaiTuChoi => 'Bài của bạn không được duyệt',
            self::BaiBiAn => 'Bài của bạn đã bị ẩn',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::BinhLuanBai, self::TraLoi => 'chat',
            self::BaiDuocDuyet => 'check-circle',
            self::BaiTuChoi => 'x-circle',
            self::BaiBiAn => 'eye-slash',
        };
    }

    public function cuaCuaHang(): bool
    {
        return in_array($this, [self::BaiDuocDuyet, self::BaiTuChoi, self::BaiBiAn], true);
    }
}
