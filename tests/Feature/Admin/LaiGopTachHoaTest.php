<?php

namespace Tests\Feature\Admin;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ProductType;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Services\Analytics\KhoangThoiGian;
use App\Services\Analytics\ProfitReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Lãi gộp theo phiếu nhập KHÔNG tính hoa tươi.
 * ============================================================
 * Hoa không nhập kho theo phiếu; giá vốn hoa tính theo lô ở một báo cáo
 * riêng. Để dòng hoa lọt vào bảng này thì chúng luôn "không có giá vốn" —
 * tỉ lệ phủ tụt vô lý và trang giục lập phiếu nhập cho hoa.
 */
class LaiGopTachHoaTest extends TestCase
{
    use RefreshDatabase;

    private function donGiao(ProductType $loai, string $ten, string $gia): void
    {
        $sp = Product::factory()->for(Category::factory())->price($gia)->create(['name' => $ten]);
        $sp->forceFill(['product_type' => $loai])->save();

        $don = Order::create([
            'order_number' => 'FP-LG-' . strtoupper(bin2hex(random_bytes(3))),
            'recipient_name' => 'Khách', 'recipient_phone' => '0912345678',
            'shipping_address' => '1 Đường', 'shipping_province' => 'Thành phố Hà Nội',
            'payment_method' => 'cod', 'subtotal' => $gia, 'discount_total' => '0.00',
            'shipping_fee' => '0.00', 'coupon_discount' => '0.00', 'grand_total' => $gia,
        ]);
        $don->forceFill(['status' => OrderStatus::Completed, 'payment_status' => PaymentStatus::Paid, 'completed_at' => now()])->save();

        $don->items()->create([
            'product_id' => $sp->id, 'product_name' => $ten, 'quantity' => 1,
            'unit_base_price' => $gia, 'unit_price' => $gia, 'line_total' => $gia,
        ]);
    }

    #[Test]
    public function dong_hoa_KHONG_vao_doanh_thu_va_do_phu_cua_bang_theo_phieu_nhap(): void
    {
        $this->donGiao(ProductType::Flower, 'Bó hồng thử', '500000.00');
        $this->donGiao(ProductType::Plant, 'Chậu kim tiền thử', '300000.00');

        $bao = app(ProfitReport::class)->trong(new KhoangThoiGian())->baoCao();

        $this->assertSame('300000.00', $bao['doanh_thu'], 'Doanh thu hàng không gồm hoa');
        $this->assertSame(1, $bao['dong_khong_gia_von']);
        $this->assertFalse(
            $bao['can_nhap_gia_von']->contains(fn ($r) => $r['ten'] === 'Bó hồng thử'),
            'Không giục lập phiếu nhập cho hoa',
        );
        $this->assertTrue($bao['can_nhap_gia_von']->contains(fn ($r) => $r['ten'] === 'Chậu kim tiền thử'));
    }

    #[Test]
    public function dong_ban_KHONG_ghi_quy_cach_dung_gia_von_binh_quan_cac_quy_cach(): void
    {
        /*
         * Đơn cũ chỉ ghi sản phẩm, còn giá vốn lưu theo quy cách. Không đoán
         * quy cách nào đã bán: 4 cái @100.000 và 4 cái @200.000 → 150.000.
         */
        $sp = Product::factory()->for(Category::factory())->price('300000.00')->create(['name' => 'Kim tiền nhiều quy cách']);
        $sp->forceFill(['product_type' => ProductType::Plant])->save();

        $trang = $sp->variants()->create(['name' => 'Chậu trắng', 'price' => '300000.00', 'stock_quantity' => 0, 'track_inventory' => true, 'is_active' => true]);
        $den = $sp->variants()->create(['name' => 'Chậu đen', 'price' => '300000.00', 'stock_quantity' => 0, 'track_inventory' => true, 'is_active' => true]);

        $phieu = \App\Models\StockReceipt::create(['code' => 'NK-QC-1', 'received_at' => now()->subDays(10)->toDateString()]);
        $phieu->items()->create(['product_id' => $sp->id, 'product_variant_id' => $trang->id, 'product_name' => $sp->name, 'quantity' => 4, 'unit_cost' => '100000.00']);
        $phieu->items()->create(['product_id' => $sp->id, 'product_variant_id' => $den->id, 'product_name' => $sp->name, 'quantity' => 4, 'unit_cost' => '200000.00']);
        app(\App\Services\Inventory\StockReceiptService::class)->ghiSo($phieu->fresh('items'));

        $don = Order::create([
            'order_number' => 'FP-QC-1', 'recipient_name' => 'Khách', 'recipient_phone' => '0912345678',
            'shipping_address' => '1 Đường', 'shipping_province' => 'Thành phố Hà Nội', 'payment_method' => 'cod',
            'subtotal' => '300000.00', 'discount_total' => '0.00', 'shipping_fee' => '0.00', 'coupon_discount' => '0.00', 'grand_total' => '300000.00',
        ]);
        $don->forceFill(['status' => OrderStatus::Completed, 'payment_status' => PaymentStatus::Paid, 'completed_at' => now()])->save();
        $don->items()->create([
            'product_id' => $sp->id, 'product_variant_id' => null, 'product_name' => $sp->name, 'quantity' => 1,
            'unit_base_price' => '300000.00', 'unit_price' => '300000.00', 'line_total' => '300000.00',
        ]);

        $bao = app(ProfitReport::class)->trong(new KhoangThoiGian())->baoCao();

        $this->assertSame(0, $bao['dong_khong_gia_von']);
        $this->assertSame('150000.00', $bao['gia_von']);
    }

