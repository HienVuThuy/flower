<?php

return [

    /*
    |--------------------------------------------------------------------------
    | "HÀNG MỚI VỀ" — MỚI TRONG BAO NHIÊU NGÀY
    |--------------------------------------------------------------------------
    |
    | LỖI ĐANG SỬA: khối "Hàng mới về" ở trang chủ chỉ là `latest()->take(8)`
    | — tức là "8 sản phẩm được thêm gần đây nhất", KHÔNG phải "sản phẩm
    | mới". Hai thứ đó trùng nhau khi cửa hàng nhập hàng đều tay, và tách
    | hẳn ra khi không: nghỉ nhập hàng ba tháng thì trang chủ vẫn trưng
    | tám món của quý trước dưới chữ "Hàng mới về".
    |
    | Đó là nói sai với khách. Khách quay lại xem có gì mới, thấy đúng
    | tám món tuần trước đã xem, và lần sau họ không bấm vào khối đó nữa.
    |
    | 60 NGÀY, và con số này là một lựa chọn về nghiệp vụ chứ không phải
    | mặc định kỹ thuật:
    |
    |   - Ngắn hơn (2 tuần): hoa tươi thì hợp, nhưng cây cảnh và bonsai
    |     bán chậm hơn nhiều — một cây mới nhập vẫn còn "mới" với khách
    |     sau một tháng.
    |   - Dài hơn (6 tháng): quá nửa danh mục sẽ luôn là "mới", và chữ
    |     "mới" mất hết nghĩa.
    |
    | Hai tháng đủ để một đợt nhập hàng được nhìn thấy trọn vẹn, mà vẫn
    | đủ ngắn để khối đó nói thật.
    |
    | KHÔNG CÓ HÀNG NÀO TRONG KHOẢNG NÀY THÌ ẨN CẢ KHỐI. Hiện một khối
    | trống kèm chữ "Chưa có sản phẩm" là chiếm chỗ để nói rằng không có
    | gì — thà nhường chỗ cho khối khác.
    |
    */

    'new_arrival_days' => (int) env('CATALOG_NEW_ARRIVAL_DAYS', 60),

    /*
    |--------------------------------------------------------------------------
    | Số sản phẩm tối đa trong khối "Hàng mới về"
    |--------------------------------------------------------------------------
    |
    | 8 = hai hàng bốn cột ở màn hình rộng. Lẻ ra một hàng thiếu ô trông
    | như trang bị lỗi.
    |
    */

    'new_arrival_limit' => 8,

];
