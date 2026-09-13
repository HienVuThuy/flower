<?php

namespace Tests\Feature\Admin;

use App\Enums\FlowerLotStatus;
use App\Enums\ReturnReason;
use App\Enums\ReturnSettlement;
use App\Enums\UserRole;
use App\Models\ActivityLog;
use App\Models\FlowerKind;
use App\Models\FlowerLot;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Inventory\FlowerLotService;
use App\Services\Inventory\SupplierReturnService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Sửa và xoá lô hoa CÒN MỞ.
 * ============================================================
 * Gõ nhầm 5.000.000 thành 50.000.000 mà không sửa được thì con số đó đi
 * thẳng vào giá vốn khi đóng lô. Nhưng lô ĐÃ ĐÓNG (tiền đã vào một kỳ) và
 * lô ĐÃ GHI TRẢ HÀNG (tiền trả lại tính theo đơn giá cũ) thì không được
 * đụng vào.
 */
class SuaLoHoaTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private FlowerKind $loai;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
        $this->admin->role = UserRole::Admin;
        $this->admin->save();

        $this->loai = FlowerKind::create(['name' => 'Hồng đỏ', 'default_unit' => 'bo']);
    }

    private function lo(array $ghiDe = []): FlowerLot
    {
        $lo = new FlowerLot();

        $lo->forceFill(array_merge([
            'code' => 'LH-' . uniqid(),
            'flower_kind_id' => $this->loai->id,
            'purchased_at' => now()->subDays(2)->toDateString(),
            'quantity' => '10.00',
            'unit' => 'bo',
            'total_cost' => '50000000.00',
            'status' => FlowerLotStatus::DangDung,
        ], $ghiDe))->save();

        return $lo->fresh();
    }

    private function duLieu(array $ghiDe = []): array
    {
        return array_merge([
            'flower_kind_id' => $this->loai->id,
            'purchased_at' => now()->subDays(2)->toDateString(),
            'quantity' => '10',
            'unit' => 'bo',
            'total_cost' => '5000000',
        ], $ghiDe);
    }

    /* ================= SỬA ================= */

    #[Test]
    public function go_nham_tong_tien_thi_SUA_DUOC_khi_lo_con_mo(): void
    {
        $lo = $this->lo();

        $this->actingAs($this->admin)
            ->put(route('admin.flower-lots.update', $lo), $this->duLieu())
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.flower-lots.index'));

        $this->assertSame('5000000.00', $lo->fresh()->total_cost);
    }

    #[Test]
    public function sua_lo_ghi_NHAT_KY_kem_gia_tri_cu(): void
    {
        /*
         * Sửa tiền mà không để lại dấu vết thì câu hỏi "ai đổi tổng tiền
         * lô này từ bao nhiêu" không trả lời được.
         */
        $lo = $this->lo();

        $this->actingAs($this->admin)->put(route('admin.flower-lots.update', $lo), $this->duLieu());

        $nk = ActivityLog::where('action', 'kho.sua-lo-hoa')->latest('id')->firstOrFail();

        $this->assertStringContainsString('50000000.00 → 5000000.00', json_encode($nk->properties, JSON_UNESCAPED_UNICODE));
    }

    #[Test]
    public function lo_DA_DONG_thi_KHONG_sua_duoc(): void
    {
        $lo = $this->lo();
        app(FlowerLotService::class)->dongLo($lo);

        $this->actingAs($this->admin)
            ->put(route('admin.flower-lots.update', $lo), $this->duLieu())
            ->assertSessionHas('error');

        $this->assertSame('50000000.00', $lo->fresh()->total_cost, 'Tiền đã vào giá vốn của một kỳ');

        $this->get(route('admin.flower-lots.edit', $lo))
            ->assertRedirect(route('admin.flower-lots.index'));
    }

    #[Test]
    public function lo_DA_GHI_TRA_HANG_thi_KHONG_sua_duoc(): void
    {
        /*
         * Tiền lấy lại được tính theo đơn giá CŨ của lô. Đổi tổng tiền là
         * tiền trả lại không còn khớp với chính lô đó.
         */
        $lo = $this->lo();

        $this->actingAs($this->admin);

        app(SupplierReturnService::class)->traHangHoa($lo, [
            'quantity' => 2,
            'reason' => ReturnReason::HangHong->value,
            'settlement' => ReturnSettlement::HoanTien->value,
        ]);

        $this->put(route('admin.flower-lots.update', $lo), $this->duLieu())
            ->assertSessionHas('error');

        $this->assertSame('50000000.00', $lo->fresh()->total_cost);
    }

    #[Test]
    public function sua_van_kiem_tra_du_lieu_nhu_luc_ghi(): void
    {
        // Sửa mà lỏng hơn ghi là cửa sau để đưa lô 0 đồng vào sổ.
        $lo = $this->lo();

        $this->actingAs($this->admin)
            ->put(route('admin.flower-lots.update', $lo), $this->duLieu(['total_cost' => '0']))
            ->assertSessionHasErrors('total_cost');

        $this->assertSame('50000000.00', $lo->fresh()->total_cost);
    }

    #[Test]
    public function doi_nha_cung_cap_thi_doi_luon_BAN_CHUP_TEN(): void
    {
        $cu = Supplier::create(['name' => 'Vựa Cũ', 'kind' => 'vua']);
        $moi = Supplier::create(['name' => 'Vựa Mới', 'kind' => 'vua']);

        $lo = $this->lo(['supplier_id' => $cu->id, 'supplier_name' => $cu->name]);

        $this->actingAs($this->admin)
            ->put(route('admin.flower-lots.update', $lo), $this->duLieu(['supplier_id' => $moi->id]));

        $lo->refresh();

        $this->assertSame($moi->id, $lo->supplier_id);
        $this->assertSame('Vựa Mới', $lo->supplier_name, 'Báo cáo thu mua gom theo tên này');
    }

    #[Test]
    public function bieu_mau_sua_dien_san_gia_tri_cua_lo(): void
    {
        $lo = $this->lo(['note' => 'Hoa hơi non']);

        $this->actingAs($this->admin)
            ->get(route('admin.flower-lots.edit', $lo))
            ->assertOk()
            ->assertSee('Sửa lô ' . $lo->code)
            ->assertSee('value="50000000"', false)
            ->assertSee('Hoa hơi non');
    }

    #[Test]
    public function loai_hoa_da_ngung_van_con_trong_o_chon_khi_sua(): void
    {
        /*
         * Thiếu nó thì ô chọn tự nhảy sang mục đầu tiên, và bấm Lưu là
         * lặng lẽ đổi loại hoa của lô.
         */
        $lo = $this->lo();
        $this->loai->forceFill(['is_active' => false])->save();

        $this->actingAs($this->admin)
            ->get(route('admin.flower-lots.edit', $lo))
            ->assertOk()
            ->assertSee('value="' . $this->loai->id . '" selected', false);
    }

    /* ================= XOÁ ================= */

    #[Test]
    public function lo_ghi_trung_thi_XOA_DUOC_va_co_nhat_ky(): void
    {
        $lo = $this->lo();

        $this->actingAs($this->admin)
            ->delete(route('admin.flower-lots.destroy', $lo))
            ->assertRedirect(route('admin.flower-lots.index'));

        $this->assertNull(FlowerLot::find($lo->id));
        $this->assertTrue(ActivityLog::where('action', 'kho.xoa-lo-hoa')->exists());
    }

    #[Test]
    public function lo_DA_DONG_thi_KHONG_xoa_duoc(): void
    {
        $lo = $this->lo();
        app(FlowerLotService::class)->dongLo($lo);

        $this->actingAs($this->admin)
            ->delete(route('admin.flower-lots.destroy', $lo))
            ->assertSessionHas('error');

        $this->assertNotNull(FlowerLot::find($lo->id));
    }

    #[Test]
    public function danh_sach_chi_hien_nut_sua_cho_lo_CON_SUA_DUOC(): void
    {
        $mo = $this->lo();
        $dong = $this->lo();
        app(FlowerLotService::class)->dongLo($dong);

        $this->actingAs($this->admin)
            ->get(route('admin.flower-lots.index'))
            ->assertOk()
            ->assertSee(route('admin.flower-lots.edit', $mo), false)
            ->assertDontSee(route('admin.flower-lots.edit', $dong), false);
    }
}
