<?php

namespace Tests\Feature\Admin;

use App\Enums\ExpenseCategory;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\ActivityLog;
use App\Models\Expense;
use App\Models\Order;
use App\Models\User;
use App\Services\Analytics\CashFlowReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Sổ thu chi: chi phí vận hành và lãi ròng ước tính theo tháng.
 * ============================================================
 * Đồng hồ: 14/09/2026 17:00 giờ Việt Nam (10:00 UTC).
 */
class SoThuChiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-09-14 10:00:00', 'UTC'));
    }

    private function nguoi(UserRole $vaiTro): User
    {
        $u = User::factory()->create();
        $u->role = $vaiTro;
        $u->save();

        return $u;
    }

    private function chi(string $ngay, string $soTien, array $ghiDe = []): Expense
    {
        return Expense::create(array_merge([
            'spent_on' => $ngay, 'category' => ExpenseCategory::Luong, 'description' => 'Lương',
            'amount' => $soTien, 'is_fixed' => false,
        ], $ghiDe));
    }

    private function donDaGiao(string $tien, string $taoLucUtc): void
    {
        $don = Order::create([
            'order_number' => 'FP-TC-' . strtoupper(bin2hex(random_bytes(3))),
            'recipient_name' => 'Khách', 'recipient_phone' => '0912345678',
            'shipping_address' => '1 Đường', 'shipping_province' => 'Thành phố Hà Nội',
            'payment_method' => 'cod', 'subtotal' => $tien, 'discount_total' => '0.00',
            'shipping_fee' => '0.00', 'coupon_discount' => '0.00', 'grand_total' => $tien,
        ]);
        $don->forceFill(['status' => OrderStatus::Completed, 'payment_status' => PaymentStatus::Paid, 'created_at' => $taoLucUtc])->save();

        // Dòng hàng CHƯA CÓ GIÁ VỐN: doanh thu có, lãi gộp chưa tính được.
        $don->items()->create([
            'product_id' => null, 'product_name' => 'Cây thử', 'quantity' => 1,
            'unit_base_price' => $tien, 'unit_price' => $tien, 'line_total' => $tien,
        ]);
    }

    /* ================= QUYỀN ================= */

    #[Test]
    public function nhan_vien_khong_vao_duoc_va_khong_thay_muc(): void
    {
        $nv = $this->nguoi(UserRole::Staff);

        $this->actingAs($nv)->get(route('admin.expenses.index'))->assertForbidden();
        $this->actingAs($nv)->post(route('admin.expenses.store'), [])->assertForbidden();

        $html = $this->actingAs($nv)->get('/admin/dashboard')->assertOk()->getContent();
        $this->assertStringNotContainsString('href="' . route('admin.expenses.index') . '"', $html);

        $html = $this->actingAs($this->nguoi(UserRole::Admin))->get('/admin/dashboard')->assertOk()->getContent();
        $this->assertStringContainsString('href="' . route('admin.expenses.index') . '"', $html);
    }

    /* ================= GHI ================= */

    #[Test]
    public function ghi_khoan_chi_lam_tron_dong_ghi_nguoi_ghi_va_nhat_ky(): void
    {
        $chu = $this->nguoi(UserRole::Admin);
        $khac = User::factory()->create();

        $this->actingAs($chu)->post(route('admin.expenses.store'), [
            'spent_on' => '2026-09-10', 'category' => 'dien_nuoc', 'description' => '  Tiền điện  ',
            'amount' => '1250000.6', 'is_fixed' => '1',
            'created_by' => $khac->id, 'created_by_name' => 'Người khác',   // không được nhận từ biểu mẫu
        ])->assertRedirect(route('admin.expenses.index', ['thang' => '2026-09']));

        $k = Expense::sole();
        $this->assertSame('1250001.00', (string) $k->amount);
        $this->assertSame('Tiền điện', $k->description);
        $this->assertTrue($k->is_fixed);
        $this->assertSame($chu->id, $k->created_by);
        $this->assertSame($chu->name, $k->created_by_name);

        $log = ActivityLog::where('action', 'expense.created')->sole();
        $this->assertSame('1250001.00', $log->properties['so_tien']);
    }

    #[Test]
    public function tu_choi_so_tien_khong_duong_va_ngay_thang_sau(): void
    {
        $chu = $this->nguoi(UserRole::Admin);
        $hopLe = ['spent_on' => '2026-09-10', 'category' => 'luong', 'description' => 'Lương', 'amount' => '5000000'];

        $this->actingAs($chu)->post(route('admin.expenses.store'), ['amount' => '0'] + $hopLe)->assertSessionHasErrors('amount');
        $this->actingAs($chu)->post(route('admin.expenses.store'), ['spent_on' => '2026-10-01'] + $hopLe)->assertSessionHasErrors('spent_on');
        $this->actingAs($chu)->post(route('admin.expenses.store'), ['category' => 'bia'] + $hopLe)->assertSessionHasErrors('category');

        // Cuối tháng này vẫn ghi được — lương trả ngày 30.
        $this->actingAs($chu)->post(route('admin.expenses.store'), ['spent_on' => '2026-09-30'] + $hopLe)->assertSessionHasNoErrors();
        $this->assertSame(1, Expense::count());
    }

    #[Test]
    public function sua_va_xoa_deu_vao_nhat_ky(): void
    {
        $chu = $this->nguoi(UserRole::Admin);
        $k = $this->chi('2026-09-05', '3000000.00');

        $this->actingAs($chu)->put(route('admin.expenses.update', $k), [
            'spent_on' => '2026-09-05', 'category' => 'luong', 'description' => 'Lương', 'amount' => '3500000',
        ])->assertSessionHasNoErrors();

        $this->assertSame('3500000.00', (string) $k->fresh()->amount);
        $log = ActivityLog::where('action', 'expense.updated')->sole();
        $this->assertSame('3000000.00', $log->properties['so_tien_truoc']);

        $this->actingAs($chu)->delete(route('admin.expenses.destroy', $k))->assertRedirect();
        $this->assertDatabaseMissing('expenses', ['id' => $k->id]);
        $this->assertSame(1, ActivityLog::where('action', 'expense.deleted')->count());
    }

    /* ================= BÁO CÁO THÁNG ================= */

    #[Test]
    public function thang_cat_theo_gio_viet_nam_va_tach_dong_tien_voi_lai(): void
    {
        // 31/08 18:00 UTC = 01/09 01:00 Hà Nội → thuộc tháng 9.
        $this->donDaGiao('2000000.00', '2026-08-31 18:00:00');
        // 31/08 16:00 UTC = 31/08 23:00 Hà Nội → tháng 8.
        $this->donDaGiao('900000.00', '2026-08-31 16:00:00');

        $this->chi('2026-09-01', '500000.00');
        $this->chi('2026-09-30', '300000.00', ['category' => ExpenseCategory::MayChu, 'description' => 'Server']);
        $this->chi('2026-08-31', '7000000.00');   // tháng trước

        $bao = app(CashFlowReport::class)->thang('2026-09');

        $this->assertSame('2000000.00', $bao['dong_tien']['tien_vao']);
        $this->assertSame('800000.00', $bao['dong_tien']['chi_phi']);
        $this->assertSame('1200000.00', $bao['dong_tien']['chenh']);

        // Không có giá vốn nào → lãi gộp hàng 0, lãi ròng = −chi phí (và phải nói ra là chưa đủ vốn).
        $this->assertSame('-800000.00', $bao['lai']['lai_rong']);
        $this->assertSame(0.0, $bao['lai']['ti_le_phu']);
        $this->assertSame(['luong' => '500000.00', 'may_chu' => '300000.00'], $bao['chi_phi_theo_loai']);

        $html = $this->actingAs($this->nguoi(UserRole::Admin))
            ->get(route('admin.expenses.index', ['thang' => '2026-09']))
            ->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#data-dong="lai-rong".*?<dd[^>]*text-danger[^>]*>\s*-?800\.000#s', $html);
        $this->assertStringContainsString('Mới 0% doanh thu hàng có giá vốn', $html);
        $this->assertStringContainsString('chưa tới ngày', $html);   // khoản 30/09
    }

    #[Test]
    public function hoan_tien_va_bu_ship_tru_vao_lai_rong(): void
    {
        $this->donDaGiao('1000000.00', '2026-09-03 03:00:00');
        $don = Order::sole();
        $hoan = $don->refunds()->create(['code' => 'HT-TC-0001', 'amount' => '200000.00', 'method' => 'chuyen_khoan', 'reason' => 'khac']);
        $hoan->forceFill(['status' => 'completed', 'completed_at' => now()])->save();

        // Miễn phí ship cho khách, cửa hàng trả GHN 50.000đ.
        $don->forceFill([
            'ghn_order_code' => 'GHN-TC-1', 'ghn_fee_payer' => 'shop',
            'shipping_status' => 'delivered', 'ghn_total_fee' => '50000.00',
        ])->save();

        $this->chi('2026-09-02', '100000.00');

        $bao = app(CashFlowReport::class)->thang('2026-09');

        $this->assertSame('800000.00', $bao['dong_tien']['tien_vao']);
        $this->assertSame('200000.00', $bao['lai']['hoan_tien']);
        $this->assertSame('50000.00', $bao['lai']['bu_ship']);
        // 0 lãi gộp − 100.000 chi phí − 50.000 bù ship − 200.000 hoàn tiền.
        $this->assertSame('-350000.00', $bao['lai']['lai_rong']);
    }

    #[Test]
    public function thang_sai_dang_hoac_tuong_lai_ve_thang_hien_tai(): void
    {
        $chu = $this->nguoi(UserRole::Admin);

        foreach (['2026-13', 'abc', '2027-01'] as $sai) {
            $this->actingAs($chu)->get(route('admin.expenses.index', ['thang' => $sai]))
                ->assertOk()->assertSee('Sổ thu chi tháng 09/2026');
        }

        // Đang ở tháng hiện tại thì không có nút "Tháng sau".
        $this->actingAs($chu)->get(route('admin.expenses.index'))->assertDontSee('Tháng sau');
        $this->actingAs($chu)->get(route('admin.expenses.index', ['thang' => '2026-08']))->assertSee('Tháng sau');
    }

    /* ================= CHÉP KHOẢN CỐ ĐỊNH ================= */

    #[Test]
    public function chep_co_dinh_chi_lay_khoan_co_dinh_giu_ngay_va_khong_nhan_doi(): void
    {
        $chu = $this->nguoi(UserRole::Admin);

        $this->chi('2026-08-31', '6000000.00', ['is_fixed' => true, 'description' => 'Lương chị Hoa']);
        $this->chi('2026-08-05', '2000000.00', ['is_fixed' => true, 'category' => ExpenseCategory::MatBang, 'description' => 'Thuê mặt bằng']);
        $this->chi('2026-08-10', '400000.00', ['description' => 'Mua kéo']);   // không cố định

        $this->actingAs($chu)->get(route('admin.expenses.index', ['thang' => '2026-09']))
            ->assertSee('Tháng trước có <strong>2</strong> khoản cố định', false);

        $this->actingAs($chu)->post(route('admin.expenses.copy-fixed'), ['thang' => '2026-09'])->assertRedirect();
        $this->actingAs($chu)->post(route('admin.expenses.copy-fixed'), ['thang' => '2026-09'])->assertRedirect();

        $thang9 = Expense::where('spent_on', '>=', '2026-09-01')->orderBy('spent_on')->get();

        $this->assertCount(2, $thang9, 'Bấm hai lần vẫn chỉ hai dòng');
        $this->assertSame('2026-09-05', $thang9[0]->spent_on->toDateString());
        $this->assertSame('2026-09-30', $thang9[1]->spent_on->toDateString(), '31/08 → 30/09, không tràn sang tháng 10');
        $this->assertSame($chu->id, $thang9[1]->created_by);
        $this->assertTrue($thang9[1]->is_fixed);
        $this->assertSame(1, ActivityLog::where('action', 'expense.copied')->count());

        $this->actingAs($chu)->get(route('admin.expenses.index', ['thang' => '2026-09']))
            ->assertDontSee('khoản cố định chưa có');
    }

    /* ================= TRANG LỢI NHUẬN ================= */

    #[Test]
    public function trang_loi_nhuan_noi_chua_ghi_thay_vi_0_dong(): void
    {
        $chu = $this->nguoi(UserRole::Admin);
        $dong = fn (string $html) => substr($html, strpos($html, 'Chi phí vận hành đã ghi'), 300);

        $html = $this->actingAs($chu)->get(route('admin.analytics.profit'))->assertOk()->getContent();
        $this->assertStringContainsString('chưa ghi', $dong($html));

        $this->chi('2026-09-12', '450000.00');

        $html = $this->actingAs($chu)->get(route('admin.analytics.profit'))->assertOk()->getContent();
        $this->assertStringContainsString('450.000', $dong($html));
        $this->assertStringNotContainsString('chưa ghi', $dong($html));
    }
}
