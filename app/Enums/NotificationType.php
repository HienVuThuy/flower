<?php

namespace App\Enums;

/**
 * Loại thông báo trong trang.
 *
 * Câu chữ dựng lúc hiển thị từ chính sự việc (ai, bài nào) — ở đây chỉ giữ
 * phần cố định và biểu tượng.
 */
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

    /** Biểu tượng SVG trong sprite (icon giao diện luôn là SVG, không dùng emoji). */
    public function icon(): string
    {
        return match ($this) {
            self::BinhLuanBai, self::TraLoi => 'chat',
            self::BaiDuocDuyet => 'check-circle',
            self::BaiTuChoi => 'x-circle',
            self::BaiBiAn => 'eye-slash',
        };
    }

    /** Thông báo do CỬA HÀNG gây ra (không có người cụ thể). */
    public function cuaCuaHang(): bool
    {
        return in_array($this, [self::BaiDuocDuyet, self::BaiTuChoi, self::BaiBiAn], true);
    }
}
