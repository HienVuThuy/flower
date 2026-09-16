<?php

namespace App\Enums;

/** Bộ thông tin chăm sóc áp dụng cho một sản phẩm. */
enum CareProfile: string
{
    case LivingPlant = 'living_plant';

    case CutFlower = 'cut_flower';

    case Minimal = 'minimal';

    public function label(): string
    {
        return match ($this) {
            self::LivingPlant => 'Chăm sóc cây',
            self::CutFlower => 'Giữ hoa tươi',
            self::Minimal => 'Lưu ý',
        };
    }

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

    public function keys(): array
    {
        return array_keys($this->fields());
    }
}
