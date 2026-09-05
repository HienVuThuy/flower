<?php

/*
|--------------------------------------------------------------------------
| PHÍ GIAO HÀNG THEO VÙNG
|--------------------------------------------------------------------------
|
| ⚠️ SỐ TIỀN DƯỚI ĐÂY LÀ MỨC MẪU, KHÔNG PHẢI BẢNG GIÁ THẬT.
|
| Cửa hàng PHẢI thay bằng giá đã thoả thuận với đơn vị vận chuyển trước
| khi bán thật. Chúng được đặt ở đây để cơ chế tính phí theo vùng chạy
| được và kiểm chứng được, không phải để dùng luôn.
|
| ĐIỂM XUẤT PHÁT: MỘT cửa hàng duy nhất, ở Hà Nội.
|
| Địa chỉ thật của cửa hàng nằm trong bảng `settings` (khoá `site_address`)
| chứ không nằm ở đây — đó là dữ liệu cửa hàng tự sửa được, còn tệp này là
| bảng giá. Nhưng VÙNG phải suy ra từ nó, nên hai chỗ buộc phải khớp: dời
| cửa hàng sang tỉnh khác thì bảng dưới đây phải xếp lại từ đầu.
|
| VÌ SAO PHẢI CHIA VÙNG:
| Trước đây mọi đơn đều chịu một mức phí phẳng. Giao một bó hoa trong nội
| thành Hà Nội và giao lên Lai Châu rõ ràng không thể cùng giá — mức phẳng
| nghĩa là hoặc cửa hàng lỗ ở đơn xa, hoặc khách gần trả hộ khách xa.
|
| BỐN VÙNG, chia theo khoảng cách vận chuyển thực tế chứ không theo phân
| loại hành chính:
|   inner   - Hà Nội, nơi đặt cửa hàng: giao trong ngày được
|   near    - các tỉnh quanh Hà Nội, xe chạy trong ngày tới nơi
|   far     - phần còn lại của cả nước, kể cả TP. Hồ Chí Minh
|   remote  - miền núi phía Bắc, Tây Nguyên, hải đảo: xa và ít chuyến
|
| HOA TƯƠI KHÁC HÀNG THƯỜNG: đi càng lâu hoa càng hỏng. Vùng `remote`
| đắt hơn không chỉ vì quãng đường mà vì phải đóng gói giữ lạnh và chấp
| nhận tỉ lệ hỏng cao hơn.
|
| TÊN TỈNH PHẢI KHỚP CHÍNH XÁC config/provinces.php. Tỉnh không có trong
| bảng dưới đây rơi vào vùng mặc định — xem ShippingRates::zoneOf().
|
*/

return [

    /*
     * Vùng mặc định cho tỉnh chưa được xếp vào đâu.
     *
     * Chọn 'far' chứ KHÔNG chọn vùng rẻ nhất: sót một tỉnh mà tính giá
     * nội thành thì cửa hàng lỗ mà không ai phát hiện. Còn tính hơi cao
     * thì khách sẽ hỏi, và cửa hàng biết ngay là thiếu dữ liệu.
     */
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

    /*
     * Tỉnh/thành => vùng.
     *
     * Chỉ liệt kê những nơi KHÁC vùng mặc định, nên bảng này ngắn và dễ
     * soát. Thiếu một tỉnh không phải lỗi — nó rơi vào 'far'.
     */
    'zone_of' => [
        // Hai đô thị lớn — nơi cửa hàng có kho.
        /*
         * CHỈ HÀ NỘI LÀ `inner`.
         *
         * Bản trước xếp cả TP. Hồ Chí Minh vào `inner` vì giả định cửa
         * hàng có kho ở hai đầu. Cửa hàng thật chỉ có MỘT điểm, ở Hà Nội
         * — giao vào TP.HCM là chặng hơn 1.700km, không thể cùng giá với
         * giao trong quận. Để nguyên là cửa hàng lỗ ở mỗi đơn miền Nam.
         */
        'Thành phố Hà Nội' => 'inner',

        // Giáp Hà Nội / TP.HCM, xe trong ngày tới nơi.
        /*
         * Xe chạy trong ngày từ Hà Nội tới nơi được.
         *
         * ĐÃ BỎ Đồng Nai / Tây Ninh / Vĩnh Long / Đồng Tháp / An Giang:
         * chúng giáp TP. Hồ Chí Minh, không giáp Hà Nội. Khi cửa hàng còn
         * giả định có kho ở miền Nam thì xếp `near` là đúng; nay chỉ có
         * một kho ở Hà Nội thì đó là năm tỉnh được giao rẻ vô cớ.
         */
        'Bắc Ninh' => 'near',
        'Hưng Yên' => 'near',
        'Phú Thọ' => 'near',
        'Thái Nguyên' => 'near',
        'Ninh Bình' => 'near',
        'Thành phố Hải Phòng' => 'near',
        'Quảng Ninh' => 'near',

        // Miền núi phía Bắc, Tây Nguyên, hải đảo.
        'Lai Châu' => 'remote',
        'Điện Biên' => 'remote',
        'Sơn La' => 'remote',
        'Lào Cai' => 'remote',
        /*
         * KHÔNG có "Hà Giang" ở đây, và đó là chủ đích.
         *
         * Tỉnh Hà Giang đã nhập vào Tuyên Quang trong đợt sắp xếp 2025
         * nên không còn trong config/provinces.php. Giữ lại một dòng cho
         * tên đã mất là mở đường cho việc tưởng đã xếp vùng rồi, trong
         * khi thực tế địa chỉ mới không bao giờ mang tên đó nữa.
         * Chạy `php artisan shipping:zones` để soát những lệch kiểu này.
         */
        'Tuyên Quang' => 'remote',
        'Cao Bằng' => 'remote',
        'Lạng Sơn' => 'remote',
        'Đắk Lắk' => 'remote',
        'Gia Lai' => 'remote',
        'Lâm Đồng' => 'remote',
    ],

    /*
     * Ngưỡng miễn phí giao, tính trên tiền hàng.
     *
     * MỘT NGƯỠNG CHUNG cho mọi vùng, cố ý: ngưỡng khác nhau theo vùng
     * nghe thì công bằng hơn, nhưng khách không có cách nào biết mình
     * thuộc vùng nào trước khi nhập địa chỉ — mà lời hứa "mua thêm X nữa
     * là miễn phí giao" phải nói được ngay từ trang giỏ hàng.
     */
    'free_from' => (float) env('SHIPPING_FREE_FROM', 500000),

];
