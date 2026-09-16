<?php

namespace App\Enums;

/** Vận đơn GHN đang ở đâu, dịch sang tiếng khách đọc được. */
enum ShippingStatus: string
{
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

    public function daRoiCuaHang(): bool
    {
        return ! in_array($this, [self::NotShipped, self::ReadyToPick, self::Picking], strict: true);
    }
}
