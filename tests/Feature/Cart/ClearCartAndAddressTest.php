<?php

namespace Tests\Feature\Cart;

use App\Models\Address;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Dọn giỏ hàng và dọn sổ địa chỉ. */
class ClearCartAndAddressTest extends TestCase
{
    use RefreshDatabase;

    private function hang(string $ten = 'Cây thử'): Product
    {
        return Product::factory()
            ->for(Category::factory()->state(['kind' => 'plant', 'is_active' => true]))
            ->price('150000.00')
            ->stock(20)
            ->create(['name' => $ten, 'weight' => 500]);
    }

    private function diaChi(User $user, string $ten = 'Người nhận'): Address
    {
        return Address::create([
            'user_id' => $user->id,
            'recipient_name' => $ten,
            'recipient_phone' => '0987654321',
            'address_line' => '99 Đường Trong Sổ',
            'province' => 'Thành phố Hà Nội',
            'label' => 'home',
        ]);
    }

    #[Test]
    public function xoa_tat_ca_lam_gio_trong_han(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post('/gio-hang', ['product_id' => $this->hang('Cây A')->id, 'quantity' => 1]);
        $this->post('/gio-hang', ['product_id' => $this->hang('Cây B')->id, 'quantity' => 2]);

        $this->assertSame(2, CartItem::count());

        $this->delete('/gio-hang/tat-ca')
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(0, CartItem::count());
    }

    #[Test]
    public function xoa_tat_ca_xoa_ca_mon_dang_BO_TICH(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $deDanh = $this->hang('Cây để dành');
        $this->post('/gio-hang', ['product_id' => $deDanh->id, 'quantity' => 1]);
        $this->post('/gio-hang', ['product_id' => $this->hang('Cây mua ngay')->id, 'quantity' => 1]);

        CartItem::where('product_id', $deDanh->id)->update(['is_selected' => false]);

        $this->delete('/gio-hang/tat-ca');

        $this->assertSame(0, CartItem::count());
    }

    #[Test]
    public function nut_xoa_tat_ca_hien_o_trang_gio_hang(): void
    {
        $this->actingAs(User::factory()->create());
        $this->post('/gio-hang', ['product_id' => $this->hang()->id, 'quantity' => 1]);

        $this->get('/gio-hang')
            ->assertOk()
            ->assertSee('Xoá tất cả')
            ->assertSee('không hoàn tác được', escape: false);
    }

    #[Test]
    public function gio_trong_thi_xoa_tat_ca_khong_no_loi(): void
    {
        $this->actingAs(User::factory()->create());

        $this->delete('/gio-hang/tat-ca')
            ->assertRedirect()
            ->assertSessionHas('success', 'Giỏ hàng đang trống.');
    }

    #[Test]
    public function khong_xoa_duoc_gio_cua_nguoi_khac(): void
    {
        $mot = User::factory()->create();
        $this->actingAs($mot);
        $this->post('/gio-hang', ['product_id' => $this->hang()->id, 'quantity' => 1]);

        $this->assertSame(1, CartItem::count());

        $this->flushSession();
        $this->actingAs(User::factory()->create());
        $this->delete('/gio-hang/tat-ca');

        $this->assertSame(1, CartItem::count(), 'Đã xoá nhầm giỏ của người khác.');
    }

    #[Test]
    public function buoc_thanh_toan_co_nut_bo_dia_chi_da_luu(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $diaChi = $this->diaChi($user, 'Rin');
        $this->post('/gio-hang', ['product_id' => $this->hang()->id, 'quantity' => 1]);

        $this->get('/thanh-toan')
            ->assertOk()
            ->assertSee('Xoá địa chỉ của Rin')
            ->assertSee('form="xoa-dia-chi-' . $diaChi->id . '"', escape: false);
    }

    #[Test]
    public function bo_dia_chi_o_buoc_thanh_toan_thi_quay_lai_dung_do(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $diaChi = $this->diaChi($user);
        $this->post('/gio-hang', ['product_id' => $this->hang()->id, 'quantity' => 1]);

        $this->from('/thanh-toan')
            ->delete('/dia-chi/' . $diaChi->id)
            ->assertRedirect('/thanh-toan');

        $this->assertSame(0, Address::count());
    }

    #[Test]
    public function bo_dia_chi_tu_so_dia_chi_van_ve_so_dia_chi(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $diaChi = $this->diaChi($user);

        $this->from('/dia-chi')
            ->delete('/dia-chi/' . $diaChi->id)
            ->assertRedirect('/dia-chi');
    }

    #[Test]
    public function khong_bo_duoc_dia_chi_cua_nguoi_khac(): void
    {
        $chuNhan = User::factory()->create();
        $diaChi = $this->diaChi($chuNhan);

        $this->actingAs(User::factory()->create())
            ->delete('/dia-chi/' . $diaChi->id)
            ->assertForbidden();

        $this->assertSame(1, Address::count());
    }
}
