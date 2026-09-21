<?php

/*
 * PHÍ GIAO HÀNG THEO VÙNG
 * ⚠️ SỐ TIỀN DƯỚI ĐÂY LÀ MỨC MẪU, KHÔNG PHẢI BẢNG GIÁ THẬT.
 * Phí thật do GHN tính theo địa chỉ; bảng vùng dưới đây chỉ là DỰ PHÒNG khi GHN
 * không trả lời được. Mức miễn phí giao admin đổi ở Cài đặt › Tham số kinh doanh.
 */

return [

    'default_zone' => 'far',

    'zones' => [
        'inner' => [
            'label' => 'Nội thành Hà Nội',
            'fee' => (float) env('SHIPPING_FEE_INNER', 25000),
        ],
        'near' => [
            'label' => 'Tỉnh lân cận',
            'fee' => (float) env('SHIPPING_FEE_NEAR', 35000),
        ],
        'far' => [
            'label' => 'Tỉnh xa',
            'fee' => (float) env('SHIPPING_FEE_FAR', 50000),
        ],
        'remote' => [
            'label' => 'Miền núi, hải đảo',
            'fee' => (float) env('SHIPPING_FEE_REMOTE', 70000),
        ],
    ],

    'zone_of' => [
        'Thành phố Hà Nội' => 'inner',

        'Bắc Ninh' => 'near',
        'Hưng Yên' => 'near',
        'Phú Thọ' => 'near',
        'Thái Nguyên' => 'near',
        'Ninh Bình' => 'near',
        'Thành phố Hải Phòng' => 'near',
        'Quảng Ninh' => 'near',

        'Lai Châu' => 'remote',
        'Điện Biên' => 'remote',
        'Sơn La' => 'remote',
        'Lào Cai' => 'remote',
        'Tuyên Quang' => 'remote',
        'Cao Bằng' => 'remote',
        'Lạng Sơn' => 'remote',
        'Đắk Lắk' => 'remote',
        'Gia Lai' => 'remote',
        'Lâm Đồng' => 'remote',
    ],

    'free_from' => (float) env('SHIPPING_FREE_FROM', 500000),

];