    #[Test]
    public function mon_TAT_theo_doi_ton_duoc_danh_dau_va_trang_chi_cho_bat(): void
    {
        /*
         * Biểu mẫu nhập kho không liệt kê món tắt theo dõi tồn. Giục "nhập
         * giá vốn" cho nó là ngõ cụt — gặp thật trên dữ liệu: Monstera.
         */
        $this->donGiao(ProductType::Plant, 'Monstera thử', '850000.00');
        $this->donGiao(ProductType::Plant, 'Kim tiền có theo dõi', '300000.00');

        Product::where('name', 'Monstera thử')->update(['track_inventory' => false]);
        Product::where('name', 'Kim tiền có theo dõi')->update(['track_inventory' => true]);

        $canNhap = app(ProfitReport::class)->trong(new KhoangThoiGian())->baoCao()['can_nhap_gia_von']->keyBy('ten');

        $this->assertTrue($canNhap['Monstera thử']['khong_theo_doi']);
        $this->assertFalse($canNhap['Kim tiền có theo dõi']['khong_theo_doi']);

        $admin = \App\Models\User::factory()->create();
        $admin->role = \App\Enums\UserRole::Admin;
        $admin->save();

        $html = $this->actingAs($admin)->get(route('admin.analytics.profit', ['ky' => 'all']))->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'data-khong-theo-doi'), 'Chỉ món tắt theo dõi mới có lời nhắc');
        $this->assertStringContainsString(route('admin.products.edit', $canNhap['Monstera thử']['product_id']), $html);
    }

    #[Test]
    public function dong_CO_quy_cach_van_dung_dung_gia_quy_cach_do(): void
    {
        // Bình quân gộp chỉ là đường lùi cho dòng thiếu quy cách — không được đè lên dòng có quy cách.
        $sp = Product::factory()->for(Category::factory())->price('300000.00')->create(['name' => 'Kim tiền quy cách rõ']);
        $sp->forceFill(['product_type' => ProductType::Plant])->save();
        $trang = $sp->variants()->create(['name' => 'Chậu trắng', 'price' => '300000.00', 'stock_quantity' => 0, 'track_inventory' => true, 'is_active' => true]);
        $den = $sp->variants()->create(['name' => 'Chậu đen', 'price' => '300000.00', 'stock_quantity' => 0, 'track_inventory' => true, 'is_active' => true]);

        $phieu = \App\Models\StockReceipt::create(['code' => 'NK-QC-2', 'received_at' => now()->subDays(10)->toDateString()]);
        $phieu->items()->create(['product_id' => $sp->id, 'product_variant_id' => $trang->id, 'product_name' => $sp->name, 'quantity' => 4, 'unit_cost' => '100000.00']);
        $phieu->items()->create(['product_id' => $sp->id, 'product_variant_id' => $den->id, 'product_name' => $sp->name, 'quantity' => 4, 'unit_cost' => '200000.00']);
        app(\App\Services\Inventory\StockReceiptService::class)->ghiSo($phieu->fresh('items'));

        $don = Order::create([
            'order_number' => 'FP-QC-2', 'recipient_name' => 'Khách', 'recipient_phone' => '0912345678',
            'shipping_address' => '1 Đường', 'shipping_province' => 'Thành phố Hà Nội', 'payment_method' => 'cod',
            'subtotal' => '300000.00', 'discount_total' => '0.00', 'shipping_fee' => '0.00', 'coupon_discount' => '0.00', 'grand_total' => '300000.00',
        ]);
        $don->forceFill(['status' => OrderStatus::Completed, 'payment_status' => PaymentStatus::Paid, 'completed_at' => now()])->save();
        $don->items()->create([
            'product_id' => $sp->id, 'product_variant_id' => $den->id, 'product_name' => $sp->name, 'quantity' => 1,
            'unit_base_price' => '300000.00', 'unit_price' => '300000.00', 'line_total' => '300000.00',
        ]);

        $this->assertSame('200000.00', app(ProfitReport::class)->trong(new KhoangThoiGian())->baoCao()['gia_von']);
    }
}
