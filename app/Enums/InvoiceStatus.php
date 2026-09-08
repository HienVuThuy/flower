<?php

namespace App\Enums;

/**
 * Hoá đơn đã đi tới đâu.
 * ============================================================
 * ⚠️ HỆ THỐNG HIỆN CHỈ TẠO RA `Draft`.
 *
 * `Issued` tồn tại vì nó là bước tiếp theo có thật trong nghiệp vụ, và
 * vì `invoices.status` cần một giá trị để mang khi cửa hàng tích hợp nhà
 * cung cấp hoá đơn điện tử. Nhưng KHÔNG có nút nào trong giao diện
 * chuyển sang nó, và sẽ không có cho tới khi việc phát hành là thật:
 * một nút "Phát hành" chỉ đổi một chữ trong cơ sở dữ liệu là nói dối
 * người dùng về một chứng từ pháp lý — loại nói dối tệ nhất.
 *
 * Vì thế `label()` của `Draft` viết thẳng ra là CHƯA phát hành.
 */
enum InvoiceStatus: string
{
    case Draft = 'draft';
    case Issued = 'issued';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Chưa phát hành',
            self::Issued => 'Đã phát hành',
            self::Cancelled => 'Đã huỷ',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Draft => 'warning',
            self::Issued => 'success',
            self::Cancelled => 'secondary',
        };
    }

    /** Câu nói rõ trạng thái này nghĩa là gì với khách. */
    public function hint(): string
    {
        return match ($this) {
            self::Draft => 'Cửa hàng đã ghi nhận yêu cầu và thông tin xuất hoá đơn. '
                . 'Hoá đơn điện tử sẽ được phát hành và gửi tới email bạn cung cấp.',
            self::Issued => 'Hoá đơn điện tử đã được phát hành.',
            self::Cancelled => 'Yêu cầu xuất hoá đơn đã được huỷ.',
        };
    }
}
