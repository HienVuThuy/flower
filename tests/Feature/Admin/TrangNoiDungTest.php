<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Sửa nội dung trang giới thiệu / chính sách mà không đụng mã nguồn.
 * ============================================================
 * Bất biến quan trọng nhất: VĂN BẢN THUẦN. Không có đường nào để một
 * đoạn HTML gõ trong trang quản trị chạy trên trang công khai.
 */
class TrangNoiDungTest extends TestCase
{
    use RefreshDatabase;

    private function nguoi(UserRole $vaiTro = UserRole::Admin): User
    {
        $u = User::factory()->create();
        $u->role = $vaiTro;
        $u->save();

        return $u;
    }

    private function luu(array $noiDung): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->nguoi())
            ->put(route('admin.page-contents.update'), ['noi_dung' => $noiDung]);
    }

    #[Test]
    public function de_trong_thi_trang_dung_ban_viet_san(): void
    {
        $this->get(route('shop.pages.show', 'chinh-sach-doi-tra'))
            ->assertOk()
            ->assertSee('Nhận hàng: kiểm trước khi trả tiền');
    }

    #[Test]
    public function sua_noi_dung_thi_trang_cong_khai_hien_noi_dung_moi(): void
    {
        $this->luu(['chinh-sach-doi-tra' => "## Đổi trả trong 24 giờ\n\nHoa héo khi nhận: đổi mới miễn phí."])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.page-contents.edit'));

        $this->get(route('shop.pages.show', 'chinh-sach-doi-tra'))
            ->assertOk()
            ->assertSee('<h2>Đổi trả trong 24 giờ</h2>', false)
            ->assertSee('Hoa héo khi nhận: đổi mới miễn phí.')
            ->assertDontSee('Nhận hàng: kiểm trước khi trả tiền');
    }

    #[Test]
    public function HTML_go_vao_KHONG_chay_tren_trang_cong_khai(): void
    {
        $this->luu(['gioi-thieu' => "<script>alert('x')</script>\n\n## <img src=x onerror=alert(1)>"]);

        $html = $this->get(route('shop.pages.show', 'gioi-thieu'))->assertOk()->getContent();

        $this->assertStringNotContainsString("<script>alert('x')</script>", $html);
        $this->assertStringNotContainsString('<img src=x', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    #[Test]
    public function xoa_trang_noi_dung_thi_quay_ve_ban_viet_san(): void
    {
        $this->luu(['chinh-sach-doi-tra' => 'Nội dung tạm.']);
        $this->luu(['chinh-sach-doi-tra' => '   ']);

        $this->assertNull(Setting::get('trang_noi_dung.chinh-sach-doi-tra'));

        $this->get(route('shop.pages.show', 'chinh-sach-doi-tra'))
            ->assertOk()
            ->assertSee('Nhận hàng: kiểm trước khi trả tiền');
    }

    #[Test]
    public function khong_tao_duoc_khoa_cai_dat_la(): void
    {
        // Chỉ năm trang đã biết được ghi — không để ô gửi lên tuỳ tiện tạo khoá mới.
        $this->luu(['khong-co-trang-nay' => 'x', 'gioi-thieu' => 'Xin chào.']);

        /*
         * KIỂM KHÔNG CÓ DÒNG, không chỉ "đọc ra null".
         *
         * Thử phá code đã chứng minh: cho ghi mọi khoá gửi lên mà bài vẫn
         * xanh — khoá lạ không qua được validator nên bị ghi giá trị NULL,
         * và một dòng NULL đọc ra cũng là null. Dòng rác vẫn nằm trong bảng.
         */
        $this->assertDatabaseMissing('settings', ['key' => 'trang_noi_dung.khong-co-trang-nay']);
        $this->assertSame('Xin chào.', Setting::get('trang_noi_dung.gioi-thieu'));
    }

    #[Test]
    public function trang_quan_tri_liet_ke_du_nam_trang(): void
    {
        $html = $this->actingAs($this->nguoi())
            ->get(route('admin.page-contents.edit'))
            ->assertOk()
            ->getContent();

        foreach (\App\Http\Controllers\Shop\PageController::all() as $slug => $tieuDe) {
            $this->assertStringContainsString('name="noi_dung[' . $slug . ']"', $html);
        }
    }

    #[Test]
    public function nhan_vien_khong_co_quyen_he_thong_bi_chan(): void
    {
        $nv = $this->nguoi(UserRole::Staff);

        $this->actingAs($nv)->get(route('admin.page-contents.edit'))->assertForbidden();
        $this->actingAs($nv)->put(route('admin.page-contents.update'), ['noi_dung' => ['gioi-thieu' => 'x']])->assertForbidden();

        $this->assertNull(Setting::get('trang_noi_dung.gioi-thieu'));
    }
}
