<?php

namespace Tests\Feature\Security;

use App\Models\Address;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tài khoản này KHÔNG được chạm vào dữ liệu của tài khoản kia.
 * ============================================================
 * "Tin dữ liệu ID từ request mà không kiểm" là lỗ hổng phổ biến nhất
 * trong ứng dụng web có tài khoản (IDOR). Nó không gây lỗi, không hiện
 * cảnh báo — chỉ là một khách gõ id khác vào URL và đọc được đơn hàng,
 * địa chỉ, số điện thoại của người lạ.
 *
 * MỖI BÀI Ở ĐÂY LÀ MỘT CUỘC TẤN CÔNG THẬT: đăng nhập bằng tài khoản B,
 * gửi id của tài khoản A, rồi kiểm tra dữ liệu của A có suy suyển không.
 */
class OwnershipTest extends TestCase
{
    use RefreshDatabase;

    private function product(): Product
    {
        return Product::factory()->for(Category::factory())->create();
    }

    private function address(User $owner): Address
    {
        return $owner->addresses()->create([
            'recipient_name' => 'Chủ thật',
            'recipient_phone' => '0912345678',
            'address_line' => '1 Đường Của Tôi',
            'province' => 'Thành phố Hồ Chí Minh',
            'label' => 'home',
        ]);
    }

    // ================= SỔ ĐỊA CHỈ =================

    #[Test]
    public function khong_sua_duoc_dia_chi_cua_nguoi_khac(): void
    {
        $nanNhan = User::factory()->create();
        $diaChi = $this->address($nanNhan);

        $this->actingAs(User::factory()->create());

        $this->put('/dia-chi/'.$diaChi->id, [
            'recipient_name' => 'Kẻ tấn công',
            'recipient_phone' => '0900000000',
            'address_line' => 'Địa chỉ bị đổi',
            'province' => 'Thành phố Hà Nội',
            'label' => 'home',
        ]);

        $this->assertSame('Chủ thật', $diaChi->fresh()->recipient_name);
    }

    #[Test]
    public function khong_xoa_duoc_dia_chi_cua_nguoi_khac(): void
    {
        $nanNhan = User::factory()->create();
        $diaChi = $this->address($nanNhan);

        $this->actingAs(User::factory()->create());
        $this->delete('/dia-chi/'.$diaChi->id);

        $this->assertNotNull($diaChi->fresh(), 'Địa chỉ của người khác không được biến mất.');
    }

    #[Test]
    public function khong_doi_duoc_dia_chi_mac_dinh_cua_nguoi_khac(): void
    {
        $nanNhan = User::factory()->create();
        $diaChi = $this->address($nanNhan);

        $this->actingAs(User::factory()->create());
        $this->patch('/dia-chi/'.$diaChi->id.'/mac-dinh');

        $this->assertFalse((bool) $diaChi->fresh()->is_default);
    }

    // ================= GIỎ HÀNG =================

    #[Test]
    public function khong_sua_duoc_dong_gio_hang_cua_nguoi_khac(): void
    {
        $nanNhan = User::factory()->create();
        $cart = Cart::create(['user_id' => $nanNhan->id]);
        $dong = CartItem::create([
            'cart_id' => $cart->id,
            'product_id' => $this->product()->id,
            'quantity' => 1,
        ]);

        $this->actingAs(User::factory()->create());
        $this->patch('/gio-hang/'.$dong->id, ['quantity' => 99]);

        $this->assertSame(1, $dong->fresh()->quantity);
    }

    #[Test]
    public function khong_xoa_duoc_dong_gio_hang_cua_nguoi_khac(): void
    {
        $nanNhan = User::factory()->create();
        $cart = Cart::create(['user_id' => $nanNhan->id]);
        $dong = CartItem::create([
            'cart_id' => $cart->id,
            'product_id' => $this->product()->id,
            'quantity' => 1,
        ]);

        $this->actingAs(User::factory()->create());
        $this->delete('/gio-hang/'.$dong->id);

        $this->assertNotNull($dong->fresh(), 'Dòng giỏ của người khác không được biến mất.');
    }

    // ================= ĐƠN HÀNG =================

    /** Đặt một đơn bằng tài khoản đang đăng nhập, trả về đơn vừa tạo. */
    private function datDon(string $ten = 'Nạn nhân'): \App\Models\Order
    {
        $product = Product::factory()->for(Category::factory())->price('300000.00')->create();

        $this->post('/gio-hang', ['product_id' => $product->id, 'quantity' => 1]);
        $this->post('/thanh-toan', [
            'recipient_name' => $ten,
            'recipient_phone' => '0912345678',
            'recipient_email' => 'nan-nhan@example.com',
            'shipping_address' => '1 Đường Riêng Tư',
            'shipping_province' => 'Thành phố Hồ Chí Minh',
            'payment_method' => 'cod',
            'address_id' => '',
        ]);
        $this->post('/thanh-toan/dat-hang');

        $don = \App\Models\Order::latest('id')->first();
        $this->assertNotNull($don, 'Phải đặt được đơn trước đã.');

        return $don;
    }

