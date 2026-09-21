<?php

/** Lịch các dịp bán hoa trong năm. 'dip' nối sang nhãn Dịp tặng (GiftOccasion) để trang chủ nhắc đúng lúc. */
return [

    ['key' => 'tet-duong-lich', 'name' => 'Tết Dương lịch', 'day' => 1, 'month' => 1, 'weight' => 3],

    ['key' => 'valentine', 'dip' => 'tinh-yeu', 'name' => 'Lễ Tình nhân (Valentine)', 'day' => 14, 'month' => 2, 'weight' => 1,
        'note' => 'Cao điểm hoa hồng — chuẩn bị hàng trước ít nhất một tuần.'],

    ['key' => 'thay-thuoc', 'dip' => 'chuc-mung', 'name' => 'Ngày Thầy thuốc Việt Nam', 'day' => 27, 'month' => 2, 'weight' => 3],

    ['key' => 'quoc-te-phu-nu', 'dip' => 'chuc-mung', 'name' => 'Quốc tế Phụ nữ', 'day' => 8, 'month' => 3, 'weight' => 1,
        'note' => 'Một trong hai ngày bán nhiều nhất năm của ngành hoa.'],

    ['key' => 'gio-to', 'name' => 'Giỗ Tổ Hùng Vương', 'day' => null, 'month' => null, 'weight' => 4,
        'lunar' => true, 'lunar_note' => 'mùng 10 tháng 3 âm lịch'],

    ['key' => 'giai-phong', 'name' => 'Ngày Giải phóng miền Nam', 'day' => 30, 'month' => 4, 'weight' => 4],

    ['key' => 'quoc-te-thieu-nhi', 'name' => 'Quốc tế Thiếu nhi', 'day' => 1, 'month' => 6, 'weight' => 4],

    ['key' => 'quoc-khanh', 'name' => 'Quốc khánh', 'day' => 2, 'month' => 9, 'weight' => 4],

    ['key' => 'phu-nu-viet-nam', 'dip' => 'chuc-mung', 'name' => 'Ngày Phụ nữ Việt Nam', 'day' => 20, 'month' => 10, 'weight' => 1,
        'note' => 'Ngang 8/3 về lượng đặt hoa.'],

    ['key' => 'nha-giao', 'dip' => 'chuc-mung', 'name' => 'Ngày Nhà giáo Việt Nam', 'day' => 20, 'month' => 11, 'weight' => 1,
        'note' => 'Nhu cầu lớn về hoa bó và chậu cây làm quà tặng thầy cô.'],

    ['key' => 'giang-sinh', 'name' => 'Giáng sinh', 'day' => 24, 'month' => 12, 'weight' => 2,
        'note' => 'Kèm theo nhu cầu phụ kiện trang trí.'],

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
