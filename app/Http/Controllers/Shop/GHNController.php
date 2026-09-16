<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Services\Checkout\CheckoutSource;
use App\Services\Shipping\GHNService;
use App\Services\Shipping\ShippingRates;
use App\Services\Shipping\ShippingQuote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Danh mục địa giới và báo giá cước, phục vụ màn hình thanh toán. */
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
