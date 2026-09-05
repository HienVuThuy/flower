<?php

namespace App\Enums;

/**
 * VỊ TRÍ ĐẶT phù hợp của một cây.
 * ============================================================
 * VÌ SAO KHÔNG DÙNG care_info['position'] SẴN CÓ:
 * Ô đó là chữ tự do ("ban công hoặc gần cửa sổ, tránh nắng trưa"), viết
 * cho người đọc. Không lọc được, không đếm được, và mỗi sản phẩm một
 * cách diễn đạt. Muốn trả lời "cây nào hợp ban công" thì phải có một tập
 * giá trị đóng.
 *
 * HAI TRƯỜNG TỒN TẠI SONG SONG VÀ KHÔNG THAY THẾ NHAU:
 *   care_info['position'] - lời khuyên đầy đủ cho khách đọc
 *   trait `placement`     - nhãn để lọc và gợi ý
 * Đúng nguyên tắc "không dùng một trường kiêm nhiều ý nghĩa".
 *
 * TIÊU CHÍ PHÂN LOẠI LÀ ĐIỀU KIỆN SỐNG, không phải tên phòng cho đẹp.
 * "Phòng tắm" khác "bàn làm việc" ở chỗ ẩm cao và gần như không có nắng;
 * "ban công" là nắng trực tiếp. Cây sống hay chết là do mấy điều kiện đó
 * chứ không do cái phòng tên là gì.
 *
 * VÌ SAO TÁCH "BỆ CỬA SỔ" KHỎI "PHÒNG KHÁCH", và "HÀNH LANG" khỏi
 * "PHÒNG NGỦ": chúng khác nhau ở đúng cái quyết định cây sống hay chết.
 * Bệ cửa sổ có nắng gián tiếp cả ngày trong khi giữa phòng khách thì
 * không; hành lang thì tối VÀ ít người để ý tưới — hai điều kiện cùng
 * lúc, nên cây ở đó phải chịu được cả hai.
 *
 * "QUÁN, CỬA HÀNG" là chỗ khách doanh nghiệp hỏi nhiều: đèn bật cả ngày
 * (cây quang hợp được phần nào) nhưng nhiều người qua lại nên cây phải
 * cứng cáp, không rụng lá bừa.
 *
 * DANH SÁCH XẾP THEO ĐỘ SÁNG GIẢM DẦN, từ ngoài trời vào trong nhà, rồi
 * mới tới nhóm nơi làm việc. Người đọc lướt từ trên xuống là thấy ngay
 * chỗ của mình mà không phải đọc hết.
 */
enum Placement: string
{
    case Balcony = 'balcony';
    case WindowSill = 'window_sill';
    case LivingRoom = 'living_room';
    case Bedroom = 'bedroom';
    case Kitchen = 'kitchen';
    case Bathroom = 'bathroom';
    case Hallway = 'hallway';
    case Office = 'office';
    case Desk = 'desk';
    case Shop = 'shop';
    case Garden = 'garden';

    public function label(): string
    {
        return match ($this) {
            self::Balcony => 'Ban công',
            self::WindowSill => 'Bệ cửa sổ',
            self::LivingRoom => 'Phòng khách',
            self::Bedroom => 'Phòng ngủ',
            self::Kitchen => 'Phòng bếp',
            self::Bathroom => 'Phòng tắm',
            self::Hallway => 'Hành lang, cầu thang',
            self::Office => 'Văn phòng',
            self::Desk => 'Bàn làm việc',
            self::Shop => 'Quán, cửa hàng',
            self::Garden => 'Sân vườn',
        };
    }

    /** Điều kiện thực tế của chỗ này — hiện kèm nhãn để khách tự đối chiếu. */
    public function hint(): string
    {
        return match ($this) {
            self::Balcony => 'Nắng trực tiếp, gió nhiều',
            self::WindowSill => 'Nắng gián tiếp cả ngày, chỗ hẹp',
            self::LivingRoom => 'Sáng gián tiếp, rộng rãi',
            self::Bedroom => 'Ít nắng, cần cây không mùi mạnh',
            self::Kitchen => 'Ẩm, nhiều hơi dầu mỡ, nhiệt độ đổi liên tục',
            self::Bathroom => 'Ẩm cao, rất ít nắng',
            self::Hallway => 'Tối, ít người để ý tưới',
            self::Office => 'Đèn huỳnh quang, điều hoà khô',
            self::Desk => 'Chỗ hẹp, cần cây nhỏ',
            self::Shop => 'Đèn cả ngày, nhiều người qua lại',
            self::Garden => 'Ngoài trời, nắng mưa trực tiếp',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Balcony, self::Garden, self::WindowSill => 'brightness-high',
            self::Bathroom, self::Kitchen => 'droplet',
            self::Office, self::Desk => 'speedometer2',
            self::Shop => 'bag',
            self::Hallway => 'list',
            default => 'flower2',
        };
    }

    /** @return array<string, string> value => label, cho ô chọn và quy tắc kiểm tra */
    public static function options(): array
    {
        $out = [];

        foreach (self::cases() as $case) {
            $out[$case->value] = $case->label();
        }

        return $out;
    }
}
