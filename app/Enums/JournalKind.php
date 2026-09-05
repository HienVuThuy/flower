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

    /* ================= MỖI LOẠI SỔ MỘT KẾT CẤU ================= */

    /**
     * Những khối hiện trên trang sổ, THEO ĐÚNG THỨ TỰ hiện ra.
     * ============================================================
     * ĐÂY LÀ CHỖ DUY NHẤT QUYẾT ĐỊNH MỘT LOẠI SỔ TRÔNG NHƯ THẾ NÀO.
     *
     * Trước đây năm loại sổ dùng chung đúng một bố cục: biểu đồ, dòng
     * thời gian, biểu mẫu ghi thêm. Kết quả là sổ theo dõi giá cũng hiện
     * ô "tình trạng cây", và sổ mục tiêu thì không có chỗ nào để liệt kê
     * các bước cần làm — thứ duy nhất khiến nó là sổ mục tiêu.
     *
     * Đưa danh sách khối vào enum thay vì rải `@if($journal->kind ===
     * ...)` khắp Blade: thêm một loại sổ mới thì sửa ở đây, và không có
     * cách nào quên một chỗ.
     *
     * @return list<string>
     */
    public function panels(): array
    {
        return match ($this) {
            self::Growth => ['goal', 'chart', 'photo-strip', 'care-summary', 'timeline'],
            self::Goal => ['goal', 'milestones', 'chart', 'timeline'],
            self::Price => ['price-stats', 'chart', 'price-table'],
            self::Analysis => ['rating', 'findings', 'chart', 'timeline'],
            self::Free => ['chart', 'timeline'],
        };
    }

    /**
     * Các ô của biểu mẫu ghi thêm một trang.
     *
     * @return list<string>
     */
    public function entryFields(): array
    {
        return match ($this) {
            self::Growth => ['date', 'title', 'body', 'condition', 'care', 'photo', 'sticker', 'metrics'],
            self::Goal => ['date', 'title', 'body', 'sticker', 'metrics'],
            self::Price => ['date', 'price', 'place', 'body', 'sticker'],
            self::Analysis => ['date', 'title', 'rating', 'good', 'bad', 'body', 'condition', 'photo', 'sticker', 'metrics'],
            self::Free => ['date', 'title', 'body', 'photo', 'sticker', 'metrics'],
        };
    }

    public function hasField(string $field): bool
    {
        return in_array($field, $this->entryFields(), true);
    }

    public function hasPanel(string $panel): bool
    {
        return in_array($panel, $this->panels(), true);
    }

    /**
     * Các khoá được phép ghi vào `journal_entries.data`, kèm luật kiểm.
     * ============================================================
     * BỘ KHOÁ ĐÓNG — đây là điều kiện để cột JSON không thành thùng rác.
     *
     * Cùng ràng buộc đã đặt cho `product_traits` (xem `TraitType`): cơ sở
     * dữ liệu không chặn được nội dung một cột JSON, nên chặn phải nằm ở
     * đây, và mọi đường ghi đều đi qua đúng một chỗ trong controller.
     *
     * @return array<string, string|list<string>> khoá => luật validate
     */
    public function dataFields(): array
    {
        return match ($this) {
            self::Growth => [
                // Việc chăm sóc đã làm hôm đó. Là mảng khoá nhãn dán vì
                // chúng là cùng một bộ từ vựng — "đã tưới" trên nhãn dán
                // và "đã tưới" trong ô tích phải là một thứ, nếu không
                // người dùng phải khai hai lần cho một việc.
                'care' => ['nullable', 'array', 'max:8'],
            ],
            self::Price => [
                'price' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
                'place' => ['nullable', 'string', 'max:120'],
            ],
            self::Analysis => [
                'rating' => ['nullable', 'integer', 'min:1', 'max:5'],
                'good' => ['nullable', 'string', 'max:500'],
                'bad' => ['nullable', 'string', 'max:500'],
            ],
            self::Goal, self::Free => [],
        };
    }

    /** Loại sổ này có danh sách các mốc cần làm không. */
    public function usesMilestones(): bool
    {
        return $this === self::Goal;
    }

    /**
     * Một trang nhật ký của loại sổ này gọi là gì.
     *
     * "Thêm trang" đúng với sổ sinh trưởng nhưng sai với sổ giá — ở đó
     * mỗi dòng là một lần đi khảo giá. Dùng chung một từ cho cả năm loại
     * là tiết kiệm chữ bằng cách làm giao diện nói không đúng việc.
     *
     * @return array{one: string, add: string, empty: string}
     */
    public function entryWords(): array
    {
        return match ($this) {
            self::Growth => [
                'one' => 'lần ghi',
                'add' => 'Ghi thêm một lần',
                'empty' => 'Chưa ghi lần nào',
            ],
            self::Goal => [
                'one' => 'lần cập nhật',
                'add' => 'Cập nhật tiến độ',
                'empty' => 'Chưa cập nhật lần nào',
            ],
            self::Price => [
                'one' => 'lần khảo giá',
                'add' => 'Ghi một lần khảo giá',
                'empty' => 'Chưa khảo giá lần nào',
            ],
            self::Analysis => [
                'one' => 'lần quan sát',
                'add' => 'Ghi một lần quan sát',
                'empty' => 'Chưa quan sát lần nào',
            ],
            self::Free => [
                'one' => 'ghi chép',
                'add' => 'Viết thêm',
                'empty' => 'Chưa viết gì',
            ],
        };
    }

    /** Bộ giao diện gợi ý sẵn khi tạo sổ loại này. */
    public function defaultTheme(): JournalTheme
    {
        return match ($this) {
            self::Growth => JournalTheme::Leaf,
            self::Goal => JournalTheme::Dusk,
            // Sổ giá để đọc số — giấy trơn, không hoa văn.
            self::Price => JournalTheme::Paper,
            self::Analysis => JournalTheme::Sand,
            self::Free => JournalTheme::Moss,
        };
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
