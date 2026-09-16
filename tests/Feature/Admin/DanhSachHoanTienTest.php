<?php

namespace Tests\Feature\Admin;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\RefundMethod;
use App\Enums\RefundReason;
use App\Enums\RefundStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\Refund;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Danh sách mọi khoản hoàn tiền. */
class DanhSachHoanTienTest extends TestCase
{
    use RefreshDatabase;

    private function nguoi(UserRole $vaiTro): User
    {
        $u = User::factory()->create();
        $u->role = $vaiTro;
        $u->save();

        return $u;
    }

    private function don(string $ma): Order
    {
        $don = Order::create([
            'order_number' => $ma,
            'recipient_name' => 'Chị Lan',
            'recipient_phone' => '0912345678',
            'shipping_address' => '1 Đường Thử',
            'shipping_province' => 'Thành phố Hà Nội',
            'payment_method' => 'momo',
            'subtotal' => '900000.00',
            'discount_total' => '0.00',
            'shipping_fee' => '0.00',
            'coupon_discount' => '0.00',
            'grand_total' => '900000.00',
        ]);

        $don->forceFill(['status' => OrderStatus::Cancelled, 'payment_status' => PaymentStatus::Paid])->save();

        return $don;
    }

    private function hoan(Order $don, string $tien, RefundStatus $trangThai, RefundMethod $cach = RefundMethod::BankTransfer): Refund
    {
        $r = new Refund();
        $r->forceFill([
            'order_id' => $don->id,
            'code' => 'HT-' . strtoupper(bin2hex(random_bytes(3))),
            'amount' => $tien,
            'reason' => RefundReason::Other,
            'method' => $cach,
            'status' => $trangThai,
            'completed_at' => $trangThai === RefundStatus::Completed ? now() : null,
            'created_by_name' => 'Kế toán',
        ])->save();

        return $r;
    }

    private function trang(array $loc = [], ?User $ai = null)
    {
        return $this->actingAs($ai ?? $this->nguoi(UserRole::Admin))
            ->get(route('admin.refunds.index', $loc));
    }

    #[Test]
    public function hien_moi_khoan_va_dan_toi_don(): void
    {
        $don = $this->don('FP-HT-0001');
        $r = $this->hoan($don, '200000.00', RefundStatus::Completed);

        $this->trang()
            ->assertOk()
            ->assertSee($r->code)
            ->assertSee('href="' . route('admin.orders.show', $don) . '"', false);
    }

    #[Test]
    public function DA_HOAN_chi_cong_khoan_da_xong(): void
    {
        $don = $this->don('FP-HT-0002');
        $this->hoan($don, '200000.00', RefundStatus::Completed);
        $this->hoan($don, '150000.00', RefundStatus::Completed);
        $this->hoan($don, '500000.00', RefundStatus::Pending);
        $this->hoan($don, '100000.00', RefundStatus::Failed);

        $html = $this->trang()->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#data-da-hoan>350\.000#u', $html);
        $this->assertMatchesRegularExpression('#data-chua-ro>1<#u', $html);
    }

    #[Test]
    public function loc_theo_trang_thai(): void
    {
        $don = $this->don('FP-HT-0003');
        $xong = $this->hoan($don, '200000.00', RefundStatus::Completed);
        $that = $this->hoan($don, '100000.00', RefundStatus::Failed);

        $this->trang(['trang_thai' => RefundStatus::Failed->value])
            ->assertOk()
            ->assertSee($that->code)
            ->assertDontSee($xong->code);
    }

    #[Test]
    public function loc_theo_cach_hoan(): void
    {
        $don = $this->don('FP-HT-0004');
        $momo = $this->hoan($don, '200000.00', RefundStatus::Completed, RefundMethod::Momo);
        $tienMat = $this->hoan($don, '100000.00', RefundStatus::Completed, RefundMethod::Cash);

        $this->trang(['phuong_thuc' => RefundMethod::Cash->value])
            ->assertOk()
            ->assertSee($tienMat->code)
            ->assertDontSee($momo->code);
    }

    #[Test]
    public function tim_theo_ma_don(): void
    {
        $a = $this->hoan($this->don('FP-TIM-AAAA'), '200000.00', RefundStatus::Completed);
        $b = $this->hoan($this->don('FP-KHAC-BBBB'), '100000.00', RefundStatus::Completed);

        $this->trang(['q' => 'TIM-AAAA'])
            ->assertOk()
            ->assertSee($a->code)
            ->assertDontSee($b->code);
    }

    #[Test]
    public function nhan_vien_khong_co_quyen_tai_chinh_bi_chan_va_khong_thay_muc(): void
    {
        $nv = $this->nguoi(UserRole::Staff);

        $this->trang([], $nv)->assertForbidden();

        $this->actingAs($nv)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertDontSee('href="' . route('admin.refunds.index') . '"', false);
    }

    #[Test]
    public function quan_tri_thay_muc_hoan_tien_tren_thanh_ben(): void
    {
        $this->actingAs($this->nguoi(UserRole::Admin))
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('href="' . route('admin.refunds.index') . '"', false);
    }
}
