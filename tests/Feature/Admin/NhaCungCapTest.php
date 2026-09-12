<?php

namespace Tests\Feature\Admin;

use App\Enums\SupplierKind;
use App\Enums\UserRole;
use App\Models\Product;
use App\Models\Category;
use App\Models\StockReceipt;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Nhà cung cấp: nơi cửa hàng lấy hàng, thành một bảng.
 * ============================================================
 * TRƯỚC BẢN NÀY `stock_receipts.supplier` là một ô chữ gõ tay.
 *
 * Nghe thì tiện, nhưng nó làm mất đúng thứ đáng giá nhất của sổ thu mua:
 * **so sánh**. "Vựa Hoa Tươi", "vựa hoa tươi" và "Vua hoa tuoi" là ba
 * nơi khác nhau với máy, nên câu "cùng loại hồng này mua ở đâu rẻ hơn"
 * không trả lời được — mà đó chính là câu khiến người ta chịu khó ghi
 * chép ngay từ đầu.
 *
 * Nên bài quan trọng nhất ở đây là bài **chống trùng tên**.
 */
class NhaCungCapTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->role = UserRole::Admin;
        $u->save();

        return $u;
    }

    private function them(array $ghiDe = []): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->admin())->post('/admin/nha-cung-cap', array_merge([
            'name' => 'Vựa hoa Quảng Bá',
            'kind' => SupplierKind::Vua->value,
            'phone' => '0912345678',
            'is_active' => '1',
        ], $ghiDe));
    }

    /* ================= CHỐNG TRÙNG ================= */

    #[Test]
    public function khong_cho_hai_dong_cung_mot_ten(): void
    {
        $this->them()->assertSessionHasNoErrors()->assertRedirect();

        $this->them()
            ->assertSessionHasErrors('name');

        $this->assertSame(1, Supplier::count());
    }

    #[Test]
    public function ten_bi_cat_khoang_trang_thua_truoc_khi_luu(): void
    {
        /*
         * "  Vựa hoa Quảng Bá  " và "Vựa hoa Quảng Bá" là một nơi. Không
         * cắt thì chúng thành hai dòng, và bảng chống trùng ở trên trở
         * nên vô dụng — đúng cái mớ hỗn độn mà cả bảng này sinh ra để dẹp.
         */
        $this->them(['name' => '  Vựa hoa Quảng Bá  '])->assertRedirect();

        $this->assertSame('Vựa hoa Quảng Bá', Supplier::first()->name);
    }

    #[Test]
    public function sua_chinh_minh_thi_khong_bi_bao_trung_ten(): void
    {
        $this->them()->assertRedirect();
        $ncc = Supplier::firstOrFail();

        $this->actingAs($this->admin())
            ->put('/admin/nha-cung-cap/' . $ncc->id, [
                'name' => $ncc->name,
                'kind' => SupplierKind::NongDan->value,
                'is_active' => '1',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertSame(SupplierKind::NongDan, $ncc->fresh()->kind);
    }

    /* ================= KHÔNG XOÁ ================= */

    #[Test]
    public function khong_co_duong_dan_nao_xoa_nha_cung_cap(): void
    {
        /*
         * Phiếu nhập cũ trỏ tới đây. Xoá là mất dấu vết những lần đã mua
         * — đúng thứ người ta giữ sổ để có. Ngừng làm ăn thì tắt đi.
         *
         * Bài này canh để sau này không ai thêm nút xoá mà quên mất lý do.
         */
        $duong = collect(\Illuminate\Support\Facades\Route::getRoutes())
            ->filter(fn ($r) => str_starts_with($r->uri(), 'admin/nha-cung-cap'))
            ->map(fn ($r) => implode('|', $r->methods()))
            ->implode(' ');

        $this->assertStringNotContainsString('DELETE', $duong);
    }

    #[Test]
    public function tat_di_thi_khong_con_hien_o_o_chon_nhung_van_doc_duoc(): void
    {
        $this->them(['name' => 'Vựa còn lấy'])->assertRedirect();
        $this->them(['name' => 'Vựa đã ngừng'])->assertRedirect();

        $ngung = Supplier::where('name', 'Vựa đã ngừng')->firstOrFail();

        $this->actingAs($this->admin())->put('/admin/nha-cung-cap/' . $ngung->id, [
            'name' => $ngung->name,
            'kind' => $ngung->kind->value,
            // Không gửi is_active -> bỏ tích.
        ])->assertRedirect();

        $this->assertFalse($ngung->fresh()->is_active);

        // Biểu mẫu phiếu nhập chỉ bày nơi còn lấy hàng...
        $html = $this->actingAs($this->admin())
            ->get('/admin/nhap-kho/create')
            ->assertOk()
            ->getContent();

        $con = Supplier::where('name', 'Vựa còn lấy')->firstOrFail();

        /*
         * SOI VÀO THẺ <option>, KHÔNG SOI TÊN TRẦN.
         *
         * Bản đầu của bài này tìm chuỗi "Vựa đã ngừng" trong cả trang và
         * báo đỏ — nhưng nó bắt trúng dòng thông báo "Đã lưu Vựa đã
         * ngừng." còn sót lại trong phiên từ lần chuyển hướng trước, chứ
         * không phải ô chọn. Bài sai, mã đúng.
         *
         * Tìm theo `value="id"` thì không có chỗ nào khác trên trang
         * trùng được.
         */
        $this->assertStringContainsString('value="' . $con->id . '"', $html);
        $this->assertStringNotContainsString('value="' . $ngung->id . '"', $html);

        // ...nhưng danh sách nhà cung cấp vẫn còn đọc được.
        $this->actingAs($this->admin())
            ->get('/admin/nha-cung-cap')
            ->assertOk()
            ->assertSee('Vựa đã ngừng');
    }

    /* ================= NỐI VÀO PHIẾU NHẬP ================= */

    #[Test]
    public function phieu_nhap_chup_lai_TEN_chu_khong_chi_giu_id(): void
    {
        /*
         * Nhà cung cấp đổi tên thì phiếu cũ vẫn phải đọc được là hồi đó
         * mua của ai — cùng nguyên tắc với `order_items.product_name`.
         */
        $this->them(['name' => 'Vựa tên cũ'])->assertRedirect();
        $ncc = Supplier::firstOrFail();

        $sp = Product::factory()->for(Category::factory())->stock(5)->create();

        $this->actingAs($this->admin())->post('/admin/nhap-kho', [
            'supplier_id' => $ncc->id,
            'received_at' => now()->toDateString(),
            'items' => [
                ['mat_hang' => (string) $sp->id, 'quantity' => 3, 'unit_cost' => 50000],
            ],
        ])->assertSessionHasNoErrors()->assertRedirect();

        $phieu = StockReceipt::firstOrFail();

        // Phiếu phải thật sự có dòng hàng — không có khẳng định này thì
        // một biểu mẫu gửi sai tên trường vẫn cho bài xanh.
        $this->assertSame(1, $phieu->items()->count());

        $this->assertSame($ncc->id, $phieu->supplier_id);
        $this->assertSame('Vựa tên cũ', $phieu->supplier);

        // Đổi tên nhà cung cấp: phiếu cũ giữ nguyên tên hồi đó.
        $ncc->update(['name' => 'Vựa tên mới']);

        $this->assertSame('Vựa tên cũ', $phieu->fresh()->tenNhaCungCap());
    }

    #[Test]
    public function de_trong_nha_cung_cap_van_lap_duoc_phieu(): void
    {
        /*
         * Có lần mua lẻ ngoài chợ không thuộc mối nào. Bắt khai một nhà
         * cung cấp giả chỉ để qua được biểu mẫu thì còn tệ hơn để trống —
         * dữ liệu bịa làm hỏng chính phần so sánh mà bảng này sinh ra.
         */
        $sp = Product::factory()->for(Category::factory())->stock(5)->create();

        $this->actingAs($this->admin())->post('/admin/nhap-kho', [
            'received_at' => now()->toDateString(),
            'items' => [['mat_hang' => (string) $sp->id, 'quantity' => 1, 'unit_cost' => 1000]],
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertNull(StockReceipt::firstOrFail()->supplier_id);
    }

    #[Test]
    public function id_nha_cung_cap_khong_co_that_thi_bi_tu_choi(): void
    {
        $sp = Product::factory()->for(Category::factory())->stock(5)->create();

        $this->actingAs($this->admin())->post('/admin/nhap-kho', [
            'supplier_id' => 999999,
            'received_at' => now()->toDateString(),
            'items' => [['mat_hang' => (string) $sp->id, 'quantity' => 1, 'unit_cost' => 1000]],
        ])->assertSessionHasErrors('supplier_id');

        $this->assertSame(0, StockReceipt::count());
    }

    /* ================= PHÂN QUYỀN ================= */

    #[Test]
    public function nhan_vien_kho_vao_duoc_con_khach_thi_khong(): void
    {
        $nv = User::factory()->create();
        $nv->role = UserRole::Staff;
        $nv->save();

        // Nhà cung cấp thuộc khu "kho" — nhân viên có quyền đó.
        $this->actingAs($nv)->get('/admin/nha-cung-cap')->assertOk();

        $khach = User::factory()->create();
        $khach->role = UserRole::Customer;
        $khach->save();

        $this->actingAs($khach)->get('/admin/nha-cung-cap')->assertForbidden();
    }
}
