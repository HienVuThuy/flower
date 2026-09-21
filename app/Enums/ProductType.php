<?php

namespace App\Enums;

/** Sản phẩm này VỀ BẢN CHẤT là cái gì. */
enum ProductType: string
{
    case Flower = 'flower';
    case Plant = 'plant';
    case Artificial = 'artificial';
    case Other = 'other';

    public function allowedSellingForms(): array
    {
        return match ($this) {
            self::Flower, self::Artificial => [
                SellingForm::Bouquet,
                SellingForm::Basket,
                SellingForm::Box,
                SellingForm::Arrangement,
                SellingForm::Branch,
                SellingForm::Gift,
                SellingForm::Other,
            ],

            self::Plant => [
                SellingForm::Pot,
                SellingForm::Original,
                SellingForm::Set,
                SellingForm::Gift,
                SellingForm::Other,
            ],

            self::Other => [
                SellingForm::Set,
                SellingForm::Gift,
                SellingForm::Other,
            ],
        };
    }

    public function fitsCategoryKind(?CategoryKind $kind): bool
    {
        return match ($kind ?? CategoryKind::Plant) {
            CategoryKind::Supply => $this === self::Other,
            CategoryKind::Plant => $this !== self::Other,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Flower => 'Hoa',
            self::Plant => 'Cây cảnh',
            self::Artificial => 'Hoa giả (sáp, lụa, giấy)',
            self::Other => 'Khác',
        };
    }

    public static function options(): array
    {
        $out = [];

        foreach (self::cases() as $case) {
            $out[$case->value] = $case->label();
        }

        return $out;
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
