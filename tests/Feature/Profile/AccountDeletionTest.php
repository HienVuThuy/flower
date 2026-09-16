<?php

namespace Tests\Feature\Profile;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Mail\AccountDeletionMail;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\Auth\AccountDeleter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Xoá tài khoản theo yêu cầu của chính chủ. */
class AccountDeletionTest extends TestCase
{
    use RefreshDatabase;

    private function khach(): User
    {
        return User::factory()->create();
    }

    private function lienKet(User $user): string
    {
        return URL::temporarySignedRoute(
            'shop.profile.delete.confirm',
            now()->addMinutes(AccountDeleter::TTL_MINUTES),
            ['user' => $user->id],
        );
    }

    private function duongXoa(User $user): string
    {
        $signed = URL::temporarySignedRoute(
            'shop.profile.delete',
            now()->addMinutes(AccountDeleter::TTL_MINUTES),
            ['user' => $user->id],
        );

        return $signed;
    }

    #[Test]
    public function buoc_mot_chi_gui_thu_va_KHONG_xoa_gi(): void
    {
        Mail::fake();

        $user = $this->khach();

        $this->actingAs($user)
            ->post('/tai-khoan/xoa/yeu-cau')
            ->assertRedirect();

        Mail::assertSent(AccountDeletionMail::class);

        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $this->assertAuthenticatedAs($user);
    }

    #[Test]
    public function thu_gui_toi_dung_email_cua_tai_khoan(): void
    {
        Mail::fake();

        $user = $this->khach();

        $this->actingAs($user)->post('/tai-khoan/xoa/yeu-cau', [
            'email' => 'ke-tan-cong@example.test',
        ]);

        Mail::assertSent(
            AccountDeletionMail::class,
            fn ($mail) => $mail->hasTo($user->email),
        );
    }

