<?php

return [

    /* | "HÀNG MỚI VỀ" — MỚI TRONG BAO NHIÊU NGÀY */

    'new_arrival_days' => (int) env('CATALOG_NEW_ARRIVAL_DAYS', 60),

    'new_arrival_limit' => 8,

    /*
     * Mốc chia khoảng giá gợi sẵn ở bộ lọc: [300k, 500k, 1tr] thành
     * "Dưới 300k", "300k – 500k", "500k – 1tr", "Từ 1tr".
     * Chỉ ghi con số, không đặt tên kiểu "giá rẻ": khách tự chọn ngân sách của mình.
     */
    'moc_gia' => [300000, 500000, 1000000],

    /* Bộ sưu tập "Hoa cao cấp": hoa có giá đang bán (sau khuyến mại) từ mức này. */
    'cao_cap_tu' => (int) env('CATALOG_CAO_CAP_TU', 800000),

];
