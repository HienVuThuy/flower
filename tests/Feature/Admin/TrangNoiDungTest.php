<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\Setting;
use App\Models\User;
use App\Services\Content\TrangNoiDung;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Sửa nội dung trang giới thiệu / chính sách mà không đụng mã nguồn.
 * ============================================================
 * Hai bất biến:
 *   1. Ô soạn điền sẵn nội dung đang hiện — không phải chép lại cả trang.
 *   2. Không có đường nào để HTML gõ trong ô soạn chạy trên trang công khai.
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

    private function html(string $van): string
    {
        return (string) app(TrangNoiDung::class)->html($van);
    }

    /* ================= BẢN VIẾT SẴN ================= */

    #[Test]
    public function chua_sua_thi_trang_hien_ban_viet_san_voi_du_cau_truc(): void
    {
        $html = $this->get(route('shop.pages.show', 'chinh-sach-doi-tra'))->assertOk()->getContent();

        $this->assertStringContainsString('<h2>Nhận hàng: kiểm trước khi trả tiền</h2>', $html);
        $this->assertStringContainsString('<li><strong>Giao sai sản phẩm</strong> so với đơn đã đặt.</li>', $html);
        $this->assertStringContainsString('<ol>', $html);
        $this->assertStringContainsString('class="static-page__notice"', $html);
        $this->assertStringContainsString('<p class="lead">', $html);
    }

    #[Test]
    public function ban_viet_san_tu_dien_thong_tin_tu_cai_dat(): void
    {
        Setting::set('site_hotline', '0987654321');

        $html = $this->get(route('shop.pages.show', 'lien-he'))->assertOk()->getContent();

        $this->assertStringContainsString('<dd>0987654321</dd>', $html);
        $this->assertStringContainsString('href="/don-hang/tra-cuu"', $html);
        $this->assertStringContainsString('class="static-page__table"', $html, 'Bảng phí giao đọc từ cấu hình');
        $this->assertStringNotContainsString('{hotline}', $html);
        $this->assertStringNotContainsString('{bang_phi_giao_hang}', $html);
    }

    #[Test]
    public function moi_trang_deu_co_ban_viet_san(): void
    {
        foreach (\App\Http\Controllers\Shop\PageController::all() as $slug => $tieuDe) {
            $this->assertNotSame('', app(TrangNoiDung::class)->banVietSan($slug), $slug . ' thiếu bản viết sẵn');
            $this->get(route('shop.pages.show', $slug))->assertOk()->assertSee($tieuDe);
        }
    }

    /* ================= Ô SOẠN ĐIỀN SẴN ================= */

    #[Test]
    public function o_soan_dien_san_noi_dung_dang_hien(): void
    {
        $html = $this->actingAs($this->nguoi())
            ->get(route('admin.page-contents.edit'))
            ->assertOk()
            ->getContent();

        $dau = strpos($html, 'name="noi_dung[chinh-sach-doi-tra]"');
        $this->assertNotFalse($dau);
        $o = substr($html, $dau, strpos($html, '</textarea>', $dau) - $dau);

        $this->assertStringContainsString('## Nhận hàng: kiểm trước khi trả tiền', html_entity_decode($o));
    }

    #[Test]
    public function da_sua_thi_o_soan_hien_ban_da_sua(): void
    {
        $this->luu(['gioi-thieu' => 'Bản đã sửa của cửa hàng.']);

        $this->actingAs($this->nguoi())
            ->get(route('admin.page-contents.edit'))
            ->assertOk()
            ->assertSee('Bản đã sửa của cửa hàng.')
            ->assertSee('đang dùng nội dung đã sửa');
    }

    #[Test]
    public function luu_nguyen_ban_viet_san_thi_KHONG_ghi_de(): void
    {
        /*
         * Bấm Lưu mà không đổi chữ nào: nếu ghi chép đè, lần sau bản viết
         * sẵn được cập nhật thì trang đứng yên ở bản cũ.
         */
        $goc = app(TrangNoiDung::class)->banVietSan('chinh-sach-doi-tra');

        $this->luu(['chinh-sach-doi-tra' => str_replace("\n", "\r\n", $goc) . "\r\n"])->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('settings', ['key' => 'trang_noi_dung.chinh-sach-doi-tra', 'value' => $goc]);
        $this->assertNull(Setting::get('trang_noi_dung.chinh-sach-doi-tra'));
    }

    #[Test]
    public function sua_mot_cau_thi_trang_cong_khai_hien_ban_moi(): void
    {
        $moi = str_replace('trong 3–5 ngày', 'trong 2 ngày', app(TrangNoiDung::class)->banVietSan('chinh-sach-doi-tra'));

        $this->luu(['chinh-sach-doi-tra' => $moi])->assertSessionHasNoErrors();

        $this->get(route('shop.pages.show', 'chinh-sach-doi-tra'))
            ->assertOk()
            ->assertSee('trong 2 ngày')
            ->assertDontSee('trong 3–5 ngày')
            ->assertSee('Nhận hàng: kiểm trước khi trả tiền');
    }

    #[Test]
    public function xoa_trang_o_thi_quay_ve_ban_viet_san(): void
    {
        $this->luu(['chinh-sach-doi-tra' => 'Nội dung tạm.']);
        $this->luu(['chinh-sach-doi-tra' => '   ']);

        $this->assertNull(Setting::get('trang_noi_dung.chinh-sach-doi-tra'));
        $this->get(route('shop.pages.show', 'chinh-sach-doi-tra'))->assertOk()->assertSee('Nhận hàng: kiểm trước khi trả tiền');
    }

    /* ================= AN TOÀN ================= */

    #[Test]
    public function HTML_go_vao_KHONG_chay_tren_trang_cong_khai(): void
    {
        $this->luu(['gioi-thieu' => "<script>alert('x')</script>\n\n## <img src=x onerror=alert(1)>\n\n- <b>x</b>\n\nA:: <i>y</i>"]);

        $html = $this->get(route('shop.pages.show', 'gioi-thieu'))->assertOk()->getContent();

        $this->assertStringNotContainsString("<script>alert('x')</script>", $html);
        $this->assertStringNotContainsString('<img src=x', $html);
        $this->assertStringNotContainsString('<b>x</b>', $html);
        $this->assertStringNotContainsString('<i>y</i>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    #[Test]
    public function lien_ket_chi_nhan_dich_an_toan(): void
    {
        $html = $this->html("[a](javascript:alert(1)) [b](//evil.test) [c](/tai-khoan) [d](https://x.test) [e](mailto:a@b.test)");

        $this->assertStringNotContainsString('href="javascript:', $html);
        $this->assertStringNotContainsString('href="//evil.test"', $html);
        $this->assertStringContainsString('href="/tai-khoan"', $html);
        $this->assertStringContainsString('href="https://x.test"', $html);
        $this->assertStringContainsString('href="mailto:a@b.test"', $html);
    }

    #[Test]
    public function lien_ket_khong_mo_duoc_thuoc_tinh_moi(): void
    {
        $html = $this->html('[x](/a"onmouseover="alert(1))');

        $this->assertStringNotContainsString('onmouseover="alert', $html);
    }

    #[Test]
    public function cac_quy_uoc_dung_ra_dung_the(): void
    {
        $html = $this->html("Mở đầu.\n\nĐoạn hai\nvẫn là đoạn hai.\n\n### Nhỏ\n\n1. một\n2. hai\n\n| A | B |\n|---|---|\n| 1 | 2 |");

        $this->assertStringContainsString('<p class="lead">Mở đầu.</p>', $html);
        $this->assertStringContainsString('<p>Đoạn hai vẫn là đoạn hai.</p>', $html);
        $this->assertStringContainsString('<h3>Nhỏ</h3>', $html);
        $this->assertStringContainsString('<ol><li>một</li><li>hai</li></ol>', $html);
        $this->assertStringContainsString('<thead><tr><th>A</th><th>B</th></tr></thead><tbody><tr><td>1</td><td>2</td></tr></tbody>', $html);
    }

    /* ================= GHI VÀ QUYỀN ================= */

    #[Test]
    public function khong_tao_duoc_khoa_cai_dat_la(): void
    {
        $this->luu(['khong-co-trang-nay' => 'x', 'gioi-thieu' => 'Xin chào.']);

        $this->assertDatabaseMissing('settings', ['key' => 'trang_noi_dung.khong-co-trang-nay']);
        $this->assertSame('Xin chào.', Setting::get('trang_noi_dung.gioi-thieu'));
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
