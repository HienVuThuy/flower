<?php

namespace App\Enums;

/** Các khu vực của trang quản trị, dùng để phân quyền. */
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
    case HoTro = 'ho-tro';

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
            self::HoTro => 'Hỗ trợ khách hàng',
        };
    }

    public function moTa(): string
    {
        return match ($this) {
            self::DonHang => 'Xem và xử lý đơn, tạo vận đơn, trả lời yêu cầu số lượng lớn.',
            self::SanPham => 'Thêm, sửa, ẩn sản phẩm và danh mục — gồm cả GIÁ BÁN.',
            self::Kho => 'Nhập kho, kiểm kê, xem tồn, lô hoa và GIÁ NHẬP. Không thấy lãi gộp.',
            self::KhuyenMai => 'Đặt và dừng chương trình giảm giá.',
            self::DanhGia => 'Duyệt, ẩn, trả lời đánh giá và bài đăng.',
            self::BaoCao => 'Tổng quan, doanh thu, khách hàng — không gồm lãi gộp.',
            self::TaiChinh => 'Giá vốn, lãi gộp, và xác nhận tiền hoàn đã đi.',
            self::HeThong => 'Phân quyền, cấu hình cửa hàng, nhật ký thao tác.',
            self::HoTro => 'Trả lời tin nhắn khách gửi tới cửa hàng.',
        };
    }
}
