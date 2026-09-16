<?php

/*
 * CHẤM ĐIỂM RỦI RO ĐƠN HÀNG
 * ⚠️ SỐ ĐIỂM VÀ NGƯỠNG DƯỚI ĐÂY LÀ ĐIỂM KHỞI ĐẦU, KHÔNG PHẢI KẾT QUẢ
 */

return [

    'weights' => [
        'cancelled_before' => 20,

        'high_value_cod' => 25,

        'guest' => 10,

        'no_email' => 10,

        'burst_orders' => 20,
    ],

    'cancelled_cap' => 40,

    'thresholds' => [
        'high_value' => (float) env('RISK_HIGH_VALUE', 1500000),

        'burst_count' => 3,
        'burst_hours' => 24,
    ],

    'review_from' => 40,

];
