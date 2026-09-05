<?php

namespace Tests\Feature\Admin;

use App\Enums\ProductType;
use App\Enums\SellingForm;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Ba trường phân loại sản phẩm phải khớp nhau.
 * ============================================================
 * category_id, product_type và selling_form trước đây được kiểm riêng
 * từng cái: mỗi ô chỉ phải nằm trong danh sách giá trị cho phép. Ghép
 * lại thì lưu được những tổ hợp vô nghĩa, và không có thông báo nào.
 *
 * Hai hậu quả nhìn thấy được:
 *   - Sai danh mục: hàng nằm nhầm gian. mainCatalog()/supplyCatalog()
 *     chia hàng theo nhóm danh mục, nên một bó hoa xếp vào danh mục vật
 *     tư sẽ biến mất khỏi gian hoa.
 *   - Sai hình thức bán: mất bộ thông tin chăm sóc. careProfile() lấy
 *     theo selling_form, nên "cây cảnh bán theo bó" không còn ô ánh
 *     sáng, đất, tưới nước.
 */
class ProductClassificationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->role = UserRole::Admin;
        $user->save();

        return $user;
    }

    /** Một bộ dữ liệu sản phẩm hợp lệ, đủ để qua mọi luật khác. */
    private function duLieu(array $ghiDe = []): array
    {
        return array_merge([
            'category_id' => Category::factory()->create()->id,
            'name' => 'Chậu sen đá kiểm thử',
            'slug' => 'chau-sen-da-kiem-thu',
            'product_code' => 'KT-'.strtoupper(bin2hex(random_bytes(3))),
            'product_type' => ProductType::Plant->value,
            'selling_form' => SellingForm::Pot->value,
            'base_price' => '150000',
            'track_inventory' => '1',
            'stock_quantity' => '10',
            'status' => 'active',
        ], $ghiDe);
    }

    private function taoSanPham(array $ghiDe = [])
    {
        return $this->actingAs($this->admin())
            ->post('/admin/products', $this->duLieu($ghiDe));
    }

    #[Test]
    public function to_hop_dung_thi_luu_duoc(): void
    {
        /*
         * Bài này đi TRƯỚC các bài chặn. Một luật mới chỉ đáng tin khi
         * đã chứng minh nó không chặn nhầm đường đi bình thường — nếu
         * không thì "chặn được mọi thứ" cũng làm mọi bài kia xanh.
         */
        $this->taoSanPham()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('products', ['slug' => 'chau-sen-da-kiem-thu']);
    }

    #[Test]
    public function cay_canh_khong_ban_duoc_duoi_hinh_thuc_bo_hoa(): void
    {
        $this->taoSanPham(['selling_form' => SellingForm::Bouquet->value])
            ->assertSessionHasErrors('selling_form');

        $this->assertSame(0, Product::count());
    }

    #[Test]
    public function hoa_tuoi_khong_ban_duoc_duoi_hinh_thuc_chau(): void
    {
        $this->taoSanPham([
            'product_type' => ProductType::Flower->value,
            'selling_form' => SellingForm::Pot->value,
        ])->assertSessionHasErrors('selling_form');
    }

    #[Test]
    public function danh_muc_vat_tu_khong_nhan_hoa_tuoi(): void
    {
        $vatTu = Category::factory()->supply()->create();

        $this->taoSanPham([
            'category_id' => $vatTu->id,
            'product_type' => ProductType::Flower->value,
            'selling_form' => SellingForm::Bouquet->value,
        ])->assertSessionHasErrors('category_id');
    }

    #[Test]
    public function danh_muc_cay_hoa_khong_nhan_vat_tu(): void
    {
        // Chiều ngược lại. Chặn một chiều mà bỏ chiều kia thì gian hàng
        // vật tư vẫn lẫn hàng, chỉ là lẫn theo hướng khác.
        $this->taoSanPham([
            'product_type' => ProductType::Other->value,
            'selling_form' => SellingForm::Other->value,
        ])->assertSessionHasErrors('category_id');
    }

    #[Test]
    public function sua_san_pham_cung_bi_kiem_y_het(): void
    {
        /*
         * Đường DỄ tạo tổ hợp sai nhất, vì admin chỉ đổi một ô rồi lưu.
         * Chép luật vào một FormRequest mà quên cái kia là lỗ hổng
         * thường gặp nhất của kiểu kiểm tra này.
         */
        $product = Product::factory()->for(Category::factory())->create();

        $this->actingAs($this->admin())
            ->put("/admin/products/{$product->id}", $this->duLieu([
                'category_id' => $product->category_id,
                'slug' => $product->slug,
                'product_code' => $product->product_code,
                'selling_form' => SellingForm::Bouquet->value,
            ]))
            ->assertSessionHasErrors('selling_form');
    }

    #[Test]
    public function loi_noi_ro_hinh_thuc_nao_dung_duoc(): void
    {
        // "Trường này không hợp lệ" bắt admin đoán. Thông báo phải kể ra
        // đúng những lựa chọn dùng được, nếu không họ thử từng cái một.
        // assertInvalid so KHỚP MỘT PHẦN chuỗi, nên kiểm được nội dung
        // thông báo mà không phải chép nguyên câu vào bài kiểm tra.
        $this->taoSanPham(['selling_form' => SellingForm::Bouquet->value])
            ->assertInvalid([
                'selling_form' => SellingForm::Pot->label(),
            ])
            ->assertInvalid([
                'selling_form' => ProductType::Plant->label(),
            ]);
    }
}
