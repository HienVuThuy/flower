<?php

namespace Tests\Feature\Chat;

use App\Models\Message;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Nhắn tin trực tiếp khách ↔ cửa hàng (lab07). */
class NhanTinTest extends TestCase
{
    use RefreshDatabase;

    private function khach(): User
    {
        return User::factory()->create(['role' => 'customer']);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    #[Test]
    public function khach_gui_tin_va_xem_lai_lich_su(): void
    {
        $khach = $this->khach();

        $this->actingAs($khach)
            ->postJson(route('shop.chat.send'), ['noi_dung' => '  Cây lưỡi hổ tưới mấy ngày một lần?  '])
            ->assertCreated()
            ->assertJsonPath('tin.noi_dung', 'Cây lưỡi hổ tưới mấy ngày một lần?')
            ->assertJsonPath('tin.tu_khach', true);

        $this->actingAs($khach)
            ->getJson(route('shop.chat.messages'))
            ->assertOk()
            ->assertJsonCount(1, 'tin');
    }

    #[Test]
    public function admin_thay_hoi_thoai_kem_so_chua_doc_roi_tra_loi(): void
    {
        $khach = $this->khach();
        $admin = $this->admin();

        $this->actingAs($khach)->postJson(route('shop.chat.send'), ['noi_dung' => 'Tin 1']);
        $this->actingAs($khach)->postJson(route('shop.chat.send'), ['noi_dung' => 'Tin 2']);

        $this->actingAs($admin)
            ->getJson(route('admin.chat.users'))
            ->assertOk()
            ->assertJsonPath('chua_doc', 2)
            ->assertJsonPath('hoi_thoai.0.id', $khach->id)
            ->assertJsonPath('hoi_thoai.0.chua_doc', 2)
            ->assertJsonPath('hoi_thoai.0.tin_cuoi', 'Tin 2');

        $this->actingAs($admin)
            ->getJson(route('admin.chat.messages', $khach))
            ->assertOk()
            ->assertJsonCount(2, 'tin');

        $this->assertSame(0, Message::whereNull('read_at')->count());

        $this->actingAs($admin)
            ->postJson(route('admin.chat.send', $khach), ['noi_dung' => 'Chào bạn, 7–10 ngày một lần nhé.'])
            ->assertCreated()
            ->assertJsonPath('tin.tu_khach', false);

        $this->actingAs($khach)
            ->getJson(route('shop.chat.unread'))
            ->assertJsonPath('chua_doc', 1);
    }

    #[Test]
    public function tra_loi_cua_cua_hang_gop_mot_thong_bao_va_xem_chat_thi_da_doc(): void
    {
        $khach = $this->khach();
        $admin = $this->admin();

        $this->actingAs($admin)->postJson(route('admin.chat.send', $khach), ['noi_dung' => 'Đơn của bạn đã giao cho GHN.']);
        $this->actingAs($admin)->postJson(route('admin.chat.send', $khach), ['noi_dung' => 'Dự kiến giao ngày mai.']);

        $tb = UserNotification::where('user_id', $khach->id)->get();
        $this->assertCount(1, $tb);
        $this->assertSame('Dự kiến giao ngày mai.', $tb->first()->note);
        $this->assertSame(route('shop.chat.index'), $tb->first()->duongDan());

        $this->actingAs($khach)
            ->getJson(route('shop.chat.messages', ['da_xem' => 1]))
            ->assertJsonPath('chua_doc', 0)
            ->assertJsonPath('tin.0.nguoi_gui', \App\Services\Shop\StoreProfile::name());

        $this->assertNotNull($tb->first()->fresh()->read_at);
    }

    #[Test]
    public function chi_tai_tin_moi_hon_moc_da_co(): void
    {
        $khach = $this->khach();

        $this->actingAs($khach)->postJson(route('shop.chat.send'), ['noi_dung' => 'Tin cũ']);
        $cu = Message::latest('id')->first();
        $this->actingAs($khach)->postJson(route('shop.chat.send'), ['noi_dung' => 'Tin mới']);

        $this->actingAs($khach)
            ->getJson(route('shop.chat.messages', ['sau' => $cu->id]))
            ->assertJsonCount(1, 'tin')
            ->assertJsonPath('tin.0.noi_dung', 'Tin mới');
    }

    #[Test]
    public function khong_nhan_tin_rong_hoac_qua_dai(): void
    {
        $khach = $this->khach();

        $this->actingAs($khach)->postJson(route('shop.chat.send'), ['noi_dung' => '   '])->assertUnprocessable();
        $this->actingAs($khach)->postJson(route('shop.chat.send'), ['noi_dung' => str_repeat('a', 1001)])->assertUnprocessable();

        $this->assertSame(0, Message::count());
    }

    #[Test]
    public function noi_dung_bi_thoat_ky_tu_khi_hien_tren_trang(): void
    {
        $khach = $this->khach();

        $this->actingAs($khach)->postJson(route('shop.chat.send'), ['noi_dung' => '<script>alert(1)</script>']);

        $this->actingAs($khach)
            ->get(route('shop.chat.index'))
            ->assertOk()
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    #[Test]
    public function khong_co_javascript_van_gui_duoc_bang_bieu_mau(): void
    {
        $khach = $this->khach();

        $this->actingAs($khach)
            ->post(route('shop.chat.send'), ['noi_dung' => 'Gửi bằng biểu mẫu thường'])
            ->assertRedirect(route('shop.chat.index'));

        $this->actingAs($khach)->get(route('shop.chat.index'))->assertSee('Gửi bằng biểu mẫu thường');
    }

    #[Test]
    public function nhan_vien_co_quyen_ho_tro_khach_hang_thi_khong(): void
    {
        $khach = $this->khach();
        $nhanVien = User::factory()->create(['role' => 'staff']);

        $this->actingAs($nhanVien)->getJson(route('admin.chat.users'))->assertOk();
        $this->actingAs($khach)->getJson(route('admin.chat.users'))->assertForbidden();
        $this->actingAs($khach)->postJson(route('admin.chat.send', $khach), ['noi_dung' => 'x'])->assertForbidden();
    }

    #[Test]
    public function chi_nhan_tin_voi_tai_khoan_khach_hang(): void
    {
        $admin = $this->admin();
        $nhanVien = User::factory()->create(['role' => 'staff']);

        $this->actingAs($admin)->getJson(route('admin.chat.messages', $nhanVien))->assertNotFound();
        $this->actingAs($admin)->postJson(route('admin.chat.send', $nhanVien), ['noi_dung' => 'x'])->assertNotFound();
    }

    #[Test]
    public function khach_chua_dang_nhap_hoac_chua_xac_thuc_bi_chan(): void
    {
        $this->get(route('shop.chat.index'))->assertRedirect(route('login'));

        $chuaXacThuc = User::factory()->unverified()->create(['role' => 'customer']);
        $this->actingAs($chuaXacThuc)->get(route('shop.chat.index'))->assertRedirect(route('verification.notice'));
    }

    #[Test]
    public function khung_chat_noi_co_tab_nhan_cua_hang(): void
    {
        $this->actingAs($this->khach())
            ->get('/')
            ->assertOk()
            ->assertSee('data-chat-tab="shop"', false)
            ->assertSee(route('shop.chat.unread'), false);

        auth()->logout();

        $this->get('/')->assertSee('để nhắn tin với cửa hàng');
    }
}
