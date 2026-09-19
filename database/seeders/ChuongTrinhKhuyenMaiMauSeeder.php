<?php

namespace Database\Seeders;

use App\Enums\PromotionStatus;
use App\Enums\PromotionType;
use App\Models\GiftItem;
use App\Models\Product;
use App\Models\Promotion;
use App\Services\Analytics\KhoangThoiGian;
use App\Services\Pricing\PricingService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Chương trình khuyến mại mẫu: giảm giá chung, vài sản phẩm giảm mức riêng, vài sản phẩm tặng quà riêng.
 * Chạy lại được: php artisan db:seed --class=ChuongTrinhKhuyenMaiMauSeeder
 */
class ChuongTrinhKhuyenMaiMauSeeder extends Seeder
{
    private const DONG = [
        ['Kim tiền chậu sứ', null, null, null],
        ['Trầu bà leo cột', null, null, null],
        ['Lan hồ điệp tím chậu sứ', 'percent', 15, null],
        ['Monstera Deliciosa chậu gốm', 'tang_qua', null, ['Phân bón NPK dạng viên tan chậm', 1]],
        ['Lưỡi hổ mini để bàn', 'tang_qua', null, ['Đĩa lót chậu chống tràn', 1]],
        ['Sen đá mix chậu đá', 'tang_qua', null, ['Sỏi màu trang trí 500g', 1]],
        ['Hoa hồng đỏ Ecuador', 'tang_qua', null, ['Thiệp chúc mừng kèm kẹp cắm', 2]],
    ];

    public function run(): void
    {
        $ten = collect(self::DONG)->flatMap(fn ($d) => [$d[0], $d[3][0] ?? null])->filter()->unique();
        $sp = Product::whereIn('name', $ten)->get()->keyBy('name');

        if ($thieu = $ten->diff($sp->keys())->values()->all()) {
            $this->command?->warn('Bỏ qua chương trình mẫu — thiếu sản phẩm: ' . implode(', ', $thieu));

            return;
        }

        DB::transaction(function () use ($sp) {
            $km = Promotion::updateOrCreate(['slug' => 'tuan-le-cay-xanh-mau'], [
                'name' => 'Tuần lễ cây xanh (chương trình mẫu)',
                'short_description' => 'Cây cảnh giảm 10%, lan hồ điệp giảm 15%, một số cây và hoa được tặng quà kèm.',
                'description' => 'Chương trình mẫu để xem cách phối hợp giảm giá và tặng quà trong cùng một chương trình. '
                    . 'Quà áp cho đơn từ 200.000đ, tối đa 100 đơn.',
                'type' => PromotionType::Percent,
                'discount_value' => 10,
                'gift_item_id' => null,
                'gift_quantity' => 1,
                'min_order_amount' => 200000,
                'min_member_tier_id' => null,
                'first_order_only' => false,
                'per_user_limit' => null,
                'total_limit' => 100,
                'starts_at' => now(KhoangThoiGian::muiGio())->startOfDay()->utc(),
                'ends_at' => now(KhoangThoiGian::muiGio())->addDays(30)->endOfDay()->utc(),
                'status' => PromotionStatus::Active,
                'priority' => 10,
            ]);

            $pricing = app(PricingService::class);
            $dong = [];

            foreach (self::DONG as [$tenSp, $kieu, $muc, $qua]) {
                $p = $sp[$tenSp];
                $kieuThat = $kieu ? PromotionType::from($kieu) : $km->type;

                $dong[$p->id] = [
                    'discount_type' => $kieu,
                    'discount_value' => $muc,
                    'promotional_price' => $kieuThat->laGiamGia()
                        ? $pricing->preview($p->base_price, $kieuThat, (float) ($muc ?? $km->discount_value))
                        : null,
                    'gift_item_id' => $qua ? GiftItem::tuSanPham($sp[$qua[0]])->id : null,
                    'gift_quantity' => $qua[1] ?? null,
                ];
            }

            $km->products()->sync($dong);
        });

        $this->command?->info('Đã tạo / cập nhật chương trình mẫu "Tuần lễ cây xanh".');
    }
}
