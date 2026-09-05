<?php

namespace App\Enums;

/**
 * Bộ thông tin chăm sóc áp dụng cho một sản phẩm.
 * ============================================================
 * Guide mục 4.4 nói rõ: cây trồng trong chậu cần ánh sáng, nước, đất,
 * phân bón, nhiệt độ...; còn "đối với bó hoa/cành hoa thì thông tin
 * chăm sóc lại khác", và "phải thiết kế nội dung sản phẩm đủ linh hoạt,
 * không giả định tất cả sản phẩm đều có cùng một bộ thuộc tính".
 *
 * Trước đây form quản trị hiển thị đúng 9 ô của cây chậu cho MỌI sản
 * phẩm. Hậu quả có thật trong CSDL: bó hoa "Hoa hồng đỏ Ecuador" mang
 * đủ 9 khoá light/water/soil/... đều rỗng, còn cây chậu Monstera lại
 * không có thông tin chăm sóc nào.
 *
 * Chi tiết quyết định: docs/DOMAIN-DECISIONS.md (QĐ-02).
 *
 * Cố ý KHÔNG dựng hệ thống thuộc tính động (EAV): Guide mục 27 chống
 * over-engineering. Ba hồ sơ cố định là đủ cho nghiệp vụ hoa - cây cảnh.
 */
enum CareProfile: string
{
    /** Cây sống: chăm sóc lâu dài. */
    case LivingPlant = 'living_plant';

    /** Hoa cắt cành: giữ tươi trong vài ngày. */
    case CutFlower = 'cut_flower';

    /** Quà tặng, set, hình thức khác: chỉ ghi chú chung. */
    case Minimal = 'minimal';

    public function label(): string
    {
        return match ($this) {
            self::LivingPlant => 'Chăm sóc cây',
            self::CutFlower => 'Giữ hoa tươi',
            self::Minimal => 'Lưu ý',
        };
    }

    /**
     * Các trường thuộc hồ sơ này.
     *
     * @return array<string, array{label: string, icon: ?string, input: string, placeholder: string}>
     */
    public function fields(): array
    {
        return match ($this) {
            self::LivingPlant => [
                'light' => ['label' => 'Ánh sáng', 'icon' => 'brightness-high', 'input' => 'text', 'placeholder' => 'Ví dụ: ưa sáng gián tiếp'],
                'water' => ['label' => 'Nước tưới', 'icon' => 'droplet', 'input' => 'text', 'placeholder' => 'Ví dụ: tưới 2 lần/tuần'],
                'soil' => ['label' => 'Đất trồng', 'icon' => 'flower2', 'input' => 'text', 'placeholder' => 'Ví dụ: đất tơi xốp, thoát nước tốt'],
                'fertilizer' => ['label' => 'Phân bón', 'icon' => 'moisture', 'input' => 'text', 'placeholder' => 'Ví dụ: bón NPK mỗi tháng'],
                'temperature' => ['label' => 'Nhiệt độ', 'icon' => 'thermometer-half', 'input' => 'text', 'placeholder' => 'Ví dụ: 20°C - 30°C'],
                'position' => ['label' => 'Vị trí đặt', 'icon' => 'geo-alt', 'input' => 'text', 'placeholder' => 'Ví dụ: ban công, phòng khách'],
                'frequency' => ['label' => 'Tần suất chăm sóc', 'icon' => 'arrow-repeat', 'input' => 'text', 'placeholder' => 'Ví dụ: kiểm tra hàng tuần'],

                /*
                 * HAI Ô SỐ NGÀY — dùng cho NHẮC LỊCH TỰ ĐỘNG.
                 *
                 * Khác hẳn ô 'water' và 'fertilizer' ngay trên: hai ô kia
                 * là lời khuyên viết cho NGƯỜI ĐỌC ("tưới 2 lần/tuần",
                 * "bón NPK mỗi tháng"), mỗi sản phẩm một cách diễn đạt.
                 * Máy không đọc được chúng để tính ra ngày nhắc tiếp theo.
                 *
                 * Hai ô này là SỐ, và chỉ có một việc: cứ bao nhiêu ngày
                 * thì gửi thư nhắc. Bỏ trống thì sản phẩm đó không sinh
                 * lịch nhắc nào — đúng, vì không phải cây nào cũng có chu
                 * kỳ cố định.
                 *
                 * Đúng nguyên tắc "không dùng một trường kiêm nhiều ý
                 * nghĩa": lời khuyên và con số là hai việc khác nhau.
                 */
                'water_days' => ['label' => 'Nhắc tưới sau mỗi (ngày)', 'icon' => 'droplet', 'input' => 'number', 'placeholder' => 'Ví dụ: 4'],
                'fertilizer_days' => ['label' => 'Nhắc bón phân sau mỗi (ngày)', 'icon' => 'moisture', 'input' => 'number', 'placeholder' => 'Ví dụ: 30'],
                'difficulty' => ['label' => 'Độ khó chăm sóc', 'icon' => null, 'input' => 'difficulty', 'placeholder' => ''],
                'notes' => ['label' => 'Lưu ý khi chăm sóc', 'icon' => null, 'input' => 'textarea', 'placeholder' => 'Ví dụ: tránh nắng gắt buổi trưa'],
            ],

            self::CutFlower => [
                'water_change' => ['label' => 'Thay nước', 'icon' => 'droplet', 'input' => 'text', 'placeholder' => 'Ví dụ: thay nước mỗi ngày'],
                'trim' => ['label' => 'Cắt gốc', 'icon' => 'flower2', 'input' => 'text', 'placeholder' => 'Ví dụ: cắt vát gốc 2 ngày/lần'],
                'placement' => ['label' => 'Nơi đặt', 'icon' => 'geo-alt', 'input' => 'text', 'placeholder' => 'Ví dụ: tránh nắng và gió quạt'],
                'lifespan' => ['label' => 'Độ bền dự kiến', 'icon' => 'arrow-repeat', 'input' => 'text', 'placeholder' => 'Ví dụ: 5 - 7 ngày'],
                'notes' => ['label' => 'Lưu ý', 'icon' => null, 'input' => 'textarea', 'placeholder' => 'Ví dụ: không để gần trái cây chín'],
            ],

            self::Minimal => [
                'notes' => ['label' => 'Lưu ý', 'icon' => null, 'input' => 'textarea', 'placeholder' => 'Ví dụ: bảo quản nơi khô ráo'],
            ],
        };
    }

    /** @return array<int, string> */
    public function keys(): array
    {
        return array_keys($this->fields());
    }
}
