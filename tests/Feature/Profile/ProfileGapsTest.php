<?php

namespace Tests\Feature\Profile;

use App\Models\Category;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use App\Services\Shop\DisplayScheme;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Những chỗ hụt ở trang Hồ sơ tài khoản.
 * ============================================================
 * Ba việc người dùng cần làm mà trước đây không có đường:
 *
 *   1. Đổi mật khẩu khi KHÔNG CÒN NHỚ mật khẩu cũ.
 *   2. Chọn nền sáng / tối.
 *   3. Xem lại và gỡ đánh giá của chính mình.
 *
 * Việc thứ ba đáng nói nhất: trang Hồ sơ vẫn ĐẾM "Đánh giá đã viết: 4",
 * nên người dùng biết chúng tồn tại — chỉ là không có cách nào tới.
 */
class ProfileGapsTest extends TestCase
{
    use RefreshDatabase;

    private function khach(): User
    {
        return User::factory()->create();
    }

    // ================================================================
    // Quên mật khẩu khi đang đăng nhập
    // ================================================================

    #[Test]
    public function gui_duoc_lien_ket_dat_lai_mat_khau_cho_chinh_minh(): void
    {
        /*
         * Biểu mẫu đổi mật khẩu bắt nhập mật khẩu hiện tại — đúng, vì
         * thiếu phép kiểm đó thì ai mượn được máy đang mở sẵn cũng chiếm
         * được tài khoản.
         *
         * Nhưng người đăng nhập bằng "ghi nhớ đăng nhập" từ nhiều tháng
         * trước hoàn toàn có thể không còn nhớ mật khẩu cũ, và trang
         * "Quên mật khẩu" thì nằm sau middleware `guest`.
         */
        Notification::fake();

        $user = $this->khach();

        $this->actingAs($user)
            ->post('/tai-khoan/gui-lien-ket-mat-khau')
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('password_reset_tokens', ['email' => $user->email]);
    }

