<?php

/**
 * Ngưỡng cho công cụ Đề xuất giá của trang quản trị.
 * ============================================================
 * ĐỂ Ở FILE CẤU HÌNH VÌ CHÚNG LÀ PHÁN ĐOÁN, KHÔNG PHẢI SỰ THẬT.
 *
 * Không có con số nào ở đây rút ra được từ lý thuyết. Chúng là những
 * ngưỡng hợp lý cho một cửa hàng cỡ này, và chúng SẼ SAI khi lượng truy
 * cập tăng lên. Chôn chúng vào giữa mã nguồn thì lần sửa sau phải đi tìm
 * trong năm hàm khác nhau và sẽ sót một chỗ.
 *
 * Mỗi con số dưới đây kèm lý do chọn. Sửa thì sửa cả lý do.
 */
return [

    /*
     * Cửa sổ quan sát mặc định, tính bằng ngày.
     *
     * 30 ngày: đủ dài để gom được lượng đơn có nghĩa ở quy mô này, đủ
     * ngắn để phản ánh mùa vụ — hoa là mặt hàng đổi nhu cầu theo tuần
     * chứ không theo quý.
     */
    'window_days' => 30,

    /*
     * SỐ LƯỢT XEM TỐI THIỂU trước khi được phép kết luận bất cứ điều gì
     * về một sản phẩm.
     *
     * ĐÂY LÀ NGƯỠNG QUAN TRỌNG NHẤT CỦA CẢ FILE.
     *
     * Một sản phẩm có 3 lượt xem và 0 đơn KHÔNG nói lên điều gì về giá —
     * nó chỉ nói rằng gần như chưa ai nhìn thấy nó. Đưa ra đề xuất "giảm
     * giá vì không ai mua" trong trường hợp đó là bịa ra một kết luận từ
     * chỗ trống, và admin sẽ giảm giá một món đang lành lặn.
     *
     * 20 lượt xem là mức tối thiểu để 0 đơn bắt đầu có nghĩa.
     */
    'min_views' => 20,

    /*
     * Số đơn tối thiểu trong toàn cửa hàng để công cụ tự tin.
     *
     * Dưới ngưỡng này, công cụ vẫn chạy nhưng PHẢI tự cảnh báo là mẫu
     * còn mỏng. Nó không được im lặng đưa ra đề xuất trông chắc chắn
     * trong khi cả cửa hàng mới có mươi đơn.
     */
    'confident_orders' => 30,

    /*
     * Tỉ lệ xem → đơn (%) dưới mức này thì coi là "quan tâm mà không mua".
     *
     * 1%: với một cửa hàng nhỏ bán mặt hàng có cân nhắc, dưới 1% là thấp
     * rõ rệt. Đây là ngưỡng dễ sai nhất khi lượng truy cập đổi — xem lại
     * mỗi khi số liệu tổng thay đổi đáng kể.
     */
    'low_conversion_percent' => 1.0,

    /*
     * Thêm giỏ nhưng không đặt: tỉ lệ thêm giỏ (%) từ mức này trở lên,
     * kèm tỉ lệ đơn thấp, thì nghẽn nằm ở bước thanh toán chứ không ở giá.
     *
     * 5%: khách đã bỏ vào giỏ là đã chấp nhận giá. Phân biệt được hai
     * tình huống này quan trọng, vì chúng dẫn tới hai hành động trái
     * ngược nhau.
     */
    'high_cart_percent' => 5.0,

    /*
     * Bao nhiêu ngày không bán được thì coi là tồn kho nằm lâu.
     *
     * 45 ngày: dài hơn cửa sổ quan sát 30 ngày, để một món vừa bán hôm
     * thứ 31 không bị gắn nhãn "nằm lâu".
     */
    'stale_days' => 45,

    /*
     * Tồn kho tối thiểu để "nằm lâu" đáng bận tâm.
     *
     * Còn 1–2 cái thì dù nằm lâu cũng không phải chuyện cần một chương
     * trình khuyến mại.
     */
    'stale_min_stock' => 5,

    /*
     * Số đơn tối thiểu để gọi một sản phẩm là bán tốt.
     *
     * 3 đơn trong cửa sổ. Thấp, và cố ý thấp — ở quy mô này 3 đơn cho
     * một sản phẩm đã là nhóm dẫn đầu. Nhưng vì nó thấp nên giao diện
     * phải hiện kèm con số thật để admin tự đánh giá.
     */
    'best_seller_orders' => 3,

    /*
     * Rẻ hơn trung vị danh mục bao nhiêu phần trăm thì đáng nhắc.
     *
     * 15%: dưới mức đó là dao động bình thường giữa các sản phẩm, không
     * phải một khoảng trống về giá.
     */
    'underpriced_percent' => 15.0,
];
