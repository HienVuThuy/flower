<?php

namespace App\Enums;

/**
 * Vận đơn GHN đang ở đâu, dịch sang tiếng khách đọc được.
 * ============================================================
 * `orders.shipping_status` lưu NGUYÊN VĂN mã của GHN (`ready_to_pick`,
 * `delivering`...). Cột đó KHÔNG cast sang enum này, và đó là chủ ý:
 * GHN thêm trạng thái mới bất cứ lúc nào, và một cast sẽ ném lỗi giữa
 * trang đơn hàng của khách vì một chuỗi lạ.
 *
 * Dùng `tuGhn()` để dịch: mã lạ trả về null, và giao diện lùi về câu
 * chung "Đang vận chuyển" thay vì gãy.
 *
 * ============================================================
 * KHÁCH KHÔNG CẦN BIẾT ĐỦ MƯỜI MẤY BƯỚC CỦA GHN.
 *
 * "sorting" (đang phân loại ở kho trung chuyển) là ngôn ngữ nội bộ của
 * đơn vị vận chuyển; với người đang đợi hoa thì nó chỉ có nghĩa "hàng
 * đang trên đường". Vì thế nhiều mã GHN gộp về cùng một câu.
 */
enum ShippingStatus: string
{
    /*
     * KHÔNG phải mã của GHN — đây là giá trị mặc định của cột, nghĩa là
     * cửa hàng chưa bàn giao cho ai cả. Xem migration
     * add_ghn_shipping_to_orders_table.
     */
    case NotShipped = 'not_shipped';

    case ReadyToPick = 'ready_to_pick';
    case Picking = 'picking';
    case Picked = 'picked';
    case Storing = 'storing';
    case Transporting = 'transporting';
    case Sorting = 'sorting';
    case Delivering = 'delivering';
    case Delivered = 'delivered';
    case DeliveryFail = 'delivery_fail';
    case Returned = 'returned';
    case Cancel = 'cancel';
    case Lost = 'lost';

    /** Mã lạ (GHN thêm trạng thái mới) trả về null chứ không ném lỗi. */
    public static function tuGhn(?string $raw): ?self
    {
        return $raw === null ? null : self::tryFrom($raw);
    }

    public function label(): string
    {
        return match ($this) {
            self::NotShipped => 'Chưa bàn giao vận chuyển',
            self::ReadyToPick => 'Chờ đơn vị vận chuyển lấy hàng',
            self::Picking => 'Đang lấy hàng tại cửa hàng',
            self::Picked, self::Storing, self::Transporting, self::Sorting => 'Đang vận chuyển',
            self::Delivering => 'Đang giao đến bạn',
            self::Delivered => 'Đã giao thành công',
            self::DeliveryFail => 'Giao không thành công',
            self::Returned => 'Đã hoàn về cửa hàng',
            self::Cancel => 'Vận đơn đã huỷ',
            self::Lost => 'Thất lạc',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Delivered => 'success',
            self::DeliveryFail, self::Lost => 'danger',
            self::Returned, self::Cancel => 'secondary',
            default => 'warning',
        };
    }

    /**
     * Câu nói rõ khách cần làm gì, hoặc điều gì sắp xảy ra.
     *
     * Một cái nhãn trạng thái không trả lời được câu hỏi thật của người
     * đang đợi hàng: "vậy giờ tôi phải làm gì". Với trạng thái tốt thì
     * câu này trấn an; với trạng thái hỏng thì nó chỉ đường.
     */
    public function hint(): string
    {
        return match ($this) {
            self::NotShipped => 'Cửa hàng đang chuẩn bị hàng, chưa bàn giao cho đơn vị vận chuyển.',
            self::ReadyToPick => 'Cửa hàng đã gói hàng xong và bàn giao cho đơn vị vận chuyển.',
            self::Picking => 'Nhân viên vận chuyển đang tới cửa hàng lấy hàng.',
            self::Picked, self::Storing, self::Transporting, self::Sorting
                => 'Hàng đã rời cửa hàng và đang trên đường tới bạn.',
            self::Delivering => 'Nhân viên giao hàng sẽ liên hệ với bạn trong hôm nay.',
            self::Delivered => 'Cảm ơn bạn đã mua hàng. Nếu có vấn đề với đơn, hãy liên hệ cửa hàng.',
            self::DeliveryFail => 'Đơn vị vận chuyển sẽ thử giao lại. Hãy để ý điện thoại.',
            self::Returned => 'Hàng đã quay về cửa hàng. Vui lòng liên hệ để được hỗ trợ.',
            self::Cancel => 'Vận đơn này đã bị huỷ. Vui lòng liên hệ cửa hàng.',
            self::Lost => 'Cửa hàng đang làm việc với đơn vị vận chuyển. Chúng tôi sẽ liên hệ với bạn.',
        };
    }

    /** Hàng đã rời cửa hàng chưa. */
    public function daRoiCuaHang(): bool
    {
        return ! in_array($this, [self::NotShipped, self::ReadyToPick, self::Picking], strict: true);
    }
}
