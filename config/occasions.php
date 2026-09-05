<?php

/**
 * Lịch các dịp bán hoa trong năm.
 * ============================================================
 * DÙNG ĐỂ NHẮC, KHÔNG DÙNG ĐỂ TỰ TẠO CHƯƠNG TRÌNH.
 *
 * Công cụ chỉ đối chiếu "dịp này sắp tới, đã có chương trình nào phủ
 * chưa" rồi nhắc admin. Tự tạo chương trình khuyến mại là tự quyết định
 * giá bán — việc đó phải có người bấm nút.
 *
 * ============================================================
 * ÂM LỊCH KHÔNG CÓ NGÀY DƯƠNG CỐ ĐỊNH, VÀ ĐÂY LÀ CHỖ DỄ BỊA NHẤT.
 *
 * Tết Nguyên đán, Vu Lan, Trung thu rơi vào ngày dương khác nhau mỗi
 * năm. Viết cứng một ngày dương vào đây là ghi một dữ kiện SAI cho mọi
 * năm trừ một năm — và nó sai một cách im lặng, vì hệ thống vẫn chạy.
 *
 * PHP không có sẵn phép đổi âm–dương, và kéo về một thư viện lịch chỉ để
 * nhắc admin vài lần một năm là đổi một phụ thuộc lấy một tiện ích nhỏ.
 *
 * Nên các dịp âm lịch để `day`/`month` là null: công cụ liệt kê chúng ra
 * như một lời nhắc kèm ghi chú "ngày thay đổi theo năm", và admin tự
 * chọn ngày khi tạo chương trình. Nhắc đúng mà không có ngày thì vẫn hữu
 * ích; nhắc sai ngày thì tệ hơn không nhắc.
 */
return [

    /*
     * `weight` = mức quan trọng với một cửa hàng hoa (1 cao nhất).
     * Dùng để xếp thứ tự khi nhiều dịp cùng tới gần.
     */

    ['key' => 'tet-duong-lich', 'name' => 'Tết Dương lịch', 'day' => 1, 'month' => 1, 'weight' => 3],

    ['key' => 'valentine', 'name' => 'Lễ Tình nhân (Valentine)', 'day' => 14, 'month' => 2, 'weight' => 1,
        'note' => 'Cao điểm hoa hồng — chuẩn bị hàng trước ít nhất một tuần.'],

    ['key' => 'thay-thuoc', 'name' => 'Ngày Thầy thuốc Việt Nam', 'day' => 27, 'month' => 2, 'weight' => 3],

    ['key' => 'quoc-te-phu-nu', 'name' => 'Quốc tế Phụ nữ', 'day' => 8, 'month' => 3, 'weight' => 1,
        'note' => 'Một trong hai ngày bán nhiều nhất năm của ngành hoa.'],

    ['key' => 'gio-to', 'name' => 'Giỗ Tổ Hùng Vương', 'day' => null, 'month' => null, 'weight' => 4,
        'lunar' => true, 'lunar_note' => 'mùng 10 tháng 3 âm lịch'],

    ['key' => 'giai-phong', 'name' => 'Ngày Giải phóng miền Nam', 'day' => 30, 'month' => 4, 'weight' => 4],

    ['key' => 'quoc-te-thieu-nhi', 'name' => 'Quốc tế Thiếu nhi', 'day' => 1, 'month' => 6, 'weight' => 4],

    ['key' => 'quoc-khanh', 'name' => 'Quốc khánh', 'day' => 2, 'month' => 9, 'weight' => 4],

    ['key' => 'phu-nu-viet-nam', 'name' => 'Ngày Phụ nữ Việt Nam', 'day' => 20, 'month' => 10, 'weight' => 1,
        'note' => 'Ngang 8/3 về lượng đặt hoa.'],

    ['key' => 'nha-giao', 'name' => 'Ngày Nhà giáo Việt Nam', 'day' => 20, 'month' => 11, 'weight' => 1,
        'note' => 'Nhu cầu lớn về hoa bó và chậu cây làm quà tặng thầy cô.'],

    ['key' => 'giang-sinh', 'name' => 'Giáng sinh', 'day' => 24, 'month' => 12, 'weight' => 2,
        'note' => 'Kèm theo nhu cầu phụ kiện trang trí.'],

    /* ---------- Dịp âm lịch: KHÔNG ghi ngày dương ---------- */

    ['key' => 'ong-cong-ong-tao', 'name' => 'Ông Công ông Táo', 'day' => null, 'month' => null, 'weight' => 3,
        'lunar' => true, 'lunar_note' => '23 tháng Chạp'],

    ['key' => 'tet-nguyen-dan', 'name' => 'Tết Nguyên đán', 'day' => null, 'month' => null, 'weight' => 1,
        'lunar' => true, 'lunar_note' => 'mùng 1 tháng Giêng',
        'note' => 'Mùa cao điểm dài nhất năm: đào, quất, mai, cây chơi Tết.'],

    ['key' => 'ram-thang-gieng', 'name' => 'Rằm tháng Giêng', 'day' => null, 'month' => null, 'weight' => 3,
        'lunar' => true, 'lunar_note' => '15 tháng Giêng'],

    ['key' => 'vu-lan', 'name' => 'Lễ Vu Lan', 'day' => null, 'month' => null, 'weight' => 2,
        'lunar' => true, 'lunar_note' => '15 tháng 7 âm lịch',
        'note' => 'Hoa hồng cài áo, hoa cúng.'],

    ['key' => 'trung-thu', 'name' => 'Tết Trung thu', 'day' => null, 'month' => null, 'weight' => 3,
        'lunar' => true, 'lunar_note' => '15 tháng 8 âm lịch'],
];
