<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Product;
use App\Models\TaxClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Admin tự chỉnh được nhóm thuế suất.
 * ============================================================
 * VÌ SAO PHẢI SỬA ĐƯỢC TỪ GIAO DIỆN: mức thuế đổi theo chính sách từng
 * thời kỳ. Nếu chỉ sửa được trong mã nguồn thì mỗi lần Nhà nước đổi mức,
 * cửa hàng phải đi tìm lập trình viên — và trong lúc chờ, mọi đơn ghi
 * sai số thuế.
 *
 * ============================================================
 * CÁI BẪY CHÍNH CẢ TỆP NÀY CANH: Ô TRỐNG NGHĨA LÀ GÌ.
 *
 * Trên cùng một trang Cấu hình có hai ô thuế suất mang hai nghĩa khác
 * nhau khi để trống:
 *
 *     "Thuế suất" (mức cửa hàng)  trống = dùng mặc định trong config
 *     bảng nhóm thuế              trống = KHÔNG thuộc diện chịu VAT
 *
 * Và "không chịu VAT" lại khác "chịu thuế suất 0%". Ba khái niệm dễ lẫn,
 * hậu quả chỉ lộ ra ở kỳ quyết toán — nên mỗi cái có một bài riêng.
 */
class TaxClassAdminTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->role = UserRole::Admin;
        $user->save();

        return $user;
    }

    private function nhom(string $code, ?string $rate = '0.08000'): TaxClass
    {
        return TaxClass::create([
            'code' => $code,
            'name' => 'Nhóm ' . $code,
            'rate' => $rate,
            'is_active' => true,
        ]);
    }

    /** Bộ dữ liệu tối thiểu để trang Cấu hình chấp nhận lưu. */
    private function form(array $them = []): array
    {
        return array_merge([
            'theme' => 'default',
            'site_name' => 'Angevil',
            'site_hotline' => '0912345678',
            'site_email' => 'anaorin229@gmail.com',
            'site_address' => '41A Phú Diễn',
            'site_province' => 'Thành phố Hà Nội',
            'currency_code' => 'VND',
            'currency_symbol' => '₫',
            'currency_position' => 'after',
            'currency_decimals' => 0,
            'tax_rate_percent' => 8,
        ], $them);
    }

    /* ================= NHẬP PHẦN TRĂM, LƯU THẬP PHÂN ================= */

    #[Test]
    public function admin_doi_duoc_muc_cua_mot_nhom(): void
    {
        /*
         * Kế toán nói "8%", không nói "0,08". Bắt admin tự quy đổi là mời
         * một lỗi gấp 100 lần vào đúng con số thuế — và nó nằm im tới kỳ
         * quyết toán.
         */
        $nhom = $this->nhom('vat_10', '0.10000');

        $this->actingAs($this->admin())
            ->put('/admin/settings', $this->form([
                'tax_classes' => [$nhom->id => ['rate_percent' => '8', 'is_active' => '1']],
            ]))
            ->assertRedirect();

        $this->assertSame('0.08000', $nhom->fresh()->rate);
    }

    #[Test]
    public function o_trong_nghia_la_KHONG_CHIU_VAT(): void
    {
        $nhom = $this->nhom('vat_exempt', '0.10000');

        $this->actingAs($this->admin())
            ->put('/admin/settings', $this->form([
                'tax_classes' => [$nhom->id => ['rate_percent' => '', 'is_active' => '1']],
            ]));

        $this->assertNull($nhom->fresh()->rate);
        $this->assertTrue($nhom->fresh()->isExempt());
    }

    #[Test]
    public function so_0_nghia_la_CHIU_THUE_SUAT_0_chu_khong_phai_mien_thue(): void
    {
        /*
         * VẾ CÒN LẠI CỦA BÀI TRÊN, và là vế dễ mất nhất.
         *
         * Hàng chịu 0% vẫn là hàng chịu thuế: nó vẫn lên hoá đơn với một
         * dòng thuế suất 0%. Biến 0 thành NULL là làm mất phân biệt đó,
         * và không có gì báo.
         */
        $nhom = $this->nhom('vat_0', '0.10000');

        $this->actingAs($this->admin())
            ->put('/admin/settings', $this->form([
                'tax_classes' => [$nhom->id => ['rate_percent' => '0', 'is_active' => '1']],
            ]));

        $this->assertSame('0.00000', $nhom->fresh()->rate);
        $this->assertFalse($nhom->fresh()->isExempt());
    }

    /* ================= TẮT / BẬT ================= */

    #[Test]
    public function bo_tich_thi_nhom_bi_tat(): void
    {
        /*
         * Trình duyệt KHÔNG gửi checkbox chưa tích. Không có ô ẩn đi kèm
         * thì bỏ tích một nhóm sẽ không lưu được: ô biến mất khỏi dữ liệu
         * gửi lên và máy chủ hiểu là "không đổi". Admin bấm Lưu, trang
         * tải lại, ô vẫn tích — và họ tưởng nút hỏng.
         */
        $nhom = $this->nhom('vat_5', '0.05000');

        $this->actingAs($this->admin())
            ->put('/admin/settings', $this->form([
                'tax_classes' => [$nhom->id => ['rate_percent' => '5', 'is_active' => '0']],
            ]));

        $this->assertFalse($nhom->fresh()->is_active);
    }

    #[Test]
    public function trang_cau_hinh_van_liet_ke_nhom_da_tat(): void
    {
        /*
         * Đây là chỗ DUY NHẤT bật lại được một nhóm đã tắt. Lọc mất nó
         * thì tắt nhầm là mất hẳn, không có đường quay lại.
         */
        $nhom = $this->nhom('vat_5', '0.05000');
        $nhom->update(['is_active' => false]);

        $this->actingAs($this->admin())
            ->get('/admin/settings')
            ->assertOk()
            ->assertSee($nhom->name);
    }

    #[Test]
    public function o_danh_dau_co_o_an_di_kem_de_bo_tich_luu_duoc(): void
    {
        /*
         * ĐO TRÊN HTML THẬT, không đo bằng dữ liệu tự gửi.
         *
         * Bài "bỏ tích thì nhóm bị tắt" ở trên gửi thẳng
         * `is_active => '0'`, nên nó xanh kể cả khi ô ẩn bị xoá khỏi
         * biểu mẫu — đã kiểm bằng cách xoá. Nhưng TRÌNH DUYỆT không gửi
         * checkbox chưa tích: không có ô ẩn thì admin bỏ tích, bấm Lưu,
         * trang tải lại và ô vẫn tích.
         *
         * Chỉ có phép đo trên chính HTML mới bắt được cách hỏng đó.
         */
        $nhom = $this->nhom('vat_10', '0.10000');

        $this->actingAs($this->admin())
            ->get('/admin/settings')
            ->assertOk()
            ->assertSee(
                '<input type="hidden" name="tax_classes[' . $nhom->id . '][is_active]" value="0">',
                escape: false,
            );
    }

    /* ================= AN TOÀN ================= */

    #[Test]
    public function bieu_mau_gui_thieu_KHONG_xoa_sach_cau_hinh_thue(): void
    {
        /*
         * Duyệt toàn bộ bảng rồi lấy giá trị từ dữ liệu gửi lên sẽ biến
         * mọi nhóm VẮNG MẶT thành "không chịu VAT, đang tắt". Khi ấy một
         * lần lưu cấu hình bình thường (đổi tên cửa hàng chẳng hạn) xoá
         * sạch cấu hình thuế — im lặng, không lỗi nào.
         */
        $giu = $this->nhom('vat_10', '0.10000');
        $gui = $this->nhom('vat_8', '0.08000');

        $this->actingAs($this->admin())
            ->put('/admin/settings', $this->form([
                'tax_classes' => [$gui->id => ['rate_percent' => '8', 'is_active' => '1']],
            ]));

        $this->assertSame('0.10000', $giu->fresh()->rate, 'Nhóm không gửi lên đã bị sửa.');
        $this->assertTrue($giu->fresh()->is_active);
    }

    #[Test]
    public function id_nhom_thue_bia_tren_bieu_mau_bi_bo_qua(): void
    {
        // Không được tin id từ biểu mẫu. Một id không có thật chỉ đơn
        // giản không tìm thấy và bị bỏ qua, không được nổ ra lỗi 500.
        $this->actingAs($this->admin())
            ->put('/admin/settings', $this->form([
                'tax_classes' => [999999 => ['rate_percent' => '99', 'is_active' => '1']],
            ]))
            ->assertRedirect();

        $this->assertSame(0, TaxClass::count());
    }

    /* ================= GÁN NHÓM CHO SẢN PHẨM ================= */

    #[Test]
    public function form_san_pham_moi_chon_nhom_thue(): void
    {
        $nhom = $this->nhom('vat_10', '0.10000');

        $this->actingAs($this->admin())
            ->get('/admin/products/create')
            ->assertOk()
            ->assertSee($nhom->name)
            // Để trống phải là một lựa chọn hiện rõ, không phải một ô
            // rỗng không ai hiểu nghĩa.
            ->assertSee('Dùng mức mặc định của cửa hàng');
    }

    #[Test]
    public function form_san_pham_KHONG_moi_chon_nhom_da_tat(): void
    {
        $nhom = $this->nhom('vat_10', '0.10000');
        $nhom->update(['is_active' => false]);

        $this->actingAs($this->admin())
            ->get('/admin/products/create')
            ->assertOk()
            ->assertDontSee($nhom->name);
    }

    #[Test]
    public function id_nhom_thue_khong_co_that_bi_chan_o_form_san_pham(): void
    {
        /*
         * Không được để một id bịa chui vào cột khoá ngoại. Lọt qua thì
         * phép tính thuế của mọi đơn sau đó đọc phải một nhóm không tồn
         * tại.
         */
        $danhMuc = Category::factory()->create();

        $this->actingAs($this->admin())
            ->post('/admin/products', [
                'category_id' => $danhMuc->id,
                'name' => 'Cây thử nghiệm',
                'base_price' => '100000',
                'status' => 'published',
                'tax_class_id' => 999999,
            ])
            ->assertSessionHasErrors('tax_class_id');

        $this->assertSame(0, Product::count());
    }
}
