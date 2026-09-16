<?php

namespace Tests\Feature\Pricing;

use App\Enums\PromotionStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\Promotion;
use App\Services\Promotion\ActivePromotionProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Banner, thanh thông báo và băng chuyền chỉ quảng cáo chương trình ĐANG CHẠY THẬT. */
class BannerKhuyenMaiDangChayTest extends TestCase
{
    use RefreshDatabase;

    private function km(string $ten, array $ghiDe = []): Promotion
    {
        $km = new Promotion();
        $km->forceFill(array_merge([
            'name' => $ten,
            'slug' => \Illuminate\Support\Str::slug($ten) . '-' . uniqid(),
            'type' => 'percent',
            'discount_value' => '20',
            'status' => PromotionStatus::Active,
            'starts_at' => Carbon::parse('2026-09-01 00:00:00', 'UTC'),
            'ends_at' => Carbon::parse('2026-09-30 00:00:00', 'UTC'),
        ], $ghiDe))->save();

        $km->products()->attach(Product::factory()->for(Category::factory())->create(['status' => 'active'])->id);

        return $km->fresh();
    }

    private function nhaCungCapMoi(): ActivePromotionProvider
    {
        $this->app->forgetInstance(ActivePromotionProvider::class);

        return app(ActivePromotionProvider::class);
    }

    #[Test]
    public function ngoai_khung_gio_KHONG_la_chuong_trinh_noi_bat(): void
    {
        $this->travelTo(Carbon::parse('2026-09-14 08:00:00', 'UTC'));
        $this->km('Giờ vàng', ['daily_start_time' => '19:00:00', 'daily_end_time' => '21:00:00', 'priority' => 10]);

        $this->assertNull($this->nhaCungCapMoi()->featured());

        $this->travelTo(Carbon::parse('2026-09-14 12:30:00', 'UTC'));
        $this->assertSame('Giờ vàng', $this->nhaCungCapMoi()->featured()?->name);
    }

    #[Test]
    public function chuong_trinh_uu_tien_cao_ngoai_gio_nhuong_cho_chuong_trinh_dang_chay(): void
    {
        $this->travelTo(Carbon::parse('2026-09-14 08:00:00', 'UTC'));
        $this->km('Giờ vàng', ['daily_start_time' => '19:00:00', 'daily_end_time' => '21:00:00', 'priority' => 10]);
        $this->km('Tuần lễ sen đá', ['priority' => 1]);

        $this->assertSame('Tuần lễ sen đá', $this->nhaCungCapMoi()->featured()?->name);
    }

    #[Test]
    public function thanh_thong_bao_tren_moi_trang_KHONG_quang_cao_ngoai_khung_gio(): void
    {
        $this->travelTo(Carbon::parse('2026-09-14 08:00:00', 'UTC'));
        $this->km('Giờ vàng', ['daily_start_time' => '19:00:00', 'daily_end_time' => '21:00:00']);

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringNotContainsString('class="announcement-bar"', $html);
        $this->assertStringNotContainsString('Giờ vàng', $html);
    }

    #[Test]
    public function bang_chuyen_hien_toi_da_ba_chuong_trinh_dang_chay_kem_muc_giam(): void
    {
        $this->travelTo(Carbon::parse('2026-09-14 08:00:00', 'UTC'));
        foreach (['Một', 'Hai', 'Ba', 'Bốn'] as $i => $ten) {
            $this->km('Chương trình ' . $ten, ['priority' => 10 - $i]);
        }

        $html = $this->get('/')->assertOk()->getContent();

        $dau = strpos($html, 'data-banner-rotator');
        $this->assertNotFalse($dau);
        $bangChuyen = substr($html, $dau, strpos($html, '</section>', $dau) - $dau);

        $this->assertSame(3, substr_count($bangChuyen, 'aria-label="Chương trình khuyến mại"'));
        $this->assertStringNotContainsString('Chương trình Bốn', $bangChuyen);
        $this->assertStringContainsString('Giảm đến 20%', $bangChuyen);
    }

    #[Test]
    public function chuong_trinh_KHONG_gan_san_pham_nao_khong_len_banner(): void
    {
        $this->travelTo(Carbon::parse('2026-09-14 08:00:00', 'UTC'));

        $rong = new Promotion();
        $rong->forceFill([
            'name' => 'Chương trình rỗng', 'slug' => 'chuong-trinh-rong', 'type' => 'percent',
            'discount_value' => '30', 'status' => PromotionStatus::Active, 'priority' => 99,
            'starts_at' => Carbon::parse('2026-09-01 00:00:00', 'UTC'),
            'ends_at' => Carbon::parse('2026-09-30 00:00:00', 'UTC'),
        ])->save();

        $this->km('Tuần lễ sen đá', ['priority' => 1]);

        $cacCt = $this->nhaCungCapMoi()->dangChay(3);

        $this->assertSame(['Tuần lễ sen đá'], $cacCt->pluck('name')->all());
    }

    #[Test]
    public function khong_co_chuong_trinh_dang_chay_thi_bang_chuyen_chi_con_slide_su_kien(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringNotContainsString('aria-label="Chương trình khuyến mại"', $html);
        $this->assertStringContainsString('Bạn đang chuẩn bị một sự kiện?', $html);
    }
}
