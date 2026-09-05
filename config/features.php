<?php

return [

    /*
     * Giỏ hàng & đặt hàng.
     *
     * Đang TẮT vì module Cart/Order chưa được triển khai.
     *
     * Giao diện vẫn hiển thị đầy đủ nút "Thêm vào giỏ" / "Mua ngay"
     * theo đúng mô hình thương mại điện tử, nhưng ở trạng thái vô
     * hiệu hoá kèm ghi chú — cố ý KHÔNG thay bằng "Xem chi tiết",
     * vì như vậy website sẽ giống catalog hơn là cửa hàng.
     *
     * Khi làm Phase Cart/Order: bật cờ này lên true và nối route vào
     * component x-product.actions — không phải thiết kế lại frontend.
     */
    'cart' => env('FEATURE_CART', false),

    /*
     * PHÍ GIAO HÀNG ĐÃ CHUYỂN SANG config/shipping.php.
     *
     * Trước đây ở đây có một mức phẳng duy nhất. Nay phí tính theo vùng,
     * và bảng vùng cần chỗ rộng hơn một mảng hai dòng.
     *
     * KHÔNG để lại bản sao ở đây, kể cả để "tương thích ngược": hai chỗ
     * cùng khai một con số tiền là chuyện chỉ đúng cho tới lần sửa đầu
     * tiên. Đã rà toàn bộ mã nguồn, không còn nơi nào đọc
     * config('features.shipping').
     */

];
