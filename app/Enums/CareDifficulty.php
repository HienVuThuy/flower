<?php

namespace App\Enums;

/**
 * Độ khó chăm sóc của một cây.
 * ============================================================
 * Ba giá trị này trước nay là chuỗi gõ tay ở BỐN nơi: quy tắc kiểm tra
 * của form admin, ô <select> trong form, bộ lọc ở trang danh sách, và
 * đường dẫn "Người mới bắt đầu trồng cây" trên trang chủ. Bốn bản chép
 * tay của cùng một danh sách — đổi một giá trị là ba chỗ còn lại âm thầm
 * lệch, và lệch ở đây nghĩa là bộ lọc trả về rỗng mà không báo lỗi gì.
 *
 * Giá trị lưu trong care_info['difficulty'], tức trong JSON, nên cơ sở
 * dữ liệu không ràng buộc được. Enum này là ràng buộc duy nhất.
 */
enum CareDifficulty: string
{
    case Easy = 'easy';
    case Medium = 'medium';
    case Hard = 'hard';

    public function label(): string
    {
        return match ($this) {
            self::Easy => 'Dễ',
            self::Medium => 'Trung bình',
            self::Hard => 'Khó',
        };
    }

    /** Câu giải thích cho khách, nói theo công sức thật chứ không theo thang điểm. */
    public function hint(): string
    {
        return match ($this) {
            self::Easy => 'Chịu được quên tưới vài ngày',
            self::Medium => 'Cần tưới và để đúng chỗ đều đặn',
            self::Hard => 'Cần theo dõi thường xuyên',
        };
    }

    /**
     * Nhãn nói theo GÓC NHÌN CỦA KHÁCH, không theo góc nhìn của cây.
     *
     * "Dễ / Trung bình / Khó" là mô tả cái cây — đúng cho bảng thông số
     * sản phẩm. Nhưng ở trang tư vấn, câu hỏi là "bạn có kinh nghiệm
     * chưa", nên nhãn phải nói về NGƯỜI. Cùng một dữ liệu, hai cách gọi,
     * và mỗi cách đúng ở đúng chỗ của nó.
     */
    public function experienceLabel(): string
    {
        return match ($this) {
            self::Easy => 'Tôi mới trồng cây',
            self::Medium => 'Đã trồng được vài cây',
            self::Hard => 'Muốn thử cây khó hơn',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        $out = [];

        foreach (self::cases() as $case) {
            $out[$case->value] = $case->label();
        }

        return $out;
    }
}
