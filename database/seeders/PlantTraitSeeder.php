<?php

namespace Database\Seeders;

use App\Enums\TraitType;
use App\Models\Product;
use Illuminate\Database\Seeder;

/** Gắn nhãn môi trường sống / dạng sống / dáng / màu cho từng sản phẩm. */
class PlantTraitSeeder extends Seeder
{
    private function bang(): array
    {
        return [
            'Hoa hồng đỏ Ecuador' => [
                'habitat' => ['temperate', 'terrestrial'],
                'form' => ['shrub'], 'shape' => ['upright'], 'color' => ['red'],
            ],
            'Bó tulip Hà Lan' => [
                'habitat' => ['temperate', 'underground'],
                'form' => ['bulb'], 'shape' => ['upright'], 'color' => ['mixed'],
            ],
            'Hộp hoa tulip vàng' => [
                'habitat' => ['temperate', 'underground'],
                'form' => ['bulb'], 'shape' => ['upright'], 'color' => ['yellow'],
            ],
            'Hướng dương rực rỡ' => [
                'habitat' => ['terrestrial'],
                'form' => ['herb'], 'shape' => ['upright'], 'color' => ['yellow'],
            ],
            'Giỏ hoa hướng dương mini' => [
                'habitat' => ['terrestrial'],
                'form' => ['herb'], 'shape' => ['bushy'], 'color' => ['yellow'],
            ],
            'Giỏ hoa baby trắng' => [
                'habitat' => ['terrestrial'],
                'form' => ['herb'], 'shape' => ['bushy'], 'color' => ['white'],
            ],
            'Cành đào phai chơi Tết' => [
                'habitat' => ['temperate', 'terrestrial'],
                'form' => ['tree'], 'shape' => ['upright'], 'color' => ['pink'],
            ],
            'Bó cẩm tú cầu xanh' => [
                'habitat' => ['temperate', 'terrestrial'],
                'form' => ['shrub'], 'shape' => ['round'], 'color' => ['blue'],
            ],
            'Bó cúc hoạ mi trắng' => [
                'habitat' => ['terrestrial'],
                'form' => ['herb'], 'shape' => ['bushy'], 'color' => ['white'],
            ],
            'Bó hoa mẫu đơn đỏ' => [
                'habitat' => ['temperate', 'terrestrial'],
                'form' => ['herb'], 'shape' => ['round'], 'color' => ['red'],
            ],
            'Hộp hoa hồng pastel' => [
                'habitat' => ['temperate', 'terrestrial'],
                'form' => ['shrub'], 'shape' => ['round'], 'color' => ['pink'],
            ],

            'Monstera Deliciosa chậu gốm' => [
                'habitat' => ['rainforest', 'epiphyte'],
                'form' => ['vine'], 'shape' => ['columnar'], 'color' => ['green'],
            ],
            'Trầu bà leo cột' => [
                'habitat' => ['rainforest', 'epiphyte'],
                'form' => ['vine'], 'shape' => ['columnar'], 'color' => ['green'],
            ],
            'Kim tiền chậu sứ' => [
                'habitat' => ['rainforest', 'underground'],
                'form' => ['bulb'], 'shape' => ['upright'], 'color' => ['green'],
            ],
            'Cây lan ý chậu sứ trắng' => [
                'habitat' => ['rainforest'],
                'form' => ['herb'], 'shape' => ['bushy'], 'color' => ['white', 'green'],
            ],
            'Vạn niên thanh chậu sứ' => [
                'habitat' => ['rainforest'],
                'form' => ['herb'], 'shape' => ['upright'], 'color' => ['green'],
            ],
            'Kim ngân bện thân' => [
                'habitat' => ['semiaquatic', 'rainforest'],
                'form' => ['tree'], 'shape' => ['upright'], 'color' => ['green'],
            ],
            'Lan hồ điệp tím chậu sứ' => [
                'habitat' => ['epiphyte', 'rainforest'],
                'form' => ['herb'], 'shape' => ['upright'], 'color' => ['purple'],
            ],
            'Dương xỉ Boston treo' => [
                'habitat' => ['rainforest', 'epiphyte'],
                'form' => ['fern'], 'shape' => ['trailing'], 'color' => ['green'],
            ],
            'Lưỡi hổ mini để bàn' => [
                'habitat' => ['desert', 'terrestrial'],
                'form' => ['succulent'], 'shape' => ['upright'], 'color' => ['green'],
            ],
            'Lưỡi hổ vàng viền để bàn' => [
                'habitat' => ['desert', 'terrestrial'],
                'form' => ['succulent'], 'shape' => ['upright'], 'color' => ['green', 'yellow'],
            ],

            'Sen đá mix chậu đá' => [
                'habitat' => ['desert', 'lithophyte'],
                'form' => ['succulent'], 'shape' => ['rosette'], 'color' => ['mixed'],
            ],
            'Sen đá nâu chậu sứ mini' => [
                'habitat' => ['desert', 'lithophyte'],
                'form' => ['succulent'], 'shape' => ['rosette'], 'color' => ['brown'],
            ],
            'Sen đá kim cương chậu treo' => [
                'habitat' => ['desert', 'lithophyte'],
                'form' => ['succulent'], 'shape' => ['rosette'], 'color' => ['green'],
            ],
            'Xương rồng bi chậu đất nung' => [
                'habitat' => ['desert'],
                'form' => ['cactus'], 'shape' => ['round'], 'color' => ['green'],
            ],

            'Bonsai tùng la hán dáng trực' => [
                'habitat' => ['temperate', 'terrestrial'],
                'form' => ['tree'], 'shape' => ['upright'], 'color' => ['green'],
            ],
            'Bonsai mai chiếu thuỷ' => [
                'habitat' => ['rainforest', 'terrestrial'],
                'form' => ['tree'], 'shape' => ['bushy'], 'color' => ['white', 'green'],
            ],

            'Hoa cầm tay cô dâu' => [
                'habitat' => [], 'form' => [], 'shape' => ['round'], 'color' => ['white'],
            ],
            'Hoa cài áo chú rể' => [
                'habitat' => [], 'form' => [], 'shape' => ['round'], 'color' => ['white'],
            ],
            'Hoa để bàn tiệc cưới' => [
                'habitat' => [], 'form' => [], 'shape' => ['bushy'], 'color' => ['white', 'pink'],
            ],
            'Lẵng hoa khai trương' => [
                'habitat' => [], 'form' => [], 'shape' => ['upright'], 'color' => ['mixed'],
            ],
            'Kệ hoa khai trương hai tầng' => [
                'habitat' => [], 'form' => [], 'shape' => ['upright'], 'color' => ['mixed'],
            ],
            'Set quà cây để bàn kèm thiệp' => [
                'habitat' => [], 'form' => [], 'shape' => ['round'], 'color' => ['green'],
            ],
        ];
    }

    public function run(): void
    {
        $daGan = 0;
        $khongThay = [];

        foreach ($this->bang() as $ten => $nhan) {
            $product = Product::where('name', $ten)->first();

            if (! $product) {
                $khongThay[] = $ten;

                continue;
            }

            $product->syncTraits(TraitType::Habitat, $nhan['habitat']);
            $product->syncTraits(TraitType::GrowthForm, $nhan['form']);
            $product->syncTraits(TraitType::Shape, $nhan['shape']);
            $product->syncTraits(TraitType::Color, $nhan['color']);

            $daGan++;
        }

        $this->command?->info("Đã gắn nhãn sinh thái cho {$daGan} sản phẩm.");

        if ($khongThay) {
            $this->command?->warn(
                'Không tìm thấy sản phẩm (tên có thể đã đổi): ' . implode(', ', $khongThay)
            );
        }
    }
}
