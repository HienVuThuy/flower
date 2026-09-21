<?php

namespace Database\Seeders;

use App\Enums\TraitType;
use App\Models\Product;
use Illuminate\Database\Seeder;

/**
 * Gắn nhãn Dịp tặng và Mùa hoa cho hàng mẫu. Mùa chỉ gắn cho hoa có mùa rõ
 * (đào Tết, tulip, cẩm tú cầu, cúc hoạ mi); hoa có quanh năm để trống.
 */
class GiftOccasionSeeder extends Seeder
{
    private function bang(): array
    {
        return [
            'Hoa hồng đỏ Ecuador' => ['dip' => ['tinh-yeu', 'sinh-nhat']],
            'Bó tulip Hà Lan' => ['dip' => ['tinh-yeu', 'sinh-nhat', 'chuc-mung'], 'mua' => ['xuan']],
            'Hướng dương rực rỡ' => ['dip' => ['sinh-nhat', 'chuc-mung', 'sinh-vien']],
            'Giỏ hoa baby trắng' => ['dip' => ['sinh-nhat', 'chuc-mung']],
            'Cành đào phai chơi Tết' => ['mua' => ['xuan']],
            'Bó cẩm tú cầu xanh' => ['dip' => ['sinh-nhat', 'chuc-mung'], 'mua' => ['ha']],
            'Bó cúc hoạ mi trắng' => ['dip' => ['tinh-yeu', 'sinh-vien'], 'mua' => ['dong']],
            'Bó hoa mẫu đơn đỏ' => ['dip' => ['tinh-yeu', 'chuc-mung']],
            'Hộp hoa hồng pastel' => ['dip' => ['tinh-yeu', 'sinh-nhat']],
            'Hộp hoa tulip vàng' => ['dip' => ['sinh-nhat', 'chuc-mung'], 'mua' => ['xuan']],
            'Giỏ hoa hướng dương mini' => ['dip' => ['sinh-nhat', 'chuc-mung', 'sinh-vien']],
            'Set quà cây để bàn kèm thiệp' => ['dip' => ['sinh-nhat', 'sinh-vien']],
            'Lan hồ điệp tím chậu sứ' => ['dip' => ['chuc-mung']],
            'Lẵng hoa khai trương' => ['dip' => ['chuc-mung']],
            'Kệ hoa khai trương hai tầng' => ['dip' => ['chuc-mung']],
        ];
    }

    public function run(): void
    {
        $daGan = 0;

        foreach ($this->bang() as $ten => $nhan) {
            $product = Product::where('name', $ten)->first();

            if (! $product) {
                continue;
            }

            $product->syncTraits(TraitType::Occasion, $nhan['dip'] ?? []);
            $product->syncTraits(TraitType::Season, $nhan['mua'] ?? []);
            $product->save();

            $daGan++;
        }

        $this->command?->info("Đã gắn dịp tặng / mùa hoa cho {$daGan} sản phẩm.");
    }
}
