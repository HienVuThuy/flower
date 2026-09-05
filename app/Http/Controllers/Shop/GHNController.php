<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Services\Checkout\CheckoutSource;
use App\Services\Shipping\GHNService;
use App\Services\Shipping\ShippingRates;
use App\Services\Shipping\ShippingQuote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Danh mục địa giới và báo giá cước, phục vụ màn hình thanh toán.
 * ============================================================
 * CHỈ TRẢ JSON. Đây là các endpoint cho JavaScript ở trang thanh toán
 * gọi khi khách chọn tỉnh → quận → phường.
 *
 * KHÔNG PHẢI CỔNG ĐỂ CHỐT GIÁ. Con số phí trả về đây chỉ để HIỂN THỊ.
 * Lúc ghi đơn, máy chủ hỏi lại GHN bằng chính mã quận/phường đã lưu —
 * xem ShippingQuote. Nếu tin con số đi qua trình duyệt thì sửa một dòng
 * trong DevTools là được giao miễn phí.
 */
class GHNController extends Controller
{
    public function __construct(
        private readonly CheckoutSource $source,
    ) {
    }

    public function getProvinces(GHNService $ghn): JsonResponse
    {
        return response()->json($ghn->getProvinces());
    }

    public function getDistricts(int $provinceId, GHNService $ghn): JsonResponse
    {
        return response()->json($ghn->getDistricts($provinceId));
    }

    public function getWards(int $districtId, GHNService $ghn): JsonResponse
    {
        return response()->json($ghn->getWards($districtId));
    }

    /**
     * Báo giá cước cho giỏ hàng hiện tại.
     *
     * KHỐI LƯỢNG LẤY TỪ GIỎ HÀNG THẬT Ở PHÍA MÁY CHỦ, không nhận từ
     * request. Nhận khối lượng từ trình duyệt thì khai 1 gram là ra cước
     * rẻ nhất — và vì đây cũng là con số hiện trên màn hình, khách sẽ
     * thấy một mức phí mà cửa hàng không bao giờ được hưởng.
     */
    public function getShippingFee(Request $request, ShippingQuote $quote): JsonResponse
    {
        $data = $request->validate([
            'to_district_id' => ['required', 'integer'],
            'to_ward_code' => ['required', 'string', 'max:20'],
        ], [], [
            'to_district_id' => 'quận/huyện',
            'to_ward_code' => 'phường/xã',
        ]);

        $basket = $this->source->basket();

        if ($basket->isEmpty()) {
            return response()->json([
                'code' => -1,
                'message' => 'Giỏ hàng đang trống.',
            ], 422);
        }

        $fee = $quote->ghnFee($basket, (int) $data['to_district_id'], $data['to_ward_code']);

        if ($fee === null) {
            /*
             * GHN không báo giá được — địa chỉ chưa hỗ trợ, hoặc dịch vụ
             * đang trục trặc. KHÔNG trả về 0: số 0 đọc là "miễn phí giao",
             * và khách sẽ đặt hàng với niềm tin đó.
             *
             * Trả về mức của bảng phí theo tỉnh kèm cờ `uoc_tinh` để
             * giao diện nói rõ đây là mức tạm.
             */
            /*
             * KHÔNG lấy phí từ $basket ở đây.
             *
             * `$basket->shippingFee()` nay trả về 0₫ khi phiên chưa có
             * địa chỉ — mà ở endpoint này thì phiên CHƯA có: khách vừa
             * chọn xong quận/phường trên màn hình, chưa gửi biểu mẫu.
             * Lấy từ đó là báo "miễn phí giao" cho một địa chỉ chưa hề
             * được tính.
             *
             * Ở đây khách ĐÃ chọn địa chỉ, nên đưa ra một mức dự phòng
             * là hợp lý — khác hẳn trang giỏ hàng, nơi chưa ai nhập gì.
             * Hỏi thẳng bảng phí vùng, và cờ `uoc_tinh` để giao diện nói
             * rõ đây là mức tạm.
             */
            $duPhong = (int) round((float) app(ShippingRates::class)->feeFor(null));

            return response()->json([
                'code' => 200,
                'message' => 'Chưa lấy được cước GHN, đang dùng mức phí chung.',
                'uoc_tinh' => true,
                'data' => ['total' => $duPhong],
            ]);
        }

        return response()->json([
            'code' => 200,
            'message' => 'Success',
            'uoc_tinh' => false,
            'data' => ['total' => $fee],
        ]);
    }
}
