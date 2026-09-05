<?php

namespace App\Enums;

/**
 * NHU CẦU của khách — trục thứ năm để duyệt hàng.
 * ============================================================
 * Bốn trục đã có đều sắp theo cách CỬA HÀNG nghĩ về hàng hoá (danh mục,
 * hình thức bán, loại, vai trò). Trục này sắp theo cách KHÁCH nghĩ về
 * việc của họ: "tôi cần quà tặng người yêu", "tôi mới tập trồng cây".
 *
 * KHÁC PlantAdvisor Ở CHỖ NÀO: trang tư vấn hỏi ĐIỀU KIỆN cụ thể (ban
 * công nắng, phòng tắm ẩm) và trả về một danh sách đã lọc. Trục này trả
 * lời sớm hơn một bước — khách còn chưa biết mình cần cây hay hoa, chỉ
 * biết mình đang có một dịp phải lo. Vì thế mỗi nhu cầu là một TRANG
 * HƯỚNG DẪN, không phải một bộ lọc.
 *
 * KHÔNG LƯU VÀO CƠ SỞ DỮ LIỆU: mỗi nhu cầu có nội dung hướng dẫn viết
 * riêng, cách chọn hàng riêng, và một trang Blade riêng. Đó là mã nguồn
 * chứ không phải dữ liệu — nhét vào bảng thì admin sửa được cái tên
 * nhưng không sửa được cái quan trọng, còn lập trình viên thì phải mở
 * hai chỗ mới hiểu một tính năng.
 */
enum ShoppingIntent: string
{
    case Gift = 'tang-nguoi-thuong';
    case Decor = 'trang-tri-khong-gian';
    case Event = 'khai-truong-su-kien';
    case Beginner = 'nguoi-moi-trong-cay';

    public function label(): string
    {
        return match ($this) {
            self::Gift => 'Tặng người thương',
            self::Decor => 'Trang trí không gian sống',
            self::Event => 'Khai trương & sự kiện',
            self::Beginner => 'Người mới bắt đầu trồng cây',
        };
    }

    /** Câu dẫn ngắn trên thẻ ở trang chủ. */
    public function tagline(): string
    {
        return match ($this) {
            self::Gift => 'Chọn hoa theo dịp và theo người nhận',
            self::Decor => 'Cây hợp từng góc trong nhà bạn',
            self::Event => 'Lẵng hoa, số lượng lớn, giao đúng giờ',
            self::Beginner => 'Bắt đầu bằng cây khó chết nhất',
        };
    }

    /** Ảnh minh hoạ — tài nguyên giao diện, đi qua Vite. Xem ASSETS.md. */
    public function image(): string
    {
        return match ($this) {
            self::Gift => 'intent-tang-nguoi-thuong.jpg',
            self::Decor => 'intent-trang-tri.jpg',
            self::Event => 'intent-su-kien.jpg',
            self::Beginner => 'intent-nguoi-moi.jpg',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Gift => 'heart',
            self::Decor => 'flower2',
            self::Event => 'people',
            self::Beginner => 'droplet',
        };
    }

    /** Tiêu đề trang, dạng câu hỏi hoặc lời mời — không lặp lại nhãn. */
    public function heading(): string
    {
        return match ($this) {
            self::Gift => 'Tặng hoa cho người mình thương',
            self::Decor => 'Chọn cây cho không gian sống',
            self::Event => 'Hoa khai trương & sự kiện',
            self::Beginner => 'Bắt đầu trồng cây từ đâu?',
        };
    }

    /** @return list<self> */
    public static function all(): array
    {
        return self::cases();
    }
}
