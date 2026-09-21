<?php

/*
 * CHẤM ĐIỂM RỦI RO ĐƠN HÀNG
 * ⚠️ SỐ ĐIỂM VÀ NGƯỠNG DƯỚI ĐÂY LÀ ĐIỂM KHỞI ĐẦU, KHÔNG PHẢI KẾT QUẢ PHÂN TÍCH DỮ LIỆU.
 * Chỉ đơn trả khi nhận (COD) mới xét khả năng bom hàng; đơn đã trả trước chỉ xét đặt dồn dập.
 */

return [

    'weights' => [
        'cancelled_before' => 20,

        'high_value_cod' => 25,

        'guest' => 5,

        'no_email' => 5,

        'burst_orders' => 20,

        /* Đơn COD có hoa tươi: khách không nhận là hoa héo, mất trắng. */
        'fresh_flower_cod' => 10,

        /* Khách đã nhận thành công từ trusted_from đơn trở lên: TRỪ điểm. */
        'trusted' => 20,
    ],

    'cancelled_cap' => 40,

    'thresholds' => [
        'high_value' => (float) env('RISK_HIGH_VALUE', 1500000),

        'burst_count' => 3,
        'burst_hours' => 24,

        'trusted_from' => 2,
    ],

    'review_from' => 40,

];
