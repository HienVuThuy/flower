<?php

namespace App\Services\Shipping;

/**
 * Phí giao hàng của một tỉnh/thành.
 * ============================================================
 * NƠI DUY NHẤT biết "giao tới đây tốn bao nhiêu". CheckoutBasket gọi vào
 * đây, và CheckoutBasket là nơi duy nhất tính tiền cho việc thanh toán —
 * nên toàn hệ thống chỉ có một đường tính phí giao.
 *
 * KHÔNG BAO GIỜ NHẬN PHÍ TỪ TRÌNH DUYỆT. Phí luôn được tra lại từ tên
 * tỉnh mà máy chủ đã kiểm tra hợp lệ. Nhận số từ client thì khách sửa
 * một dòng trong DevTools là được giao miễn phí đi Lai Châu.
 */
class ShippingRates
{
    /**
     * Vùng của một tỉnh/thành.
     *
     * Tên rỗng, null, hoặc tỉnh chưa có trong bảng đều trả về vùng mặc
     * định. KHÔNG ném lỗi: hàm này chạy trong lúc tính tiền hiển thị trên
     * trang giỏ hàng, khi khách còn chưa nhập địa chỉ. Ném lỗi ở đó là
     * trang trắng cho một tình huống hoàn toàn bình thường.
     */
    public function zoneOf(?string $province): string
    {
        $default = (string) config('shipping.default_zone', 'far');

        if ($province === null || trim($province) === '') {
            return $default;
        }

        $zone = config('shipping.zone_of')[trim($province)] ?? $default;

        // Bảng ánh xạ trỏ tới một vùng không tồn tại (gõ sai lúc sửa
        // config) thì lùi về mặc định thay vì trả phí bằng 0 — miễn phí
        // vì gõ sai là cửa hàng mất tiền mà không ai hay.
        return isset(config('shipping.zones')[$zone]) ? $zone : $default;
    }

    /** Nhãn tiếng Việt của vùng, để hiện cho khách xem. */
    public function zoneLabel(?string $province): string
    {
        $zone = $this->zoneOf($province);

        return (string) (config("shipping.zones.{$zone}.label") ?? 'Không rõ vùng');
    }

    /**
     * Phí giao tới tỉnh này, dạng chuỗi thập phân 2 số cho bcmath.
     *
     * Trả về CHUỖI chứ không phải float: mọi phép tính tiền trong dự án
     * dùng bcmath, và trộn float vào giữa là mở đường cho sai số lẻ —
     * đúng thứ mà việc dùng bcmath sinh ra để tránh.
     */
    public function feeFor(?string $province): string
    {
        $zone = $this->zoneOf($province);
        $fee = config("shipping.zones.{$zone}.fee", 0);

        return number_format((float) $fee, 2, '.', '');
    }

    /** Ngưỡng tiền hàng được miễn phí giao. */
    public function freeFrom(): string
    {
        return number_format((float) config('shipping.free_from', 0), 2, '.', '');
    }

    /**
     * Toàn bộ bảng vùng, để hiện ở trang chính sách và cho lệnh kiểm tra.
     *
     * @return array<string, array{label: string, fee: float, provinces: list<string>}>
     */
    public function zones(): array
    {
        $out = [];

        foreach (config('shipping.zones', []) as $key => $zone) {
            $out[$key] = [
                'label' => $zone['label'],
                'fee' => (float) $zone['fee'],
                'provinces' => [],
            ];
        }

        foreach (config('shipping.zone_of', []) as $province => $zone) {
            if (isset($out[$zone])) {
                $out[$zone]['provinces'][] = $province;
            }
        }

        return $out;
    }
}
