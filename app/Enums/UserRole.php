<?php

namespace App\Enums;

/**
 * Vai trò người dùng, và khu vực quản trị mà mỗi vai trò vào được.
 * ============================================================
 * BA VAI TRÒ, và bảng quyền nằm NGAY ĐÂY.
 *
 * Trước đây chỉ có admin/customer: ai vào được trang quản trị thì vào
 * được TẤT CẢ — kể cả giá vốn, lãi gộp, phân quyền và cấu hình cửa hàng.
 * Một cửa hàng thật có người chỉ xử lý đơn và nhập kho; đưa cho họ tài
 * khoản admin nghĩa là đưa luôn quyền đổi giá và xem lãi.
 *
 * ============================================================
 * VÌ SAO BẢNG QUYỀN NẰM TRONG ENUM, không nằm trong bảng dữ liệu.
 *
 * Quyền theo từng người thì mềm dẻo hơn, nhưng cũng có nghĩa là không ai
 * trả lời được câu "nhân viên nói chung thấy được gì" mà không mở cơ sở
 * dữ liệu ra dò. Ở quy mô này, một bảng đọc hết trong mười giây và nằm
 * trong lịch sử mã nguồn đáng giá hơn.
 *
 * Khi nào thật sự cần quyền riêng cho từng người thì thêm một lớp phủ
 * lên trên bảng này — không phải thay nó.
 */
enum UserRole: string
{
    case Admin = 'admin';
    case Staff = 'staff';
    case Customer = 'customer';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Chủ cửa hàng',
            self::Staff => 'Nhân viên',
            self::Customer => 'Khách hàng',
        };
    }

    public function moTa(): string
    {
        return match ($this) {
            self::Admin => 'Toàn quyền, gồm giá vốn, lãi gộp, phân quyền và cấu hình.',
            self::Staff => 'Xử lý đơn, kho, đánh giá và xem báo cáo bán hàng.',
            self::Customer => 'Không vào được trang quản trị.',
        };
    }

    /**
     * Khu vực quản trị mà vai trò này vào được.
     *
     * NHÂN VIÊN KHÔNG CÓ: sản phẩm (vì sửa được GIÁ BÁN), khuyến mại,
     * tài chính (giá vốn và lãi gộp), hệ thống (phân quyền, cấu hình).
     * Ba nhóm đầu là tiền của cửa hàng; nhóm cuối là chìa khoá của chính
     * hệ thống — ai sửa được phân quyền thì tự cho mình mọi quyền còn
     * lại.
     *
     * @return list<Quyen>
     */
    public function quyen(): array
    {
        return match ($this) {
            self::Admin => Quyen::cases(),

            self::Staff => [
                Quyen::DonHang,
                Quyen::Kho,
                Quyen::DanhGia,
                Quyen::BaoCao,
            ],

            self::Customer => [],
        };
    }

    /** Vai trò này có vào được trang quản trị không. */
    public function laNhanSu(): bool
    {
        return $this->quyen() !== [];
    }

    /** Các vai trò gán được cho tài khoản quản trị. */
    public static function nhanSu(): array
    {
        return array_values(array_filter(self::cases(), fn (self $v) => $v->laNhanSu()));
    }
}
