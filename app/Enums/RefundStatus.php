<?php

namespace App\Enums;

/**
 * Tiền của một lần hoàn đã tới tay khách chưa.
 * ============================================================
 * BA TRẠNG THÁI, và "đang xử lý" không phải thứ thêm vào cho đủ bộ:
 *
 *   Pending    ĐÃ GIỮ CHỖ số tiền, chưa chắc tiền đã đi. Chỉ có ở hoàn
 *              qua MoMo: bản ghi được tạo TRƯỚC khi gọi MoMo, để hai admin
 *              bấm cùng lúc không hoàn quá số khách đã trả. Nếu MoMo không
 *              trả lời (mất mạng, hết thời gian chờ), bản ghi ở lại đây và
 *              người thật phải kiểm trên cổng MoMo rồi xác nhận.
 *   Completed  Tiền đã trả lại khách. Chỉ trạng thái này được tính là
 *              "đã hoàn" — cho trạng thái thanh toán của đơn, cho doanh
 *              thu thuần, và để cộng hàng trả về vào kho.
 *   Failed     Không hoàn được. Số tiền giữ chỗ được nhả ra.
 */
enum RefundStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Chưa rõ kết quả',
            self::Completed => 'Đã hoàn',
            self::Failed => 'Không thành công',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Completed => 'success',
            self::Failed => 'danger',
        };
    }

    /** Có đang chiếm một phần số tiền còn hoàn được hay không. */
    public function giuChoTien(): bool
    {
        return $this !== self::Failed;
    }
}
