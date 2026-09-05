<?php

namespace Tests\Feature\Catalog;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * "Hàng mới về" phải thật sự là hàng mới.
 * ============================================================
 * Khối này trước đây là `latest()->take(8)` — nghĩa là "8 món thêm sau
 * cùng", KHÔNG phải "8 món mới". Hai thứ trùng nhau khi cửa hàng nhập
 * hàng đều tay, và tách hẳn khi không: nghỉ nhập ba tháng thì trang chủ
 * vẫn trưng tám món của quý trước dưới chữ "Hàng mới về".
 *
 * Bài kiểm thử quan trọng nhất ở đây là bài CUỐI: khi không có gì mới,
 * câu trả lời đúng là "không hiện gì cả", chứ không phải "lấy tạm mấy
 * món cũ nhìn cho có".
 */
class NewArrivalTest extends TestCase
{
    use RefreshDatabase;

    private function sanPham(string $ten, int $soNgayTruoc): Product
    {
        $p = Product::factory()
            ->for(Category::factory()->state(['kind' => 'plant', 'is_active' => true]))
            ->create(['name' => $ten, 'status' => 'active']);

        // created_at nằm ngoài $fillable và bị timestamps ghi đè, nên
        // phải đặt sau khi tạo.
        $p->forceFill(['created_at' => now()->subDays($soNgayTruoc)])->saveQuietly();

        return $p->refresh();
    }

    #[Test]
    public function hang_nhap_trong_khoang_ngay_thi_duoc_tinh_la_moi(): void
    {
        config()->set('catalog.new_arrival_days', 60);

        $this->sanPham('Cây mới hôm qua', 1);
        $this->sanPham('Cây nhập tháng trước', 40);

        $this->assertSame(2, Product::mainCatalog()->newArrivals()->count());
    }

    #[Test]
    public function hang_qua_nguong_ngay_thi_khong_con_la_moi(): void
    {
        config()->set('catalog.new_arrival_days', 60);

        $this->sanPham('Cây mới', 5);
        $this->sanPham('Cây nhập từ quý trước', 90);

        $ten = Product::mainCatalog()->newArrivals()->pluck('name')->all();

        $this->assertSame(['Cây mới'], $ten);
    }

    #[Test]
    public function nguong_ngay_doc_tu_cau_hinh_chu_khong_viet_cung(): void
    {
        // Hoa tươi và bonsai có nhịp bán khác nhau; con số phải sửa được
        // mà không phải đụng vào mã nguồn.
        $this->sanPham('Cây nhập 30 ngày trước', 30);

        config()->set('catalog.new_arrival_days', 60);
        $this->assertSame(1, Product::mainCatalog()->newArrivals()->count());

        config()->set('catalog.new_arrival_days', 14);
        $this->assertSame(0, Product::mainCatalog()->newArrivals()->count());
    }

    #[Test]
    public function khong_co_hang_moi_thi_trang_chu_khong_hien_khoi_do(): void
    {
        /*
         * ĐIỀU QUAN TRỌNG NHẤT TỆP NÀY.
         *
         * Cách hỏng cũ không phải là hiện sai vài món — mà là khối đó
         * KHÔNG BAO GIỜ rỗng, nên nó luôn nói "có hàng mới" kể cả khi
         * không có. Nay rỗng là một câu trả lời hợp lệ, và giao diện phải
         * chịu được câu trả lời đó: ẩn hẳn, không hiện khối trống chiếm
         * một màn hình đầu trang để nói rằng không có gì.
         */
        config()->set('catalog.new_arrival_days', 60);

        $this->sanPham('Cây nhập từ năm ngoái', 400);

        $res = $this->get('/');

        $res->assertOk();
        $res->assertDontSee('Hàng mới về');

        /*
         * CỐ Ý KHÔNG kiểm "tên sản phẩm không xuất hiện trên trang".
         *
         * Bản đầu của bài này có kiểm, và nó ĐỎ — đúng ra phải đỏ. Món
         * hàng cũ vẫn nằm trong danh mục nên nó xuất hiện hợp lệ ở khối
         * gợi ý và khối được yêu thích bên dưới. Việc phải giữ ở đây là
         * "khối HÀNG MỚI VỀ không hiện", không phải "món này biến mất
         * khỏi cả trang chủ" — cái thứ hai là một yêu cầu khác hẳn, và
         * sai.
         */
    }

    #[Test]
    public function co_hang_moi_thi_khoi_do_hien_ra(): void
    {
        // Mặt còn lại của bài trên: ẩn đúng lúc thì cũng phải hiện đúng
        // lúc, nếu không "ẩn khi rỗng" thành "ẩn luôn".
        config()->set('catalog.new_arrival_days', 60);

        $this->sanPham('Cây vừa nhập tuần này', 3);

        $res = $this->get('/');

        $res->assertOk();
        $res->assertSee('Hàng mới về');
        $res->assertSee('Cây vừa nhập tuần này');
    }
}
