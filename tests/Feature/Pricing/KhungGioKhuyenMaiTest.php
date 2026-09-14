<?php

namespace Tests\Feature\Pricing;

use App\Enums\PromotionStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\Promotion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Khung giờ và thứ trong tuần của khuyến mại đọc theo GIỜ VIỆT NAM.
 * ============================================================
 * Lỗi đã sửa: ứng dụng chạy UTC, và isRunning() so khung giờ bằng giờ
 * UTC. "Giờ vàng 19:00–21:00" admin nhập theo đồng hồ ở cửa hàng thì giá
 * giảm thật sự chạy lúc 02:00–04:00 sáng. PricingService lọc bằng hàm này,
 * nên đây là lỗi GIÁ. Trước đây không có bài kiểm thử nào đụng khung giờ.
 *
 * 14/09/2026 là thứ Hai.
 */
class KhungGioKhuyenMaiTest extends TestCase
{
    use RefreshDatabase;

    private function km(array $ghiDe = []): Promotion
    {
        $km = new Promotion();
        $km->forceFill(array_merge([
            'name' => 'Giờ vàng',
            'slug' => 'gio-vang-' . uniqid(),
            'type' => 'percent',
            'discount_value' => '50',
            'status' => PromotionStatus::Active,
            'starts_at' => Carbon::parse('2026-09-01 00:00:00', 'UTC'),
            'ends_at' => Carbon::parse('2026-09-30 00:00:00', 'UTC'),
        ], $ghiDe))->save();

        return $km->fresh();
    }

    #[Test]
    public function khung_19_21_gio_CHAY_luc_19h30_gio_Viet_Nam(): void
    {
        // 12:30 UTC = 19:30 ở Hà Nội. Bản cũ so 12:30 với 19:00–21:00 → không chạy.
        $this->travelTo(Carbon::parse('2026-09-14 12:30:00', 'UTC'));

        $this->assertTrue($this->km(['daily_start_time' => '19:00:00', 'daily_end_time' => '21:00:00'])->isRunning());
    }

    #[Test]
    public function khung_19_21_gio_KHONG_chay_luc_19h30_UTC_tuc_2h30_sang(): void
    {
        // 19:30 UTC = 02:30 sáng hôm sau ở Hà Nội. Bản cũ lại cho chạy — giảm giá lúc nửa đêm.
        $this->travelTo(Carbon::parse('2026-09-14 19:30:00', 'UTC'));

        $this->assertFalse($this->km(['daily_start_time' => '19:00:00', 'daily_end_time' => '21:00:00'])->isRunning());
    }

    #[Test]
    public function chi_thu_Hai_CHAY_luc_1h_sang_thu_Hai_gio_Viet_Nam(): void
    {
        // 13/09 18:00 UTC là Chủ nhật theo UTC, nhưng đã là 01:00 thứ Hai ở Hà Nội.
        $this->travelTo(Carbon::parse('2026-09-13 18:00:00', 'UTC'));

        $this->assertTrue($this->km(['weekdays' => [1]])->isRunning());
        $this->assertFalse($this->km(['weekdays' => [7]])->isRunning(), 'Chủ nhật đã qua ở Hà Nội');
    }

    #[Test]
    public function sap_xep_theo_gia_thuc_trong_SQL_ap_dung_khung_gio_theo_gio_Viet_Nam(): void
    {
        /*
         * scopeOrderByEffectivePrice() dựng lại cùng luật bằng SQL. Hai nơi
         * lệch nhau thì thứ tự "giá thấp → cao" khác với giá trên thẻ.
         */
        $dm = Category::factory()->create();
        $re = Product::factory()->for($dm)->create(['name' => 'Rẻ sẵn', 'base_price' => '300000.00']);
        $dat = Product::factory()->for($dm)->create(['name' => 'Đắt nhưng giờ vàng', 'base_price' => '500000.00']);

        $this->km(['daily_start_time' => '19:00:00', 'daily_end_time' => '21:00:00'])->products()->attach($dat->id);

        // 19:30 ở Hà Nội: 500.000 giảm 50% = 250.000 < 300.000 → đứng đầu.
        $this->travelTo(Carbon::parse('2026-09-14 12:30:00', 'UTC'));
        $this->assertSame($dat->id, Product::query()->orderByEffectivePrice('asc')->value('id'));

        // 02:30 sáng ở Hà Nội: ngoài khung → hàng 300.000 đứng đầu.
        $this->travelTo(Carbon::parse('2026-09-14 19:30:00', 'UTC'));
        $this->assertSame($re->id, Product::query()->orderByEffectivePrice('asc')->value('id'));
    }
}
