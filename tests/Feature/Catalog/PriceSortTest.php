<?php

namespace Tests\Feature\Catalog;

use App\Enums\PromotionStatus;
use App\Enums\PromotionType;
use App\Models\Category;
use App\Models\Product;
use App\Models\Promotion;
use App\Services\Pricing\PricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Sắp xếp theo giá phải khớp với con số khách đang nhìn thấy.
 * ============================================================
 * Trang danh sách in GIÁ SAU KHUYẾN MẠI lên thẻ sản phẩm, nhưng câu
 * truy vấn lại sắp theo GIÁ NIÊM YẾT. Hai con số đó khác nhau đúng ở
 * những sản phẩm đang giảm giá — tức là đúng ở nhóm hàng cửa hàng muốn
 * khách nhìn thấy trước.
 *
 * Kết quả với người dùng: chọn "Giá thấp đến cao" rồi thấy một món
 * 250.000₫ nằm dưới một món 300.000₫. Không cần biết gì về kỹ thuật
 * cũng thấy sai, và cái sai nằm ngay trên màn hình.
 */
class PriceSortTest extends TestCase
{
    use RefreshDatabase;

    private Category $danhMuc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->danhMuc = Category::factory()->create();
    }

    private function sanPham(string $ten, string $gia): Product
    {
        return Product::factory()
            ->for($this->danhMuc)
            ->price($gia)
            ->create(['name' => $ten]);
    }

    /** Gắn một chương trình đang chạy vào sản phẩm. */
    private function giamGia(
        Product $product,
        PromotionType $kieu,
        string $muc,
        array $ghiDe = [],
    ): Promotion {
        $promotion = Promotion::create(array_merge([
            'name' => 'Chương trình kiểm thử',
            'slug' => 'ct-'.uniqid(),
            'type' => $kieu,
            'discount_value' => $muc,
            'status' => PromotionStatus::Active,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
        ], $ghiDe));

        $promotion->products()->attach($product->id);

        return $promotion;
    }

    /** Tên sản phẩm theo đúng thứ tự câu truy vấn trả về. */
    private function thuTu(string $huong = 'asc'): array
    {
        return Product::query()
            ->orderByEffectivePrice($huong)
            ->pluck('name')
            ->all();
    }

    #[Test]
    public function hang_dang_giam_gia_duoc_xep_theo_gia_da_giam(): void
    {
        $re = $this->sanPham('Rẻ sau giảm', '500000.00');   // giảm 60% -> 200.000
        $this->giamGia($re, PromotionType::Percent, '60.00');

        $this->sanPham('Vừa tiền', '300000.00');
        $this->sanPham('Đắt', '900000.00');

        $this->assertSame(
            ['Rẻ sau giảm', 'Vừa tiền', 'Đắt'],
            $this->thuTu('asc'),
            'Món 500.000₫ giảm còn 200.000₫ phải đứng trước món 300.000₫.',
        );
    }

    #[Test]
    public function xep_giam_dan_cung_theo_gia_da_giam(): void
    {
        $tuot = $this->sanPham('Giảm sâu', '900000.00');    // -> 100.000
        $this->giamGia($tuot, PromotionType::FixedPrice, '100000.00');

        $this->sanPham('Không giảm', '400000.00');

        $this->assertSame(
            ['Không giảm', 'Giảm sâu'],
            $this->thuTu('desc'),
            'Xếp giảm dần cũng phải dùng giá khách trả, không dùng giá niêm yết.',
        );
    }

    #[Test]
    public function chuong_trinh_da_ket_thuc_khong_duoc_tinh(): void
    {
        /*
         * Mặt trái của bài trên. Một biểu thức chỉ nhìn bảng giảm giá mà
         * không xét khoảng thời gian sẽ xếp hàng theo mức giảm của tháng
         * trước — sai lặng lẽ, và sai mãi vì chương trình cũ không bao
         * giờ tự biến mất khỏi bảng.
         */
        $cu = $this->sanPham('Ưu đãi đã hết hạn', '800000.00');
        $this->giamGia($cu, PromotionType::FixedPrice, '100000.00', [
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->subDay(),
        ]);

        $this->sanPham('Giá thường', '500000.00');

        $this->assertSame(
            ['Giá thường', 'Ưu đãi đã hết hạn'],
            $this->thuTu('asc'),
        );
    }

    #[Test]
    public function chuong_trinh_dang_tam_dung_khong_duoc_tinh(): void
    {
        $tam = $this->sanPham('Đang tạm dừng', '800000.00');
        $this->giamGia($tam, PromotionType::FixedPrice, '100000.00', [
            'status' => PromotionStatus::Paused,
        ]);

        $this->sanPham('Giá thường', '500000.00');

        $this->assertSame(['Giá thường', 'Đang tạm dừng'], $this->thuTu('asc'));
    }

    #[Test]
    public function muc_giam_rieng_cua_san_pham_thang_muc_chung(): void
    {
        /*
         * Admin đặt chương trình chung "giảm 10%", rồi ghi đè riêng cho
         * một sản phẩm thành giá cố định 120.000₫. Biểu thức sắp xếp
         * phải theo mức riêng — đó cũng là mức PricingService dùng để in
         * ra màn hình.
         */
        $p = $this->sanPham('Có mức riêng', '900000.00');
        $km = $this->giamGia($p, PromotionType::Percent, '10.00');

        $km->products()->updateExistingPivot($p->id, [
            'discount_type' => PromotionType::FixedPrice->value,
            'discount_value' => '120000.00',
        ]);

        $this->sanPham('Giá thường', '500000.00');

        $this->assertSame(['Có mức riêng', 'Giá thường'], $this->thuTu('asc'));
    }

    #[Test]
    public function thu_tu_sqlkhop_voi_gia_ma_PricingService_tinh_ra(): void
    {
        /*
         * BÀI QUAN TRỌNG NHẤT ở đây.
         *
         * Biểu thức SQL là bản CHÉP LẠI luật của PricingService. Hai bản
         * chép của cùng một luật thì sớm muộn cũng trôi khỏi nhau — sửa
         * một bên, quên bên kia. Bài này bắt tận tay lúc đó xảy ra: nó
         * so thứ tự do cơ sở dữ liệu trả về với thứ tự tính bằng chính
         * PricingService, trên đủ ba kiểu giảm giá.
         */
        $a = $this->sanPham('A', '1000000.00');
        $this->giamGia($a, PromotionType::Percent, '35.00');        // 650.000

        $b = $this->sanPham('B', '700000.00');
        $this->giamGia($b, PromotionType::FixedAmount, '250000.00'); // 450.000

        $c = $this->sanPham('C', '600000.00');
        $this->giamGia($c, PromotionType::FixedPrice, '899000.00');  // 899.000 (tăng giá)

        $this->sanPham('D', '500000.00');                            // 500.000

        $pricing = app(PricingService::class);

        $theoPhp = Product::with('promotions')->get()
            ->sortBy(fn (Product $p) => (float) $pricing->resolve($p)->finalPrice)
            ->pluck('name')
            ->values()
            ->all();

        $this->assertSame($theoPhp, $this->thuTu('asc'),
            'Thứ tự của SQL phải trùng thứ tự tính bằng PricingService.');
    }
}
