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

/** Khung giờ và thứ trong tuần của khuyến mại đọc theo GIỜ VIỆT NAM. */
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
        $this->travelTo(Carbon::parse('2026-09-14 12:30:00', 'UTC'));

        $this->assertTrue($this->km(['daily_start_time' => '19:00:00', 'daily_end_time' => '21:00:00'])->isRunning());
    }

    #[Test]
    public function khung_19_21_gio_KHONG_chay_luc_19h30_UTC_tuc_2h30_sang(): void
    {
        $this->travelTo(Carbon::parse('2026-09-14 19:30:00', 'UTC'));

        $this->assertFalse($this->km(['daily_start_time' => '19:00:00', 'daily_end_time' => '21:00:00'])->isRunning());
    }

    #[Test]
    public function chi_thu_Hai_CHAY_luc_1h_sang_thu_Hai_gio_Viet_Nam(): void
    {
        $this->travelTo(Carbon::parse('2026-09-13 18:00:00', 'UTC'));

        $this->assertTrue($this->km(['weekdays' => [1]])->isRunning());
        $this->assertFalse($this->km(['weekdays' => [7]])->isRunning(), 'Chủ nhật đã qua ở Hà Nội');
    }

    #[Test]
    public function sap_xep_theo_gia_thuc_trong_SQL_ap_dung_khung_gio_theo_gio_Viet_Nam(): void
    {
        $dm = Category::factory()->create();
        $re = Product::factory()->for($dm)->create(['name' => 'Rẻ sẵn', 'base_price' => '300000.00']);
        $dat = Product::factory()->for($dm)->create(['name' => 'Đắt nhưng giờ vàng', 'base_price' => '500000.00']);

        $this->km(['daily_start_time' => '19:00:00', 'daily_end_time' => '21:00:00'])->products()->attach($dat->id);

        $this->travelTo(Carbon::parse('2026-09-14 12:30:00', 'UTC'));
        $this->assertSame($dat->id, Product::query()->orderByEffectivePrice('asc')->value('id'));

        $this->travelTo(Carbon::parse('2026-09-14 19:30:00', 'UTC'));
        $this->assertSame($re->id, Product::query()->orderByEffectivePrice('asc')->value('id'));
    }
}
