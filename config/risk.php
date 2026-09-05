<?php

/*
|--------------------------------------------------------------------------
| CHẤM ĐIỂM RỦI RO ĐƠN HÀNG
|--------------------------------------------------------------------------
|
| Mỗi dấu hiệu cộng một số điểm; tổng bị kẹp ở 100.
|
| ⚠️ SỐ ĐIỂM VÀ NGƯỠNG DƯỚI ĐÂY LÀ ĐIỂM KHỞI ĐẦU, KHÔNG PHẢI KẾT QUẢ
| PHÂN TÍCH DỮ LIỆU. Cửa hàng thật phải chỉnh lại sau vài tháng bán: chỉ
| khi có đủ đơn bùng thật mới biết dấu hiệu nào thật sự dự đoán được.
| Đặt sẵn ở đây để cơ chế chạy được và kiểm chứng được.
|
| VÌ SAO TRỌNG SỐ ĐƯỢC ĐẶT NHƯ VẬY:
|
|   Lịch sử huỷ đơn nặng nhất. Đây là dấu hiệu duy nhất dựa trên HÀNH VI
|   ĐÃ XẢY RA THẬT của chính người này, không phải suy đoán từ đặc điểm
|   chung. Một người từng bùng hai đơn là bằng chứng; một người đặt đơn
|   to lần đầu thì không.
|
|   Khách vãng lai chỉ cộng ít điểm. Đặt hàng không cần đăng nhập là tính
|   năng cửa hàng cố ý mở; phạt nặng nhóm này là tự mâu thuẫn với chính
|   quyết định đó. Nó chỉ đáng chú ý khi ĐI KÈM dấu hiệu khác.
|
*/

return [

    /*
     * Điểm từ mỗi dấu hiệu.
     */
    'weights' => [
        // Từng huỷ đơn trước đây — nhân với SỐ LẦN huỷ, kẹp ở mức tối đa
        // bên dưới. Ba lần huỷ đáng ngại hơn hẳn một lần.
        'cancelled_before' => 20,

        // Đơn COD giá trị lớn: hàng đã làm xong, đã đi đường, khách không
        // nhận thì mất trắng. Với hoa tươi không có chuyện trả về kho.
        'high_value_cod' => 25,

        // Không đăng nhập — không có lịch sử nào để đối chiếu.
        'guest' => 10,

        // Không để lại email: mất một đường liên hệ khi giao thất bại.
        'no_email' => 10,

        // Nhiều đơn từ cùng số điện thoại trong thời gian ngắn.
        'burst_orders' => 20,
    ],

    /*
     * Điểm tối đa mà riêng lịch sử huỷ đơn có thể cộng.
     *
     * Không kẹp thì một khách huỷ mười đơn (có thể vì lý do chính đáng —
     * đổi ý, cửa hàng hết hàng) sẽ đạt điểm tối đa và mọi đơn sau đều bị
     * gắn cờ đỏ vĩnh viễn.
     */
    'cancelled_cap' => 40,

    'thresholds' => [
        /*
         * Ngưỡng "giá trị lớn" cho đơn COD.
         *
         * Đặt theo giá trị đơn thực tế của cửa hàng hoa: phần lớn đơn ở
         * mức vài trăm nghìn, nên 1.500.000đ là đã cao hơn hẳn mức bình
         * thường chứ không phải một con số tròn chọn bừa.
         */
        'high_value' => (float) env('RISK_HIGH_VALUE', 1500000),

        // Bao nhiêu đơn trong bao nhiêu giờ thì coi là bất thường.
        'burst_count' => 3,
        'burst_hours' => 24,
    ],

    /*
     * Từ điểm này trở lên thì trang quản trị gắn cờ "cần xem lại".
     *
     * KHÔNG có ngưỡng "tự động huỷ". Hệ thống không bao giờ tự từ chối
     * đơn — xem migration 2026_09_06_010000.
     */
    'review_from' => 40,

];
