<?php

namespace Tests\Feature\Admin;

use App\Enums\ExchangeReason;
use App\Enums\ExchangeStatus;
use App\Enums\OrderStatus;
use App\Enums\ProductType;
use App\Enums\RefundStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Exchange;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Services\Exchange\ExchangeException;
use App\Services\Exchange\ExchangeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Đổi hàng: khách trả món này, nhận món khác.
 * ============================================================
 * CHÍNH SÁCH ĐƯỢC CANH Ở ĐÂY (xem ExchangeService):
 *
 *   1. Hạn 7 ngày kể từ khi đơn chuyển sang "đã giao".
 *   2. Hoa tươi không đổi — cả chiều trả về lẫn chiều gửi đi.
 *   3. Lỗi cửa hàng thì cửa hàng chịu phí ship; khách đổi ý thì khách trả.
 *
 * ============================================================
 * HAI CHỖ DỄ MẤT TIỀN THẬT, và cả hai đều có bài riêng:
 *
 *   - GIÁ HÀNG TRẢ phải là giá KHÁCH ĐÃ TRẢ, không phải giá niêm yết hôm
 *     nay. Lấy nhầm là trả cho khách phần khuyến mại họ chưa từng bỏ ra.
 *   - "CÒN ĐỔI ĐƯỢC" phải trừ CẢ phần đã trả về qua hoàn tiền. Đếm riêng
 *     hai đường thì một dòng 3 cái bị lấy ra 4.
 */
class DoiHangTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->role = UserRole::Admin;
        $u->save();

        return $u;
    }

    private function sanPham(string $ten, string $gia, ProductType $loai = ProductType::Plant, int $ton = 20): Product
    {
        return Product::factory()
            ->for(Category::factory())
            ->price($gia)
            ->stock($ton)
            ->create(['name' => $ten, 'product_type' => $loai]);
    }

    /**
     * Một đơn ĐÃ GIAO, có sẵn dòng hàng — ghi thẳng vào bảng.
     *
     * Đi qua luồng đặt hàng thật thì không đặt được `unit_price` khác giá
     * niêm yết, mà đúng sự chênh lệch đó mới là thứ bài này cần đo.
     */
    private function don(Product $sp, int $soLuong, string $daTraMoiCai, string $phiShip = '30000.00'): Order
    {
        $tien = bcmul($daTraMoiCai, (string) $soLuong, 2);

        $don = new Order();
        $don->forceFill([
            'order_number' => 'KT-' . uniqid(),
            'recipient_name' => 'Khách đổi hàng',
            'recipient_phone' => '0912345678',
            'shipping_address' => '1 Đường Thử',
            'shipping_province' => 'Thành phố Hà Nội',
            'payment_method' => 'cod',
            'subtotal' => $tien,
            'shipping_fee' => $phiShip,
            'grand_total' => bcadd($tien, $phiShip, 2),
            'status' => OrderStatus::Completed,
            // Đơn COD đã giao là đơn đã thu tiền — điều kiện để hoàn được
            // phần chênh lệch khi hàng mới rẻ hơn.
            'payment_status' => \App\Enums\PaymentStatus::Paid,
            'completed_at' => now()->subDay(),
        ])->save();

        $dong = new OrderItem();
        $dong->forceFill([
            'order_id' => $don->id,
            'product_id' => $sp->id,
            'product_name' => $sp->name,
            'unit_base_price' => $daTraMoiCai,
            'unit_price' => $daTraMoiCai,
            'quantity' => $soLuong,
            'line_total' => $tien,
            'discount_amount' => '0.00',
        ])->save();

        return $don->fresh('items');
    }

    private function dichVu(): ExchangeService
    {
        return app(ExchangeService::class);
    }

    /* ================= TIỀN ================= */

    #[Test]
    public function gia_hang_tra_lay_theo_gia_KHACH_DA_TRA_chu_khong_phai_gia_hom_nay(): void
    {
        /*
         * ĐÂY LÀ CHỖ MẤT TIỀN THẬT.
         *
         * Khách mua chậu lúc đang giảm còn 350.000₫, giá niêm yết
         * 500.000₫. Nay đổi sang món khác. Tính hàng trả theo giá niêm yết
         * là trả cho khách 150.000₫ mà họ chưa từng bỏ ra — và cửa hàng
         * mất đúng số đó, mỗi lần đổi.
         */
        $cu = $this->sanPham('Chậu sứ trắng', '500000.00');
        $moi = $this->sanPham('Chậu gốm nâu', '350000.00');

        // Khách đã trả 350.000₫/cái, không phải 500.000₫.
        $don = $this->don($cu, 1, '350000.00');

        $phieu = $this->dichVu()->tao($don, [
            'reason' => ExchangeReason::GiaoSai->value,
            'tra' => [$don->items->first()->id => ['quantity' => 1]],
            'moi' => [['product_id' => $moi->id, 'quantity' => 1]],
        ]);

        $this->assertSame('350000.00', $phieu->tien_hang_tra);
        $this->assertSame('350000.00', $phieu->tien_hang_moi);

        // Đổi ngang: không ai nợ ai.
        $this->assertSame('0.00', $phieu->chenh_lech);
    }

    #[Test]
    public function hang_moi_dat_hon_thi_khach_bu_them(): void
    {
        $cu = $this->sanPham('Chậu nhỏ', '200000.00');
        $moi = $this->sanPham('Chậu lớn', '300000.00');

        $don = $this->don($cu, 1, '200000.00');

        $phieu = $this->dichVu()->tao($don, [
            'reason' => ExchangeReason::GiaoSai->value,
            'tra' => [$don->items->first()->id => ['quantity' => 1]],
            'moi' => [['product_id' => $moi->id, 'quantity' => 1]],
        ]);

        $this->assertSame('100000.00', $phieu->chenh_lech);
        $this->assertSame('100000.00', $phieu->conPhaiThu());
        $this->assertFalse($phieu->cuaHangNoLai());

        // Khách bù thì KHÔNG lập phiếu hoàn tiền.
        $this->assertNull($phieu->refund_id);
    }

    #[Test]
    public function hang_moi_re_hon_thi_lap_luon_chung_tu_hoan_tien(): void
    {
        /*
         * TIỀN CHỈ CÓ MỘT ĐƯỜNG RA.
         *
         * Phiếu đổi không tự trả tiền cho khách — nó lập một chứng từ hoàn
         * tiền và trỏ sang đó. Hoàn tiền đã có bước xác nhận tiền thật sự
         * đi, và đã được trừ khỏi doanh thu thuần. Tự trả ở đây là tạo một
         * đường tiền thứ hai không ai đối chiếu.
         */
        $cu = $this->sanPham('Chậu lớn', '300000.00');
        $moi = $this->sanPham('Chậu nhỏ', '200000.00');

        $don = $this->don($cu, 1, '300000.00');

        $phieu = $this->dichVu()->tao($don, [
            'reason' => ExchangeReason::GiaoSai->value,
            'tra' => [$don->items->first()->id => ['quantity' => 1]],
            'moi' => [['product_id' => $moi->id, 'quantity' => 1]],
        ]);

        $this->assertSame('-100000.00', $phieu->chenh_lech);
        $this->assertTrue($phieu->cuaHangNoLai());
        $this->assertSame('0.00', $phieu->conPhaiThu());

        $this->assertNotNull($phieu->refund, 'Cửa hàng nợ lại mà không có chứng từ hoàn tiền');
        $this->assertSame('100000.00', $phieu->refund->amount);
    }

    #[Test]
    public function khach_doi_y_thi_khach_tra_phi_ship_con_loi_cua_hang_thi_khong(): void
    {
        $cu = $this->sanPham('Chậu A', '200000.00');
        $moi = $this->sanPham('Chậu B', '200000.00');

        foreach ([
            [ExchangeReason::GiaoSai, '0.00', '0.00'],
            [ExchangeReason::HangHong, '0.00', '0.00'],
            [ExchangeReason::KhachDoiY, '30000.00', '30000.00'],
        ] as [$lyDo, $phiMong, $chenhMong]) {
            $don = $this->don($cu, 1, '200000.00', '30000.00');

            $phieu = $this->dichVu()->tao($don, [
                'reason' => $lyDo->value,
                'tra' => [$don->items->first()->id => ['quantity' => 1]],
                'moi' => [['product_id' => $moi->id, 'quantity' => 1]],
            ]);

            $this->assertSame($phiMong, $phieu->phi_ship, 'Sai phí ship với lý do ' . $lyDo->value);
            $this->assertSame($chenhMong, $phieu->chenh_lech);
        }
    }

    #[Test]
    public function khong_thu_qua_so_khach_phai_bu(): void
    {
        $cu = $this->sanPham('Chậu nhỏ', '200000.00');
        $moi = $this->sanPham('Chậu lớn', '300000.00');
        $don = $this->don($cu, 1, '200000.00');

        $phieu = $this->dichVu()->tao($don, [
            'reason' => ExchangeReason::GiaoSai->value,
            'tra' => [$don->items->first()->id => ['quantity' => 1]],
            'moi' => [['product_id' => $moi->id, 'quantity' => 1]],
        ]);

        $this->dichVu()->daNhanHang($phieu, [$phieu->hangTra->first()->id => '1']);

        $this->expectException(ExchangeException::class);
        $this->expectExceptionMessageMatches('/chỉ còn phải bù/iu');

        $this->dichVu()->hoanTat($phieu, 999_999);
    }

    /* ================= KHO ================= */

    #[Test]
    public function hang_moi_bi_giu_ngay_luc_lap_phieu(): void
    {
        /*
         * Không giữ thì giữa lúc hẹn với khách và lúc hàng cũ về, món đó
         * đã bán cho người khác — và cửa hàng phải gọi điện nuốt lời.
         */
        $cu = $this->sanPham('Chậu A', '200000.00');
        $moi = $this->sanPham('Chậu B', '200000.00', ProductType::Plant, 20);
        $don = $this->don($cu, 2, '200000.00');

        $this->dichVu()->tao($don, [
            'reason' => ExchangeReason::GiaoSai->value,
            'tra' => [$don->items->first()->id => ['quantity' => 2]],
            'moi' => [['product_id' => $moi->id, 'quantity' => 2]],
        ]);

        $this->assertSame(18, (int) $moi->fresh()->stock_quantity);
    }

    #[Test]
    public function hang_cu_chi_vao_kho_khi_da_nhan_VA_con_ban_duoc(): void
    {
        /*
         * Chậu vỡ khách gửi về không phải hàng tồn. Cộng bừa vào kho thì
         * trang Tồn kho nói cửa hàng còn hàng, khách đặt, và cửa hàng lại
         * phải gọi điện xin lỗi.
         */
        $cu = $this->sanPham('Chậu A', '200000.00', ProductType::Plant, 5);
        $moi = $this->sanPham('Chậu B', '200000.00');
        $don = $this->don($cu, 2, '200000.00');

        $phieu = $this->dichVu()->tao($don, [
            'reason' => ExchangeReason::HangHong->value,
            'tra' => [$don->items->first()->id => ['quantity' => 2]],
            'moi' => [['product_id' => $moi->id, 'quantity' => 2]],
        ]);

        // Chưa nhận: kho hàng cũ chưa đổi.
        $this->assertSame(5, (int) $cu->fresh()->stock_quantity);

        // Nhận về nhưng KHÔNG đánh dấu bán lại được.
        $this->dichVu()->daNhanHang($phieu, []);

        $this->assertSame(5, (int) $cu->fresh()->stock_quantity, 'Hàng hỏng không được vào kho');
        $this->assertSame(ExchangeStatus::DaNhan, $phieu->fresh()->status);
    }

    #[Test]
    public function danh_dau_con_ban_duoc_thi_cong_lai_kho(): void
    {
        $cu = $this->sanPham('Chậu A', '200000.00', ProductType::Plant, 5);
        $moi = $this->sanPham('Chậu B', '200000.00');
        $don = $this->don($cu, 2, '200000.00');

        $phieu = $this->dichVu()->tao($don, [
            'reason' => ExchangeReason::KhachDoiY->value,
            'tra' => [$don->items->first()->id => ['quantity' => 2]],
            'moi' => [['product_id' => $moi->id, 'quantity' => 2]],
        ]);

        $this->dichVu()->daNhanHang($phieu, [$phieu->hangTra->first()->id => '1']);

        $this->assertSame(7, (int) $cu->fresh()->stock_quantity);
    }

    #[Test]
    public function huy_phieu_thi_nha_lai_hang_dang_giu(): void
    {
        $cu = $this->sanPham('Chậu A', '200000.00');
        $moi = $this->sanPham('Chậu B', '200000.00', ProductType::Plant, 20);
        $don = $this->don($cu, 2, '200000.00');

        $phieu = $this->dichVu()->tao($don, [
            'reason' => ExchangeReason::GiaoSai->value,
            'tra' => [$don->items->first()->id => ['quantity' => 2]],
            'moi' => [['product_id' => $moi->id, 'quantity' => 2]],
        ]);

        $this->assertSame(18, (int) $moi->fresh()->stock_quantity);

        $this->dichVu()->huy($phieu, 'Khách đổi ý lần nữa');

        $this->assertSame(20, (int) $moi->fresh()->stock_quantity, 'Huỷ rồi mà vẫn giữ hàng');
        $this->assertSame(ExchangeStatus::Huy, $phieu->fresh()->status);
    }

    #[Test]
    public function huy_SAU_KHI_da_nhan_hang_thi_tru_lai_so_da_cong_vao_kho(): void
    {
        /*
         * Huỷ sau khi đã nhận nghĩa là hàng đó quay lại cho khách. Bỏ qua
         * bước trừ ra thì kho thừa đúng số hàng đã trả về, và sai lệch chỉ
         * lộ ra ở lần kiểm kê sau — lúc không ai còn nhớ vì sao.
         */
        $cu = $this->sanPham('Chậu A', '200000.00', ProductType::Plant, 5);
        $moi = $this->sanPham('Chậu B', '200000.00', ProductType::Plant, 20);
        $don = $this->don($cu, 2, '200000.00');

        $phieu = $this->dichVu()->tao($don, [
            'reason' => ExchangeReason::GiaoSai->value,
            'tra' => [$don->items->first()->id => ['quantity' => 2]],
            'moi' => [['product_id' => $moi->id, 'quantity' => 2]],
        ]);

        $this->dichVu()->daNhanHang($phieu, [$phieu->hangTra->first()->id => '1']);
        $this->assertSame(7, (int) $cu->fresh()->stock_quantity);

        $this->dichVu()->huy($phieu, 'Nhầm phiếu');

        $this->assertSame(5, (int) $cu->fresh()->stock_quantity);
        $this->assertSame(20, (int) $moi->fresh()->stock_quantity);
    }

    /* ================= CHÍNH SÁCH ================= */

    #[Test]
    public function hoa_tuoi_khong_doi_duoc_ca_hai_chieu(): void
    {
        $hoa = $this->sanPham('Bó hồng đỏ', '300000.00', ProductType::Flower);
        $cay = $this->sanPham('Cây kim tiền', '300000.00', ProductType::Plant);

        // Chiều trả về.
        $donHoa = $this->don($hoa, 1, '300000.00');

        $this->assertNotNull($this->dichVu()->lyDoKhongDoiDuoc($donHoa));
        $this->assertStringContainsString(
            'Hoa tươi',
            (string) $this->dichVu()->lyDoDongKhongDoiDuoc($donHoa->items->first()),
        );

        // Chiều gửi đi.
        $donCay = $this->don($cay, 1, '300000.00');

        $this->expectException(ExchangeException::class);
        $this->expectExceptionMessageMatches('/hoa tươi/iu');

        $this->dichVu()->tao($donCay, [
            'reason' => ExchangeReason::GiaoSai->value,
            'tra' => [$donCay->items->first()->id => ['quantity' => 1]],
            'moi' => [['product_id' => $hoa->id, 'quantity' => 1]],
        ]);
    }

    #[Test]
    public function qua_han_7_ngay_thi_khong_doi_duoc(): void
    {
        $cu = $this->sanPham('Chậu A', '200000.00');
        $don = $this->don($cu, 1, '200000.00');

        // Đúng ngày thứ 7 vẫn còn đổi được.
        $don->forceFill(['completed_at' => now()->subDays(7)->addHour()])->save();
        $this->assertNull($this->dichVu()->lyDoKhongDoiDuoc($don->fresh('items')));

        // Sang ngày thứ 8 thì hết.
        $don->forceFill(['completed_at' => now()->subDays(8)])->save();
        $this->assertStringContainsString(
            'quá hạn',
            (string) $this->dichVu()->lyDoKhongDoiDuoc($don->fresh('items')),
        );
    }

    #[Test]
    public function don_chua_giao_thi_khong_doi_duoc(): void
    {
        $cu = $this->sanPham('Chậu A', '200000.00');
        $don = $this->don($cu, 1, '200000.00');

        $don->forceFill(['status' => OrderStatus::Preparing])->save();

        $this->assertStringContainsString(
            'đã giao',
            (string) $this->dichVu()->lyDoKhongDoiDuoc($don->fresh('items')),
        );
    }

    /* ================= KHÔNG LẤY RA QUÁ SỐ ĐÃ MUA ================= */

    #[Test]
    public function con_doi_duoc_tru_ca_phan_da_tra_qua_hoan_tien(): void
    {
        /*
         * Hai đường đều lấy hàng ra khỏi đơn. Đếm riêng thì khách trả 2
         * cái qua hoàn tiền rồi đổi tiếp 2 cái nữa của một dòng chỉ có 3.
         */
        $cu = $this->sanPham('Chậu A', '200000.00');
        $moi = $this->sanPham('Chậu B', '200000.00');
        $don = $this->don($cu, 3, '200000.00');

        $dong = $don->items->first();

        $this->assertSame(3, $this->dichVu()->conDoiDuoc($dong));

        // Giả lập: 2 cái đã trả về qua một phiếu hoàn tiền.
        $refund = \App\Models\Refund::create([
            'order_id' => $don->id,
            'code' => 'HT-TEST-1',
            'amount' => '400000.00',
            'reason' => \App\Enums\RefundReason::Returned->value,
            'method' => \App\Enums\RefundMethod::Cash->value,
        ]);
        $refund->forceFill(['status' => RefundStatus::Completed])->save();
        $refund->items()->create(['order_item_id' => $dong->id, 'quantity' => 2, 'restock' => false]);

        $this->assertSame(1, $this->dichVu()->conDoiDuoc($dong->fresh()));

        $this->expectException(ExchangeException::class);
        $this->expectExceptionMessageMatches('/chỉ còn đổi được 1/iu');

        $this->dichVu()->tao($don->fresh('items'), [
            'reason' => ExchangeReason::GiaoSai->value,
            'tra' => [$dong->id => ['quantity' => 2]],
            'moi' => [['product_id' => $moi->id, 'quantity' => 2]],
        ]);
    }

    #[Test]
    public function phieu_da_huy_khong_con_chiem_cho(): void
    {
        $cu = $this->sanPham('Chậu A', '200000.00');
        $moi = $this->sanPham('Chậu B', '200000.00');
        $don = $this->don($cu, 2, '200000.00');
        $dong = $don->items->first();

        $phieu = $this->dichVu()->tao($don, [
            'reason' => ExchangeReason::GiaoSai->value,
            'tra' => [$dong->id => ['quantity' => 2]],
            'moi' => [['product_id' => $moi->id, 'quantity' => 2]],
        ]);

        $this->assertSame(0, $this->dichVu()->conDoiDuoc($dong->fresh()));

        $this->dichVu()->huy($phieu, 'Lập nhầm');

        $this->assertSame(2, $this->dichVu()->conDoiDuoc($dong->fresh()));
    }

    /* ================= ĐI QUA GIAO DIỆN ================= */

    #[Test]
    public function lap_phieu_va_mo_trang_phieu_qua_giao_dien(): void
    {
        $cu = $this->sanPham('Chậu A', '200000.00');
        $moi = $this->sanPham('Chậu B', '250000.00');
        $don = $this->don($cu, 1, '200000.00');

        $admin = $this->admin();

        $this->actingAs($admin)
            ->post('/admin/orders/' . $don->order_number . '/doi-hang', [
                'reason' => ExchangeReason::GiaoSai->value,
                'tra' => [$don->items->first()->id => ['quantity' => 1]],
                'moi' => [['product_id' => $moi->id, 'quantity' => 1]],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $phieu = Exchange::firstOrFail();

        $html = $this->actingAs($admin)
            ->get('/admin/doi-hang/' . $phieu->id)
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString($phieu->code, $html);
        $this->assertStringContainsString('Chậu A', $html);
        $this->assertStringContainsString('Chậu B', $html);

        // Danh sách phiếu cũng mở được.
        $this->actingAs($admin)->get('/admin/doi-hang')->assertOk();

        // Và trang đơn hiện phiếu vừa lập.
        $this->actingAs($admin)
            ->get('/admin/orders/' . $don->order_number)
            ->assertOk()
            ->assertSee($phieu->code);
    }

    #[Test]
    public function trang_don_noi_ro_vi_sao_khong_doi_duoc(): void
    {
        /*
         * Người đứng ở quầy đang có khách trước mặt. "Không thấy nút đâu"
         * bắt họ đi hỏi người khác; câu giải thích là thứ họ đọc thẳng cho
         * khách nghe.
         */
        $hoa = $this->sanPham('Bó hồng đỏ', '300000.00', ProductType::Flower);
        $don = $this->don($hoa, 1, '300000.00');

        $this->actingAs($this->admin())
            ->get('/admin/orders/' . $don->order_number)
            ->assertOk()
            ->assertSee('Hoa tươi không đổi được', escape: false);
    }

    #[Test]
    public function phieu_khong_sua_va_khong_xoa_duoc(): void
    {
        /*
         * Phiếu đổi là CHỨNG TỪ: sai thì huỷ và lập phiếu khác, không sửa
         * lại lịch sử. Không có đường dẫn nào cho việc đó — bài này canh
         * để sau này không ai thêm vào mà quên mất nguyên tắc.
         */
        $duong = collect(\Illuminate\Support\Facades\Route::getRoutes())
            ->filter(fn ($r) => str_starts_with($r->uri(), 'admin/doi-hang'))
            ->map(fn ($r) => implode('|', $r->methods()) . ' ' . $r->uri())
            ->values()
            ->all();

        foreach ($duong as $d) {
            $this->assertStringNotContainsString('DELETE', $d, 'Có đường dẫn xoá phiếu đổi: ' . $d);
            $this->assertStringNotContainsString('/edit', $d, 'Có trang sửa phiếu đổi: ' . $d);
        }
    }

    /* ================= DOANH THU ================= */

    #[Test]
    public function tien_khach_bu_khong_bi_bo_quen_khoi_so_sach(): void
    {
        /*
         * Khách bù thêm là TIỀN VÀO. Ghi nó ở phiếu mà không ai cộng vào
         * doanh thu thì cửa hàng thu tiền thật mà sổ không thấy — và
         * ngược lại, cộng hai lần thì doanh thu tự phồng lên.
         *
         * Bài này canh con số nằm đúng chỗ đã khai; phần cộng vào doanh
         * thu thuần do AnalyticsService lo (xem bài của nó).
         */
        $cu = $this->sanPham('Chậu nhỏ', '200000.00');
        $moi = $this->sanPham('Chậu lớn', '300000.00');
        $don = $this->don($cu, 1, '200000.00');

        $phieu = $this->dichVu()->tao($don, [
            'reason' => ExchangeReason::GiaoSai->value,
            'tra' => [$don->items->first()->id => ['quantity' => 1]],
            'moi' => [['product_id' => $moi->id, 'quantity' => 1]],
        ]);

        $this->dichVu()->daNhanHang($phieu, []);
        $this->dichVu()->hoanTat($phieu, 100_000);

        $phieu->refresh();

        $this->assertSame('100000.00', $phieu->da_thu);
        $this->assertSame('0.00', $phieu->conPhaiThu());
        $this->assertSame(ExchangeStatus::HoanTat, $phieu->status);

        // So bằng số: SQLite và MySQL trả về kiểu khác nhau cho cột decimal.
        $this->assertEquals(
            100000,
            DB::table('exchanges')->where('id', $phieu->id)->value('da_thu'),
        );
    }

    #[Test]
    public function tien_khach_bu_duoc_cong_vao_doanh_thu_thuan(): void
    {
        /*
         * Khoản khách bù KHÔNG nằm trong `grand_total` của đơn — đơn đã
         * chốt từ trước. Bỏ qua nó là cửa hàng thu tiền thật mà sổ không
         * thấy; cộng nhầm cả chiều ngược lại là trừ hai lần.
         */
        $cu = $this->sanPham('Chậu nhỏ', '200000.00');
        $moi = $this->sanPham('Chậu lớn', '300000.00');
        $don = $this->don($cu, 1, '200000.00');

        $a = app(\App\Services\Analytics\AnalyticsService::class)->forPeriod('30');
        $truoc = $a->orderStats();

        $phieu = $this->dichVu()->tao($don, [
            'reason' => ExchangeReason::GiaoSai->value,
            'tra' => [$don->items->first()->id => ['quantity' => 1]],
            'moi' => [['product_id' => $moi->id, 'quantity' => 1]],
        ]);

        // Chưa hoàn tất thì chưa tính — tiền chưa thu.
        $giua = app(\App\Services\Analytics\AnalyticsService::class)->forPeriod('30')->orderStats();
        $this->assertSame($truoc['net_revenue'], $giua['net_revenue']);

        $this->dichVu()->daNhanHang($phieu, []);
        $this->dichVu()->hoanTat($phieu, 100_000);

        $sau = app(\App\Services\Analytics\AnalyticsService::class)->forPeriod('30')->orderStats();

        $this->assertEqualsWithDelta(100000, $sau['bu_doi_hang'], 0.01);
        $this->assertEqualsWithDelta($truoc['net_revenue'] + 100000, $sau['net_revenue'], 0.01);

        // Doanh thu gộp KHÔNG đổi: đơn gốc vẫn là đơn gốc.
        $this->assertEqualsWithDelta($truoc['revenue'], $sau['revenue'], 0.01);
    }

    #[Test]
    public function cua_hang_tra_lai_thi_KHONG_bi_tru_hai_lan(): void
    {
        /*
         * Chiều ngược lại đã có chứng từ hoàn tiền lo, và `refunded` đã
         * trừ nó. Cộng thêm một lần nữa ở `bu_doi_hang` là trừ hai lần
         * cho cùng một khoản.
         */
        $cu = $this->sanPham('Chậu lớn', '300000.00');
        $moi = $this->sanPham('Chậu nhỏ', '200000.00');
        $don = $this->don($cu, 1, '300000.00');

        $phieu = $this->dichVu()->tao($don, [
            'reason' => ExchangeReason::GiaoSai->value,
            'tra' => [$don->items->first()->id => ['quantity' => 1]],
            'moi' => [['product_id' => $moi->id, 'quantity' => 1]],
        ]);

        $this->dichVu()->daNhanHang($phieu, []);
        $this->dichVu()->hoanTat($phieu, 0);

        $so = app(\App\Services\Analytics\AnalyticsService::class)->forPeriod('30')->orderStats();

        $this->assertEqualsWithDelta(0, $so['bu_doi_hang'], 0.01);
        $this->assertEqualsWithDelta(100000, $so['refunded'], 0.01);
    }

    #[Test]
    public function chi_phieu_HOAN_TAT_moi_duoc_tinh_vao_doanh_thu(): void
    {
        /*
         * BÀI NÀY SINH RA TỪ MỘT PHÉP ĐỘT BIẾN SỐNG SÓT.
         *
         * Bỏ bộ lọc `status = hoàn tất` mà mọi bài vẫn xanh — vì hôm nay
         * `da_thu` chỉ được đặt đúng lúc hoàn tất, nên phiếu chưa xong
         * luôn có 0. Bộ lọc đang đúng nhưng CHƯA TỪNG ĐƯỢC ĐO.
         *
         * Nó sẽ gánh việc thật vào ngày có người thêm "thu trước một
         * phần": lúc đó tiền của một phiếu chưa xong, thậm chí một phiếu
         * bị huỷ, sẽ chảy thẳng vào doanh thu nếu không có bộ lọc.
         *
         * Ghi thẳng trạng thái vào bảng chứ không đi qua dịch vụ: đúng cái
         * trạng thái đó hôm nay dịch vụ không tạo ra được, mà luật thì vẫn
         * phải đúng từ trước khi nó tạo ra được.
         */
        $cu = $this->sanPham('Chậu nhỏ', '200000.00');
        $moi = $this->sanPham('Chậu lớn', '300000.00');
        $don = $this->don($cu, 1, '200000.00');

        $phieu = $this->dichVu()->tao($don, [
            'reason' => ExchangeReason::GiaoSai->value,
            'tra' => [$don->items->first()->id => ['quantity' => 1]],
            'moi' => [['product_id' => $moi->id, 'quantity' => 1]],
        ]);

        foreach ([ExchangeStatus::ChoNhan, ExchangeStatus::DaNhan, ExchangeStatus::Huy] as $tt) {
            DB::table('exchanges')->where('id', $phieu->id)->update([
                'status' => $tt->value,
                'da_thu' => '100000.00',
            ]);

            $so = app(\App\Services\Analytics\AnalyticsService::class)->forPeriod('30')->orderStats();

            $this->assertEqualsWithDelta(
                0,
                $so['bu_doi_hang'],
                0.01,
                'Phiếu ở trạng thái "' . $tt->label() . '" không được tính vào doanh thu',
            );
        }

        // Và khi hoàn tất thì mới tính.
        DB::table('exchanges')->where('id', $phieu->id)->update([
            'status' => ExchangeStatus::HoanTat->value,
            'da_thu' => '100000.00',
        ]);

        $so = app(\App\Services\Analytics\AnalyticsService::class)->forPeriod('30')->orderStats();

        $this->assertEqualsWithDelta(100000, $so['bu_doi_hang'], 0.01);
    }
}
