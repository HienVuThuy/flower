<?php

return [

    /*
    |--------------------------------------------------------------------------
    | THUẾ GIÁ TRỊ GIA TĂNG (VAT)
    |--------------------------------------------------------------------------
    |
    | GIÁ NIÊM YẾT ĐÃ BAO GỒM VAT — đây là quyết định quan trọng nhất của
    | cả tệp này, và nó đúng với cách bán lẻ ở Việt Nam.
    |
    | Hai mô hình có thể chọn:
    |
    |   1. GIÁ CHƯA THUẾ, cộng thuế ở bước cuối (kiểu Mỹ). Khách nhìn
    |      "260.000₫" trên thẻ sản phẩm rồi phải trả 280.800₫ — con số ở
    |      trang danh sách nói dối.
    |
    |   2. GIÁ ĐÃ GỒM THUẾ (Việt Nam, EU). Con số trên thẻ sản phẩm chính
    |      là con số khách trả. Phần thuế được TÁCH RA từ tổng, không
    |      cộng thêm vào.
    |
    | Dự án chọn (2). Hệ quả: bật thuế lên KHÔNG làm khách phải trả thêm
    | một đồng nào, và không con số nào trên giao diện đổi. Nó chỉ cho cửa
    | hàng biết trong mỗi đơn có bao nhiêu tiền là thuế.
    |
    | ============================================================
    | ⚠️ MỨC THUẾ SUẤT NÀY LÀ GIÁ TRỊ MẶC ĐỊNH KỸ THUẬT, KHÔNG PHẢI
    | LỜI TƯ VẤN THUẾ.
    |
    | Thuế suất VAT ở Việt Nam phụ thuộc vào mặt hàng và vào chính sách
    | từng thời kỳ; hoa tươi và cây cảnh có những trường hợp riêng (hàng
    | nông sản chưa qua chế biến do người sản xuất bán ra có thể không
    | chịu thuế). Cửa hàng PHẢI hỏi kế toán rồi chỉnh lại ở trang Cấu hình
    | — đó là lý do con số này sửa được từ giao diện quản trị chứ không
    | nằm chết trong mã nguồn.
    |
    | ============================================================
    | ĐÂY CHỈ CÒN LÀ MỨC MẶC ĐỊNH — không phải mức của cả cửa hàng.
    |
    | Từ khi có bảng `tax_classes`, mỗi sản phẩm gán được một nhóm thuế
    | riêng. Con số ở đây chỉ áp cho hai thứ:
    |
    |   - PHÍ VẬN CHUYỂN (một dịch vụ của cửa hàng, không mượn mức của
    |     bất kỳ sản phẩm nào trong giỏ);
    |   - sản phẩm CHƯA được phân loại (`tax_class_id` NULL).
    |
    | Xem App\Services\Tax\TaxCalculator::rateFor() và
    | App\Services\Tax\BasketTax.
    |
    */

    /*
     * Thuế suất mặc định, dạng thập phân (0.08 = 8%).
     *
     * Chỉ dùng khi CHƯA có ai đặt giá trị ở trang Cấu hình. Nguồn thật
     * là Setting `tax_rate` — xem App\Services\Tax\TaxCalculator.
     */
    'default_rate' => (float) env('TAX_DEFAULT_RATE', 0.08),

    /*
     * Bật/tắt việc tính và lưu thuế.
     *
     * TẮT KHÔNG có nghĩa là "bán không thuế" — nó có nghĩa là hệ thống
     * chưa ghi nhận phần thuế trong đơn. Cửa hàng chưa hỏi kế toán xong
     * thì để tắt còn hơn ghi một con số sai vào sổ.
     */
    'enabled' => (bool) env('TAX_ENABLED', true),

    /*
     * Số chữ số thập phân khi làm tròn tiền thuế.
     *
     * 2 chữ số, giống mọi cột tiền khác trong cơ sở dữ liệu. Làm tròn về
     * đồng chẵn ở đây thì tổng thuế của một trăm đơn sẽ lệch với tổng
     * tính lại từ doanh thu — và đó đúng là con số kế toán đối chiếu.
     */
    'scale' => 2,

];
