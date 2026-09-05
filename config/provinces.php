<?php

/*
|--------------------------------------------------------------------------
| ĐƠN VỊ HÀNH CHÍNH CẤP TỈNH
|--------------------------------------------------------------------------
|
| 34 đơn vị: 6 thành phố trực thuộc trung ương + 28 tỉnh.
|
| Áp dụng theo Nghị quyết của Quốc hội ngày 12/6/2025 về sắp xếp đơn vị
| hành chính cấp tỉnh; chính quyền các đơn vị mới hoạt động từ 1/7/2025.
| Trước đó cả nước có 63 đơn vị.
|
| VÌ SAO ĐỂ Ở CONFIG, KHÔNG TẠO BẢNG:
| Đây là dữ liệu tham chiếu của nhà nước, không phải dữ liệu của cửa
| hàng: admin không có quyền và cũng không có lý do gì để sửa. Guide §27
| chống tạo bảng khi chưa có nghiệp vụ rõ ràng. Để ở config thì danh sách
| đi cùng mã nguồn, có lịch sử thay đổi, và không cần seeder.
|
| KHI NHÀ NƯỚC SÁP NHẬP TIẾP:
| Sửa danh sách này KHÔNG làm hỏng đơn hàng cũ. Cột orders.shipping_province
| là BẢN CHỤP tại thời điểm đặt — đơn giao về "Hà Tây" năm 2007 vẫn phải
| đọc được là "Hà Tây", không được viết lại theo tên hiện hành.
|
| Nguồn đã đối chiếu chéo hai nơi trước khi ghi vào đây; một nguồn thiếu
| "Thành phố Huế" nên không dùng riêng một nguồn.
|
*/

return [

    /*
     * Xếp thành phố lên trước trong ô chọn: phần lớn đơn hàng của một
     * cửa hàng hoa nằm ở các thành phố lớn, để lên đầu thì khách bớt
     * phải cuộn.
     */
    'cities' => [
        'Thành phố Hà Nội',
        'Thành phố Hồ Chí Minh',
        'Thành phố Hải Phòng',
        'Thành phố Đà Nẵng',
        'Thành phố Huế',
        'Thành phố Cần Thơ',
    ],

    /* 28 tỉnh, xếp theo bảng chữ cái tiếng Việt. */
    'provinces' => [
        'An Giang',
        'Bắc Ninh',
        'Cà Mau',
        'Cao Bằng',
        'Điện Biên',
        'Đắk Lắk',
        'Đồng Nai',
        'Đồng Tháp',
        'Gia Lai',
        'Hà Tĩnh',
        'Hưng Yên',
        'Khánh Hòa',
        'Lai Châu',
        'Lâm Đồng',
        'Lào Cai',
        'Lạng Sơn',
        'Nghệ An',
        'Ninh Bình',
        'Phú Thọ',
        'Quảng Ngãi',
        'Quảng Ninh',
        'Quảng Trị',
        'Sơn La',
        'Tây Ninh',
        'Thái Nguyên',
        'Thanh Hóa',
        'Tuyên Quang',
        'Vĩnh Long',
    ],

    /*
     * TÊN NGẮN — dùng khi nối API bên ngoài.
     *
     * Dự án lưu tên đầy đủ ("Thành phố Hà Nội") vì đó là tên hành chính
     * chính thức, và địa chỉ in trên đơn giao hàng phải đúng tên chính
     * thức. Nhưng các API vận chuyển (GHN, GHTK, Viettel Post) tra cứu
     * bằng tên ngắn ("Hà Nội") và sẽ trả về "không tìm thấy tỉnh" nếu
     * gửi tên đầy đủ.
     *
     * CHỈ liệt kê những tên KHÁC nhau giữa hai dạng, tức là 6 thành phố
     * trực thuộc trung ương. 28 tỉnh còn lại đã vốn là tên ngắn — chép
     * lại đủ 34 dòng chỉ tạo thêm 28 chỗ có thể gõ sai.
     *
     * CHƯA CÓ API NÀO ĐANG DÙNG BẢNG NÀY. Nó tồn tại vì phần "Rủi ro"
     * của báo cáo trước đã chỉ ra đúng va chạm này, và vì chỗ đúng để
     * ghi lại một quy ước là ngay cạnh dữ liệu mà nó nói về —
     * App\Services\Shop\Provinces::shortName() là đường dùng.
     */
    'short_names' => [
        'Thành phố Hà Nội' => 'Hà Nội',
        'Thành phố Hồ Chí Minh' => 'Hồ Chí Minh',
        'Thành phố Hải Phòng' => 'Hải Phòng',
        'Thành phố Đà Nẵng' => 'Đà Nẵng',
        'Thành phố Huế' => 'Huế',
        'Thành phố Cần Thơ' => 'Cần Thơ',
    ],

];
