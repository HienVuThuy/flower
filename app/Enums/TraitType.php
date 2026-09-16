<?php

namespace App\Enums;

/** Các loại nhãn phân loại nhiều-giá-trị của sản phẩm. */
enum TraitType: string
{
    case Placement = 'placement';

    case FengShui = 'feng_shui';

    case AccessoryFor = 'accessory_for';

    case Habitat = 'habitat';

    case GrowthForm = 'growth_form';

    case Shape = 'shape';

    case Color = 'color';

    public function label(): string
    {
        return match ($this) {
            self::Placement => 'Vị trí đặt',
            self::FengShui => 'Hợp mệnh',
            self::AccessoryFor => 'Dùng kèm',
            self::Habitat => 'Môi trường sống',
            self::GrowthForm => 'Dạng sống',
            self::Shape => 'Dáng',
            self::Color => 'Màu sắc',
        };
    }

    public static function filterable(): array
    {
        return [
            self::Color,
            self::GrowthForm,
            self::Habitat,
            self::Shape,
            self::Placement,
            self::FengShui,
        ];
    }

    public function queryKey(): string
    {
        return match ($this) {
            self::Placement => 'vi-tri',
            self::FengShui => 'menh',
            self::AccessoryFor => 'dung-kem',
            self::Habitat => 'moi-truong',
            self::GrowthForm => 'dang-song',
            self::Shape => 'dang',
            self::Color => 'mau',
        };
    }

    public static function fromQueryKey(string $key): ?self
    {
        foreach (self::cases() as $case) {
            if ($case->queryKey() === $key) {
                return $case;
            }
        }

        return null;
    }

    public function options(): array
    {
        return match ($this) {
            self::Placement => Placement::options(),
            self::FengShui => FengShuiElement::options(),
            self::AccessoryFor => ['all' => 'Mọi loại hàng'] + SellingForm::options(),
            self::Habitat => Habitat::options(),
            self::GrowthForm => GrowthForm::options(),
            self::Shape => PlantShape::options(),
            self::Color => PlantColor::options(),
        };
    }

    public function labelFor(string $value): string
    {
        return $this->options()[$value] ?? $value;
    }
}