    #[Test]
    public function lien_ket_khong_ky_bi_tu_choi(): void
    {
        $user = $this->khach();

        $this->actingAs($user)
            ->get("/tai-khoan/xoa/xac-nhan/{$user->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    #[Test]
    public function lien_ket_het_han_bi_tu_choi(): void
    {
        $user = $this->khach();

        $url = URL::temporarySignedRoute(
            'shop.profile.delete.confirm',
            now()->subMinute(),
            ['user' => $user->id],
        );

        $this->actingAs($user)->get($url)->assertForbidden();
    }

    #[Test]
    public function mo_trang_xac_nhan_KHONG_xoa_gi(): void
    {
        $user = $this->khach();

        $this->actingAs($user)
            ->get($this->lienKet($user))
            ->assertOk()
            ->assertSee(AccountDeleter::CAU_XAC_NHAN);

        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    #[Test]
    public function khong_dung_lien_ket_cua_nguoi_khac(): void
    {
        $nanNhan = $this->khach();
        $keKhac = $this->khach();

        $this->actingAs($keKhac)
            ->get($this->lienKet($nanNhan))
            ->assertRedirect(route('shop.profile.edit'));

        $this->assertDatabaseHas('users', ['id' => $nanNhan->id]);
    }

    #[Test]
    public function go_dung_dong_xac_nhan_thi_tai_khoan_bi_xoa(): void
    {
        $user = $this->khach();

        $this->actingAs($user)
            ->delete($this->duongXoa($user), ['xac_nhan' => AccountDeleter::CAU_XAC_NHAN])
            ->assertRedirect(route('welcome'));

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertGuest();
    }

    #[Test]
    public function go_sai_dong_xac_nhan_thi_khong_xoa(): void
    {
        $user = $this->khach();

        $this->actingAs($user)
            ->delete($this->duongXoa($user), ['xac_nhan' => 'ok'])
            ->assertSessionHasErrors('xac_nhan');

        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    #[Test]
    public function khong_ky_thi_khong_xoa_duoc_du_go_dung(): void
    {
        $user = $this->khach();

        $this->actingAs($user)
            ->delete("/tai-khoan/xoa/{$user->id}", [
                'xac_nhan' => AccountDeleter::CAU_XAC_NHAN,
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    #[Test]
    public function don_hang_o_lai_nhung_mat_chu(): void
    {
        $user = $this->khach();

        $order = Order::create([
            'order_number' => 'FP-XOA-'.strtoupper(bin2hex(random_bytes(3))),
            'user_id' => $user->id,
            'recipient_name' => 'Nguyễn Văn Kiểm Thử',
            'recipient_phone' => '0912345678',
            'shipping_address' => '12 Đường Thử',
            'shipping_province' => 'Thành phố Hà Nội',
            'payment_method' => 'cod',
            'subtotal' => '300000.00',
            'discount_total' => '0.00',
            'shipping_fee' => '25000.00',
            'coupon_discount' => '0.00',
            'grand_total' => '325000.00',
        ]);
        $order->status = OrderStatus::Completed;
        $order->save();

        $this->actingAs($user)
            ->delete($this->duongXoa($user), ['xac_nhan' => AccountDeleter::CAU_XAC_NHAN])
            ->assertRedirect();

        $order->refresh();

        $this->assertNull($order->user_id, 'Đơn phải mất chủ...');
        $this->assertSame('Nguyễn Văn Kiểm Thử', $order->recipient_name,
            '...nhưng thông tin giao hàng đã chụp vào đơn thì vẫn còn, nên đơn vẫn giao được.');
    }

    #[Test]
    public function con_don_dang_do_thi_chua_cho_xoa(): void
    {
        Mail::fake();

        $user = $this->khach();

        $order = Order::create([
            'order_number' => 'FP-DANGDO-'.strtoupper(bin2hex(random_bytes(3))),
            'user_id' => $user->id,
            'recipient_name' => 'Nguyễn Văn Kiểm Thử',
            'recipient_phone' => '0912345678',
            'shipping_address' => '12 Đường Thử',
            'shipping_province' => 'Thành phố Hà Nội',
            'payment_method' => 'cod',
            'subtotal' => '300000.00',
            'discount_total' => '0.00',
            'shipping_fee' => '25000.00',
            'coupon_discount' => '0.00',
            'grand_total' => '325000.00',
        ]);
        $order->status = OrderStatus::Shipping;
        $order->save();

        $this->actingAs($user)->post('/tai-khoan/xoa/yeu-cau')->assertRedirect();

        Mail::assertNothingSent();

        $this->delete($this->duongXoa($user), ['xac_nhan' => AccountDeleter::CAU_XAC_NHAN]);

        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    #[Test]
    public function quan_tri_vien_duy_nhat_khong_tu_xoa_duoc(): void
    {
        $admin = User::factory()->create();
        $admin->role = UserRole::Admin;
        $admin->save();

        $this->actingAs($admin)
            ->delete($this->duongXoa($admin), ['xac_nhan' => AccountDeleter::CAU_XAC_NHAN]);

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    #[Test]
    public function danh_gia_va_dia_chi_di_theo_tai_khoan(): void
    {
        $user = $this->khach();

        $product = Product::factory()->for(Category::factory())->create();

        $user->addresses()->create([
            'recipient_name' => 'Nguyễn Văn Kiểm Thử',
            'recipient_phone' => '0912345678',
            'address_line' => '12 Đường Thử',
            'province' => 'Thành phố Hà Nội',
        ]);

        $user->wishlists()->create(['product_id' => $product->id]);

        $this->actingAs($user)
            ->delete($this->duongXoa($user), ['xac_nhan' => AccountDeleter::CAU_XAC_NHAN]);

        $this->assertDatabaseMissing('addresses', ['user_id' => $user->id]);
        $this->assertDatabaseMissing('wishlists', ['user_id' => $user->id]);
    }

    #[Test]
    public function moi_phien_dang_nhap_deu_bi_huy(): void
    {
        $user = $this->khach();

        \Illuminate\Support\Facades\DB::table('sessions')->insert([
            'id' => 'phien-may-khac',
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Kiểm thử',
            'payload' => base64_encode(serialize([])),
            'last_activity' => now()->timestamp,
        ]);

        $this->actingAs($user)
            ->delete($this->duongXoa($user), ['xac_nhan' => AccountDeleter::CAU_XAC_NHAN]);

        $this->assertDatabaseMissing('sessions', ['id' => 'phien-may-khac']);
    }
}
