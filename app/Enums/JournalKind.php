<?php

namespace App\Enums;

/**
 * Loại sổ nhật ký cá nhân.
 * ============================================================
 * LOẠI SỔ KHÔNG KHOÁ NGƯỜI DÙNG VÀO MỘT KHUÔN — nó chỉ quyết định cửa
 * hàng GỢI Ý sẵn những gì.
 *
 * Mọi loại sổ đều dùng chung một bộ máy: trang nhật ký có ngày, tiêu đề,
 * nội dung, ảnh, tình trạng và các CHỈ SỐ do chính người dùng đặt tên.
 * Khác nhau ở chỗ mở sổ ra thì được mời sẵn chỉ số nào, và biểu đồ vẽ
 * theo chỉ số nào.
 *
 * Vì sao làm vậy: một người trồng lan có thể muốn ghi "số nụ", người
 * trồng sen đá muốn ghi "số lá con", người sưu tầm bonsai muốn ghi
 * "đường kính thân". Liệt kê sẵn mọi chỉ số cho mọi loài là việc không
 * bao giờ xong. Gợi ý vài cái hay dùng rồi cho tự thêm thì ai cũng ghi
 * được đúng thứ mình quan tâm.
 */
enum JournalKind: string
{
    case Growth = 'growth';
    case Goal = 'goal';
    case Price = 'price';
    case Analysis = 'analysis';
    case Free = 'free';

    public function label(): string
    {
        return match ($this) {
            self::Growth => 'Nhật ký sinh trưởng',
            self::Goal => 'Mục tiêu',
            self::Price => 'Theo dõi giá',
            self::Analysis => 'Phân tích cây',
            self::Free => 'Ghi chép tự do',
        };
    }

    /** Sổ này để làm gì — hiện lúc chọn loại, để không phải đoán. */
    public function hint(): string
    {
        return match ($this) {
            self::Growth => 'Ghi lại cây lớn thế nào theo thời gian: chiều cao, số lá, ảnh từng đợt.',
            self::Goal => 'Đặt một đích cụ thể kèm hạn, rồi ghi tiến độ dần tới đó.',
            self::Price => 'Ghi giá thấy được ở từng thời điểm, để biết lúc nào nên mua.',
            self::Analysis => 'Quan sát và suy đoán: cây bị gì, thử cách nào, kết quả ra sao.',
            self::Free => 'Không khuôn mẫu. Ghi gì cũng được, thêm chỉ số nào cũng được.',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Growth => 'flower2',
            self::Goal => 'check-circle',
            self::Price => 'tags',
            self::Analysis => 'search',
            self::Free => 'list',
        };
    }

    /**
     * Chỉ số GỢI Ý sẵn khi tạo trang nhật ký mới.
     *
     * Đây là LỜI MỜI, không phải danh sách đóng: màn hình luôn có nút
     * thêm chỉ số tự đặt tên. Gợi ý tồn tại để người mới không phải nhìn
     * một ô trống và tự nghĩ ra mình nên đo cái gì.
     *
     * @return array<string, string> tên chỉ số => đơn vị
     */
    public function suggestedMetrics(): array
    {
        return match ($this) {
            self::Growth => [
                'Chiều cao' => 'cm',
                'Số lá' => 'lá',
                'Đường kính tán' => 'cm',
            ],
            self::Goal => [
                'Tiến độ' => '%',
            ],
            self::Price => [
                'Giá' => \App\Services\Shop\Money::symbol(),
            ],
            self::Analysis => [
                'Độ ẩm đất' => '%',
                'Số giờ nắng' => 'giờ',
            ],
            self::Free => [],
        };
    }

    /**
     * Chỉ số vẽ biểu đồ mặc định.
     *
     * `null` = loại sổ này không có chỉ số nào đáng vẽ sẵn. Người dùng
     * vẫn tự chọn được ở trang sổ.
     */
    public function defaultChartMetric(): ?string
    {
        return array_key_first($this->suggestedMetrics());
    }

    /** @return array<string, string> value => label, cho ô chọn */
    public static function options(): array
    {
        $out = [];

        foreach (self::cases() as $case) {
            $out[$case->value] = $case->label();
        }

        return $out;
    }
}
