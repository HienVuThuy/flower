<?php

return [

    /* | "HÀNG MỚI VỀ" — MỚI TRONG BAO NHIÊU NGÀY */

    'new_arrival_days' => (int) env('CATALOG_NEW_ARRIVAL_DAYS', 60),

    'new_arrival_limit' => 8,

    /*
     * Khoảng giá gợi sẵn ở bộ lọc — [từ, đến], null là không chặn.
     * Chỉ ghi con số, không đặt tên kiểu "giá rẻ": khách tự chọn ngân sách của mình.
     */
    'khoang_gia' => [
        [null, 300000],
        [300000, 500000],
        [500000, 1000000],
        [1000000, null],
    ],

    /* Bộ sưu tập "Hoa cao cấp": hoa có giá đang bán (sau khuyến mại) từ mức này. */
    'cao_cap_tu' => (int) env('CATALOG_CAO_CAP_TU', 800000),

];