    #[Test]
    public function tu_mot_phien_khac_thi_khong_xem_duoc_don_cua_nguoi_la(): void
    {
        // Đơn hàng chứa TÊN, SỐ ĐIỆN THOẠI, ĐỊA CHỈ NHÀ. Đây là dữ liệu
        // cá nhân, không phải "chỉ là số tiền".
        $this->actingAs(User::factory()->create());
        $don = $this->datDon();

        // Trình duyệt khác = phiên khác, không có "vé" nào trong tay.
        $this->flushSession();
        $this->actingAs(User::factory()->create());

        $this->get('/don-hang/'.$don->order_number)->assertForbidden();
    }

    #[Test]
    public function tu_mot_phien_khac_thi_khong_huy_duoc_don_cua_nguoi_la(): void
    {
        $this->actingAs(User::factory()->create());
        $don = $this->datDon();
        $trangThaiCu = $don->status;

        $this->flushSession();
        $this->actingAs(User::factory()->create());

        $this->post('/don-hang/'.$don->order_number.'/huy', ['reason' => 'đổi ý'])
            ->assertForbidden();

        $this->assertSame($trangThaiCu, $don->fresh()->status);
    }

    #[Test]
    public function dang_nhap_xoa_ve_xem_don_cua_khach_vang_lai(): void
    {
        /*
         * LỖ HỔNG ĐÃ ĐO ĐƯỢC — MÁY DÙNG CHUNG.
         *
         *   1. Khách A đặt hàng mà không đăng nhập. Mã đơn được ghi vào
         *      session `checkout.placed` làm "vé" xem lại đơn.
         *   2. A rời máy — khách vãng lai không có nút đăng xuất để bấm.
         *   3. B ngồi xuống, đăng nhập bằng tài khoản của mình.
         *   4. login() chỉ regenerate() — ĐỔI ID PHIÊN NHƯNG GIỮ DỮ LIỆU.
         *      Vé của A còn nguyên trong phiên của B.
         *   5. B mở /don-hang/{mã của A} và đọc được tên, số điện thoại,
         *      địa chỉ nhà của A. Bấm huỷ cũng được.
         *
         * KIỂM TRỰC TIẾP CÁI VÉ, không dựng lại cả phiên khách vãng lai:
         * giỏ của khách chưa đăng nhập nhận diện bằng session()->getId(),
         * mà TestCase của Laravel cấp phiên mới cho mỗi request nên không
         * dựng được luồng nhiều bước đó — xem docs/KIEM-THU.md. Thứ đang
         * canh ở đây là "đăng nhập có xoá vé không", và câu đó trả lời
         * được trọn vẹn mà không cần đặt đơn thật.
         */
        $b = User::factory()->create(['password' => 'MatKhau@12345']);

        $this->withSession([\App\Http\Controllers\Shop\CheckoutController::PLACED_KEY => ['FP-260101-AAAA']])
            ->post('/login', ['email' => $b->email, 'password' => 'MatKhau@12345'])
            ->assertRedirect();

        $this->assertNull(
            session(\App\Http\Controllers\Shop\CheckoutController::PLACED_KEY),
            'Đăng nhập phải xoá vé xem đơn của người vô danh trước đó.',
        );
    }

    // ================= KHU VỰC QUẢN TRỊ =================

    #[Test]
    public function khach_thuong_khong_vao_duoc_khu_vuc_quan_tri(): void
    {
        $this->actingAs(User::factory()->create());

        foreach ([
            '/admin/dashboard',
            '/admin/products',
            '/admin/orders',
            '/admin/users',
            '/admin/settings',
            '/admin/coupons',
        ] as $path) {
            $response = $this->get($path);

            $this->assertContains(
                $response->getStatusCode(),
                [302, 403, 404],
                "Khách thường không được vào {$path} (nhận {$response->getStatusCode()}).",
            );
        }
    }

    #[Test]
    public function khach_thuong_khong_sua_duoc_du_lieu_qua_route_quan_tri(): void
    {
        $product = $this->product();
        $tenCu = $product->name;

        $this->actingAs(User::factory()->create());
        $this->put('/admin/products/'.$product->id, ['name' => 'Bị đổi tên']);

        $this->assertSame($tenCu, $product->fresh()->name);
    }

    #[Test]
    public function khach_vang_lai_khong_vao_duoc_khu_vuc_quan_tri(): void
    {
        $this->get('/admin/dashboard')->assertRedirect('/login');
    }
}