    #[Test]
    public function lien_ket_luon_gui_ve_email_cua_chinh_tai_khoan(): void
    {
        /*
         * Địa chỉ lấy từ tài khoản đang đăng nhập, KHÔNG từ biểu mẫu.
         * Nhận email từ request thì màn hình này thành công cụ gửi thư
         * tới địa chỉ bất kỳ, ký tên cửa hàng.
         */
        Notification::fake();

        $user = $this->khach();
        $nguoiKhac = $this->khach();

        $this->actingAs($user)->post('/tai-khoan/gui-lien-ket-mat-khau', [
            'email' => $nguoiKhac->email,
        ]);

        $this->assertDatabaseHas('password_reset_tokens', ['email' => $user->email]);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $nguoiKhac->email]);
    }

    // ================================================================
    // Nền sáng / tối
    // ================================================================

    #[Test]
    public function chon_duoc_nen_toi_va_luu_vao_cookie(): void
    {
        $this->actingAs($this->khach())
            ->post('/che-do-hien-thi', ['che_do' => DisplayScheme::TOI])
            ->assertRedirect()
            ->assertCookie(DisplayScheme::COOKIE, DisplayScheme::TOI);
    }

    #[Test]
    public function khach_chua_dang_nhap_cung_doi_duoc_nen(): void
    {
        /*
         * Phần lớn người xem trang bán hàng là khách vãng lai. Bắt đăng
         * nhập mới đổi được nền là biến một tuỳ chọn dễ chịu thành một
         * rào cản, cho đúng nhóm đông nhất.
         */
        $this->post('/che-do-hien-thi', ['che_do' => DisplayScheme::TOI])
            ->assertRedirect()
            ->assertCookie(DisplayScheme::COOKIE, DisplayScheme::TOI);
    }

    #[Test]
    public function gia_tri_bia_dat_bi_tu_choi(): void
    {
        $this->post('/che-do-hien-thi', ['che_do' => 'ke-tan-cong'])
            ->assertSessionHasErrors('che_do');
    }

    #[Test]
    public function may_chu_dung_san_thuoc_tinh_tren_the_html(): void
    {
        /*
         * BÀI QUAN TRỌNG NHẤT của phần này.
         *
         * Nếu thuộc tính chỉ do JavaScript đặt sau khi tải thì trang
         * luôn vẽ nền sáng trước rồi mới nháy sang tối — cú nháy đó chói
         * mắt đúng vào ban đêm, tức đúng lúc người ta bật chế độ tối.
         */
        $this->withCookie(DisplayScheme::COOKIE, DisplayScheme::TOI)
            ->get('/')
            ->assertOk()
            ->assertSee('data-scheme="toi"', false);
    }

    #[Test]
    public function chua_chon_gi_thi_de_may_chu_quyet_dinh_sau(): void
    {
        // "auto" đi xuống trình duyệt để đoạn script trong <head> quy về
        // sáng hay tối theo cài đặt của hệ điều hành — thông tin mà máy
        // chủ không thể biết.
        $this->get('/')
            ->assertOk()
            ->assertSee('data-scheme="auto"', false);
    }

    // ================================================================
    // Đánh giá của tôi
    // ================================================================

    private function danhGia(User $user, string $tenSanPham): Review
    {
        $product = Product::factory()
            ->for(Category::factory())
            ->create(['name' => $tenSanPham]);

        return Review::create([
            'product_id' => $product->id,
            'user_id' => $user->id,
            'rating' => 5,
            'comment' => 'Hoa tươi, giao đúng hẹn.',
            'is_visible' => true,
        ]);
    }

    #[Test]
    public function xem_duoc_danh_sach_danh_gia_cua_chinh_minh(): void
    {
        $user = $this->khach();
        $this->danhGia($user, 'Bó hồng đỏ');

        $this->actingAs($user)
            ->get('/danh-gia-cua-toi')
            ->assertOk()
            ->assertSee('Bó hồng đỏ');
    }

    #[Test]
    public function khong_thay_danh_gia_cua_nguoi_khac(): void
    {
        // Chốt quan trọng nhất của trang này: nó liệt kê theo user_id,
        // và một điều kiện lọc bị quên là lộ toàn bộ đánh giá của mọi
        // người kèm tên sản phẩm họ đã mua.
        $user = $this->khach();
        $nguoiKhac = $this->khach();

        $this->danhGia($nguoiKhac, 'Chậu sen đá của người khác');

        $this->actingAs($user)
            ->get('/danh-gia-cua-toi')
            ->assertOk()
            ->assertDontSee('Chậu sen đá của người khác');
    }

    #[Test]
    public function go_duoc_danh_gia_tu_trang_nay(): void
    {
        $user = $this->khach();
        $review = $this->danhGia($user, 'Bó hồng đỏ');

        $this->actingAs($user)
            ->delete("/danh-gia/{$review->id}")
            ->assertRedirect();

        $this->assertDatabaseMissing('reviews', ['id' => $review->id]);
    }

    #[Test]
    public function khong_go_duoc_danh_gia_cua_nguoi_khac(): void
    {
        $review = $this->danhGia($this->khach(), 'Bó hồng đỏ');

        $this->actingAs($this->khach())
            ->delete("/danh-gia/{$review->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('reviews', ['id' => $review->id]);
    }

    #[Test]
    public function trang_ho_so_co_duong_di_toi_moi_con_so_no_dem(): void
    {
        /*
         * Trước bản này, "Đánh giá đã viết: 4" là ngõ cụt — ba con số
         * kia bấm được, riêng nó thì không. Ví voucher và Lịch chăm cây
         * cũng đã có trang riêng nhưng không được nhắc tới ở đây.
         */
        $user = $this->khach();

        $html = $this->actingAs($user)->get('/tai-khoan')->assertOk()->getContent();

        foreach ([
            route('shop.reviews.mine'),
            route('shop.vouchers.index'),
            route('shop.care.index'),
            route('shop.wishlist.index'),
        ] as $duongDan) {
            $this->assertStringContainsString($duongDan, $html, "Thiếu đường tới {$duongDan}");
        }
    }

    #[Test]
    public function khach_chua_dang_nhap_khong_xem_duoc_danh_gia_cua_ai(): void
    {
        $this->get('/danh-gia-cua-toi')->assertRedirect('/login');
    }
}
