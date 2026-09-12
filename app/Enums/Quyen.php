<?php

namespace App\Enums;

/**
 * Các khu vực của trang quản trị, dùng để phân quyền.
 * ============================================================
 * VÌ SAO CHIA THEO KHU VỰC, không theo từng nút.
 *
 * Quyền cho từng hành động ("được sửa giá nhưng không được sửa tên") nghe
 * thì mịn, nhưng trong thực tế không ai cấu hình nổi và cũng không ai
 * kiểm lại được là mình đã cấu hình đúng chưa. Một danh sách tám khu vực
 * thì đọc hết trong mười giây, và câu hỏi "nhân viên này thấy được gì"
 * trả lời được bằng mắt.
 *
 * ============================================================
 * ĐÂY LÀ DANH SÁCH DUY NHẤT.
 *
 * Cùng một nơi cho: middleware chặn đường dẫn, thanh điều hướng ẩn mục,
 * và trang phân quyền. Khai ở hai chỗ thì sớm muộn thanh điều hướng ẩn
 * một mục mà đường dẫn vẫn vào được — tức là **trông như đã khoá trong
 * khi chưa khoá**, thứ nguy hiểm hơn hẳn việc không khoá gì.
 */
enum Quyen: string
{
    case DonHang = 'don-hang';
    case SanPham = 'san-pham';
    case Kho = 'kho';
    case KhuyenMai = 'khuyen-mai';
    case DanhGia = 'danh-gia';
    case BaoCao = 'bao-cao';
    case TaiChinh = 'tai-chinh';
    case HeThong = 'he-thong';

    public function nhan(): string
    {
        return match ($this) {
            self::DonHang => 'Đơn hàng và giao hàng',
            self::SanPham => 'Sản phẩm, danh mục, bài viết',
            self::Kho => 'Kho: tồn, nhập, kiểm kê',
            self::KhuyenMai => 'Khuyến mại, mã giảm giá, đề xuất giá',
            self::DanhGia => 'Đánh giá và bài đăng của khách',
            self::BaoCao => 'Báo cáo bán hàng',
            self::TaiChinh => 'Giá vốn, lãi gộp, hoàn tiền',
            self::HeThong => 'Người dùng, cấu hình, nhật ký',
        };
    }

    public function moTa(): string
    {
        return match ($this) {
            self::DonHang => 'Xem và xử lý đơn, tạo vận đơn, trả lời yêu cầu số lượng lớn.',
            self::SanPham => 'Thêm, sửa, ẩn sản phẩm và danh mục — gồm cả GIÁ BÁN.',
            self::Kho => 'Nhập kho, kiểm kê, xem tồn. Không thấy giá vốn.',
            self::KhuyenMai => 'Đặt và dừng chương trình giảm giá.',
            self::DanhGia => 'Duyệt, ẩn, trả lời đánh giá và bài đăng.',
            self::BaoCao => 'Tổng quan, doanh thu, khách hàng — không gồm lãi gộp.',
            self::TaiChinh => 'Giá vốn, lãi gộp, và xác nhận tiền hoàn đã đi.',
            self::HeThong => 'Phân quyền, cấu hình cửa hàng, nhật ký thao tác.',
        };
    }
}
