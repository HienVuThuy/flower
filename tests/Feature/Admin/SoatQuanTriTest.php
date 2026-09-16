<?php

namespace Tests\Feature\Admin;

use App\Enums\FlowerLotStatus;
use App\Enums\StockReceiptKind;
use App\Enums\UserRole;
use App\Models\FlowerKind;
use App\Models\FlowerLot;
use App\Models\StockReceipt;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Chỗ vô lý tìm ra khi soát trang quản trị với dữ liệu mẫu. */
class SoatQuanTriTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->role = UserRole::Admin;
        $u->save();

        return $u;
    }

    private function phieu(Supplier $ncc, StockReceiptKind $loai, string $ma): StockReceipt
    {
        $p = StockReceipt::create(['code' => $ma, 'supplier_id' => $ncc->id, 'supplier' => $ncc->name, 'received_at' => now()->toDateString()]);
        $p->forceFill(['kind' => $loai])->save();

        return $p;
    }

    #[Test]
    public function so_lan_lay_hang_gom_lo_hoa_va_KHONG_gom_phieu_tra(): void
    {
        $vua = Supplier::create(['name' => 'Vựa hoa thử', 'kind' => 'vua']);
        $loai = FlowerKind::create(['name' => 'Hồng thử', 'default_unit' => 'bo']);

        foreach ([1, 2, 3] as $i) {
            (new FlowerLot())->forceFill([
                'code' => 'LH-T-' . $i, 'flower_kind_id' => $loai->id, 'supplier_id' => $vua->id,
                'purchased_at' => now()->toDateString(), 'quantity' => '5', 'unit' => 'bo',
                'total_cost' => '500000', 'status' => FlowerLotStatus::DangDung,
            ])->save();
        }

        $this->phieu($vua, StockReceiptKind::NhapMoi, 'NK-T-1');
        $this->phieu($vua, StockReceiptKind::TraNcc, 'NK-T-2');

        $html = $this->actingAs($this->admin())->get(route('admin.suppliers.index'))->assertOk()->getContent();

        $this->assertStringContainsString('1 phiếu nhập · 3 lô hoa', $html);
    }

    #[Test]
    public function danh_sach_phieu_ghi_ro_loai_phieu_tra_va_ton_dau_ky(): void
    {
        $ncc = Supplier::create(['name' => 'Vựa thử', 'kind' => 'vua']);
        $this->phieu($ncc, StockReceiptKind::NhapMoi, 'NK-T-MOI');
        $this->phieu($ncc, StockReceiptKind::TraNcc, 'NK-T-TRA');
        $this->phieu($ncc, StockReceiptKind::TonDauKy, 'NK-T-DAU');

        $html = $this->actingAs($this->admin())->get(route('admin.stock-receipts.index'))->assertOk()->getContent();

        $dong = function (string $ma) use ($html) {
            $dau = strpos($html, $ma);
            $this->assertNotFalse($dau);

            return substr($html, $dau, strpos($html, '</td>', $dau) - $dau);
        };

        $this->assertStringContainsString(StockReceiptKind::TraNcc->label(), $dong('NK-T-TRA'));
        $this->assertStringContainsString(StockReceiptKind::TonDauKy->label(), $dong('NK-T-DAU'));
        $this->assertStringNotContainsString(StockReceiptKind::NhapMoi->label(), $dong('NK-T-MOI'));
    }
}
