<?php

namespace App\Services\Shipping;

use App\Enums\GhnFeePayer;
use App\Enums\PaymentStatus;
use App\Models\Order;
use Illuminate\Support\Facades\Log;

/**
 * Tạo vận đơn GHN từ một đơn hàng của hệ thống.
 * ============================================================
 * Nhiệm vụ đúng như tài liệu nêu: đọc các sản phẩm trong đơn, cộng
 * trọng lượng, chuyển sang định dạng GHN, xác định tiền thu hộ, dựng
 * payload rồi gọi GHNService::createOrder().
 *
 * TÁCH RIÊNG KHỎI GHNService là có lý do: GHNService không biết gì về
 * `Order` — nó chỉ biết nói chuyện HTTP với GHN. Lớp này biết nghiệp vụ
 * của cửa hàng nhưng không biết gọi HTTP. Trộn hai việc thì đổi cách gọi
 * API phải đụng vào luật nghiệp vụ, và ngược lại.
 */
class GHNOrderService
{
    public function __construct(
        private readonly GHNService $ghn,
    ) {
    }

    /**
     * Tạo vận đơn và ghi mã trả về vào đơn hàng.
     *
     * @return array câu trả lời thô của GHN, để nơi gọi hiện thông báo
     */
    public function create(Order $order): array
    {
        /*
         * ĐÃ CÓ VẬN ĐƠN THÌ KHÔNG TẠO LẦN HAI.
         *
         * Bấm hai lần vì trang chậm là đủ để tạo hai vận đơn cho cùng
         * một đơn hàng — GHN tính tiền cả hai, và một kiện sẽ tới nơi
         * mà không ai chờ. Đây là phép kiểm rẻ nhất chặn được chuyện đó.
         */
        if ($order->ghn_order_code) {
            return [
                'code' => 200,
                'message' => 'Đơn này đã có mã vận đơn '.$order->ghn_order_code.'.',
                'data' => ['order_code' => $order->ghn_order_code],
                'da_co_san' => true,
            ];
        }

        if (! $order->to_district_id || ! $order->to_ward_code) {
            return [
                'code' => -1,
                'message' => 'Đơn này chưa có mã quận/phường của GHN nên không tạo vận đơn được. '
                    .'Đơn đặt trước khi bật tính phí GHN sẽ rơi vào trường hợp này.',
            ];
        }

        $order->loadMissing('items.product');

        $items = [];
        $weight = 0;

        foreach ($order->items as $item) {
            /*
             * Cân nặng lấy từ SẢN PHẨM, không lấy từ đơn.
             *
             * Đơn hàng chụp lại tên và giá tại thời điểm mua, nhưng
             * không chụp cân nặng — vì cân nặng không phải thứ khách trả
             * tiền cho, nó chỉ dùng để tính cước lúc gửi. Sản phẩm bị
             * xoá thì lùi về mức mặc định.
             */
            $itemWeight = $item->product
                ? $item->product->shippingWeight($item->variant)
                : (int) config('services.ghn.default_weight', 200);

            $weight += $itemWeight * (int) $item->quantity;

            $items[] = [
                // product_name là bản CHỤP trên đơn — dùng nó chứ không
                // đọc lại tên sản phẩm hiện tại, để phiếu giao khớp với
                // hoá đơn khách đã nhận.
                'name' => $item->product_name ?: 'Sản phẩm',
                'quantity' => (int) $item->quantity,
                'price' => (int) round((float) $item->unit_price),
                'weight' => $itemWeight,
            ];
        }

        /*
         * CỬA HÀNG TRẢ CƯỚC (payment_type_id = 1).
         *
         * Bản đầu gửi 2 — người nhận trả — với lý do "phí giao đã cộng
         * vào tổng tiền khách thanh toán". Đó chính là lý do để KHÔNG
         * chọn 2: khách đã trả phí ship cho cửa hàng rồi, GHN thu thêm
         * của người nhận là thu hai lần. Xem App\Enums\GhnFeePayer.
         */
        $nguoiTra = GhnFeePayer::Shop;

        $response = $this->ghn->createOrder(array_merge([
            'payment_type_id' => $nguoiTra->ghnCode(),
            'note' => 'Đơn hàng '.$order->order_number,

            /*
             * KHONGCHOXEMHANG cho hàng tươi.
             *
             * Hoa mở ra xem là hỏng dáng, và không gói lại được như cũ.
             * Ba mức GHN cho phép: CHOTHUHANG (cho thử), CHOXEMHANGKHONGTHU
             * (xem không thử), KHONGCHOXEMHANG.
             */
            'required_note' => 'KHONGCHOXEMHANG',

            'to_name' => $order->recipient_name,
            'to_phone' => $order->recipient_phone,
            'to_address' => $order->shipping_address,
            'to_ward_code' => (string) $order->to_ward_code,
            'to_district_id' => (int) $order->to_district_id,

            /*
             * TIỀN THU HỘ — chỉ thu khi khách CHƯA trả.
             *
             * Đọc `payment_status` của chính đơn chứ không nhận cờ từ
             * nơi gọi: nơi gọi có thể truyền sai, và truyền sai theo
             * hướng "đã trả rồi" nghĩa là shipper không thu tiền, hàng
             * đi mà cửa hàng không nhận được đồng nào.
             */
            'cod_amount' => $order->payment_status === PaymentStatus::Paid
                ? 0
                : (int) round((float) $order->grand_total),

            'items' => $items,
        ], $this->ghn->packageParameters($weight)));

        if (($response['code'] ?? null) === 200 && ! empty($response['data']['order_code'])) {
            /*
             * Lưu mã vận đơn VÀ phí GHN thật.
             *
             * `ghn_total_fee` khác `shipping_fee`: cột kia là tiền cửa
             * hàng THU của khách, cột này là tiền cửa hàng TRẢ cho GHN.
             * Hai con số lệch nhau mỗi khi có khuyến mại miễn phí giao,
             * và chỉ giữ cả hai mới biết tháng này bù lỗ bao nhiêu.
             */
            $order->ghn_order_code = $response['data']['order_code'];

            /*
             * GHN không báo cước thì để NULL, KHÔNG phải 0.
             *
             * 0 là "cước bằng không" — cộng vào báo cáo thì đơn này thành
             * đơn cửa hàng lãi trọn phí ship của khách. Và ghi đè cả con
             * số báo giá lúc đặt: báo giá cũ không phải cước của vận đơn
             * này, giữ lại là để một con số sai trông như số liệu thật.
             */
            $cuoc = $response['data']['total_fee'] ?? null;
            $order->ghn_total_fee = is_numeric($cuoc) ? (int) $cuoc : null;

            // Chụp lại người trả cước ĐÚNG như đã gửi GHN, cùng lúc với mã.
            $order->ghn_fee_payer = $nguoiTra;
            $order->shipping_status = 'ready_to_pick';
            $order->save();

            Log::info('Đã tạo vận đơn GHN.', [
                'order_number' => $order->order_number,
                'ghn_order_code' => $order->ghn_order_code,
            ]);
        }

        return $response;
    }

    /**
     * Huỷ vận đơn.
     *
     * KHÔNG xoá `ghn_order_code` sau khi huỷ. Mã đó là bằng chứng cửa
     * hàng đã từng gửi hàng đi và đã huỷ — xoá đi thì đơn trông như chưa
     * bao giờ được bàn giao, và không đối soát được với hoá đơn GHN.
     */
    public function cancel(Order $order): array
    {
        if (! $order->ghn_order_code) {
            return ['code' => -1, 'message' => 'Đơn này chưa có vận đơn GHN để huỷ.'];
        }

        $response = $this->ghn->cancelOrder([$order->ghn_order_code]);

        if (($response['code'] ?? null) === 200) {
            $order->shipping_status = 'cancel';
            $order->save();

            Log::info('Đã huỷ vận đơn GHN.', [
                'order_number' => $order->order_number,
                'ghn_order_code' => $order->ghn_order_code,
            ]);
        }

        return $response;
    }
}
