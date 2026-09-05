<?php

namespace Tests\Feature\Admin;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Vòng đời trạng thái thanh toán của đơn hàng.
 * ============================================================
 * ĐÂY LÀ CỘT NÓI VỀ TIỀN, nên sai ở đây tốn tiền thật:
 *
 *   - Đánh dấu nhầm "đã thanh toán" → cửa hàng giao hàng rồi không bao
 *     giờ đòi tiền.
 *   - Huỷ đơn khách đã trả mà không ghi nhận → cửa hàng giữ tiền của
 *     khách và không ai biết.
 *
 * BỐN LỖI ĐO ĐƯỢC TRƯỚC KHI SỬA, tất cả đều nằm ở markPaid() cũ — hàm
 * đó gán thẳng cột, không kiểm gì:
 *
 *   1. Đơn ĐÃ HUỶ vẫn đánh dấu được là đã thanh toán.
 *   2. Huỷ đơn đã trả tiền không ghi nhận gì → vẫn là "đã thanh toán".
 *   3. PaymentStatus::Refunded không nơi nào đặt — mã chết từ lúc khai.
 *   4. Bấm nhầm thì không có đường lui.
 */
class OrderPaymentTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->role = UserRole::Admin;
        $user->save();

        return $user;
    }

    /** Một đơn có thật trong bảng, đặt được trạng thái tuỳ ý. */
    private function order(OrderStatus $status, PaymentStatus $payment): Order
    {
        $product = Product::factory()->for(Category::factory())->price('300000.00')->create();

        $order = Order::create([
            'order_number' => 'FP-TEST-'.strtoupper(bin2hex(random_bytes(3))),
            'recipient_name' => 'Khách thử',
            'recipient_phone' => '0912345678',
            'shipping_address' => '1 Đường Thử',
            'shipping_province' => 'Thành phố Hà Nội',
            'payment_method' => 'cod',
            'subtotal' => '300000.00',
            'discount_total' => '0.00',
            'shipping_fee' => '25000.00',
            'coupon_discount' => '0.00',
            'grand_total' => '325000.00',
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'unit_base_price' => '300000.00',
            'unit_price' => '300000.00',
            'quantity' => 1,
            'line_total' => '300000.00',
        ]);

        $order->status = $status;
        $order->payment_status = $payment;
        $order->save();

        return $order;
    }

    private function setPayment(Order $order, PaymentStatus $target)
    {
        return $this->actingAs($this->admin())
            ->from('/admin/orders/'.$order->order_number)
            ->patch('/admin/orders/'.$order->order_number.'/thanh-toan', [
                'payment_status' => $target->value,
            ]);
    }

    // ================= ĐƯỜNG ĐI ĐÚNG =================

    #[Test]
    public function ghi_nhan_don_da_thanh_toan(): void
    {
        $order = $this->order(OrderStatus::Confirmed, PaymentStatus::Unpaid);

        $this->setPayment($order, PaymentStatus::Paid)->assertSessionHas('success');

        $this->assertSame(PaymentStatus::Paid, $order->fresh()->payment_status);
    }

    #[Test]
    public function ghi_nhan_da_hoan_tien_cho_don_da_huy(): void
    {
        // Trạng thái "Đã hoàn tiền" trước đây là mã chết — khai trong
        // enum nhưng không nơi nào đặt được.
        $order = $this->order(OrderStatus::Cancelled, PaymentStatus::Paid);

        $this->setPayment($order, PaymentStatus::Refunded)->assertSessionHas('success');

        $this->assertSame(PaymentStatus::Refunded, $order->fresh()->payment_status);
    }

    #[Test]
    public function go_duoc_danh_dau_khi_bam_nham_don(): void
    {
        /*
         * ĐƯỜNG LUI LÀ CẦN THIẾT, không phải tiện tay.
         *
         * Danh sách đơn hiện 20 dòng giống nhau; bấm nhầm một dòng là
         * cửa hàng giao hàng mà không bao giờ đòi tiền. Không có đường
         * lui thì cách duy nhất để sửa là vào thẳng cơ sở dữ liệu.
         */
        $order = $this->order(OrderStatus::Confirmed, PaymentStatus::Paid);

        $this->setPayment($order, PaymentStatus::Unpaid)->assertSessionHas('success');

        $this->assertSame(PaymentStatus::Unpaid, $order->fresh()->payment_status);
    }

    // ================= ĐƯỜNG ĐI SAI PHẢI BỊ CHẶN =================

    #[Test]
    public function KHONG_danh_dau_thanh_toan_cho_don_da_huy(): void
    {
        /*
         * LỖI ĐÃ ĐO ĐƯỢC. Đơn `cancelled` vẫn nhận `paid` mà không một
         * lời cảnh báo. Nếu khách lỡ chuyển tiền thật thì việc cần làm
         * là HOÀN TIỀN, không phải ghi nhận đã thu.
         */
        $order = $this->order(OrderStatus::Cancelled, PaymentStatus::Unpaid);

        $this->setPayment($order, PaymentStatus::Paid)->assertSessionHas('error');

        $this->assertSame(PaymentStatus::Unpaid, $order->fresh()->payment_status);
    }

    #[Test]
    public function KHONG_hoan_tien_cho_don_khach_chua_tra(): void
    {
        // Chưa nhận thì không có gì để hoàn. Cho phép là tạo ra một
        // khoản hoàn tiền không có khoản thu tương ứng — sổ sách lệch.
        $order = $this->order(OrderStatus::Cancelled, PaymentStatus::Unpaid);

        $this->setPayment($order, PaymentStatus::Refunded)->assertSessionHas('error');

        $this->assertSame(PaymentStatus::Unpaid, $order->fresh()->payment_status);
    }

    #[Test]
    public function da_hoan_tien_la_diem_cuoi_khong_di_tiep(): void
    {
        $order = $this->order(OrderStatus::Cancelled, PaymentStatus::Refunded);

        foreach ([PaymentStatus::Paid, PaymentStatus::Unpaid] as $target) {
            $this->setPayment($order, $target)->assertSessionHas('error');
        }

        $this->assertSame(PaymentStatus::Refunded, $order->fresh()->payment_status);
    }

    #[Test]
    public function dat_lai_dung_trang_thai_dang_co_thi_bao_loi(): void
    {
        // Không phải lỗi nghiêm trọng, nhưng im lặng "thành công" thì
        // admin tưởng vừa đổi được cái gì đó.
        $order = $this->order(OrderStatus::Confirmed, PaymentStatus::Paid);

        $this->setPayment($order, PaymentStatus::Paid)->assertSessionHas('error');
    }

    // ================= NHẮC HOÀN TIỀN =================

    #[Test]
    public function trang_don_nhac_khi_da_huy_ma_khach_da_tra_tien(): void
    {
        /*
         * Hệ thống KHÔNG tự đặt "đã hoàn tiền" — nó không biết ai đó có
         * thật sự chuyển khoản trả lại hay chưa. Việc của phần mềm là
         * NHẮC cho tới khi người thật xác nhận.
         */
        $order = $this->order(OrderStatus::Cancelled, PaymentStatus::Paid);

        $this->actingAs($this->admin())
            ->get('/admin/orders/'.$order->order_number)
            ->assertOk()
            ->assertSee('Cần hoàn tiền cho khách');
    }

    #[Test]
    public function don_binh_thuong_khong_hien_nhac_hoan_tien(): void
    {
        $order = $this->order(OrderStatus::Confirmed, PaymentStatus::Paid);

        $this->actingAs($this->admin())
            ->get('/admin/orders/'.$order->order_number)
            ->assertOk()
            ->assertDontSee('Cần hoàn tiền cho khách');
    }

    // ================= GHI CHÚ NỘI BỘ =================

    #[Test]
    public function luu_duoc_ghi_chu_noi_bo(): void
    {
        // Cột `admin_note` có từ lúc dựng bảng nhưng chưa từng có giao
        // diện nào ghi vào — ghi chú kiểu này trước giờ nằm trong đầu
        // người trực và mất khi đổi ca.
        $order = $this->order(OrderStatus::Confirmed, PaymentStatus::Unpaid);

        $this->actingAs($this->admin())
            ->from('/admin/orders/'.$order->order_number)
            ->patch('/admin/orders/'.$order->order_number.'/ghi-chu', [
                'admin_note' => 'Đã gọi 2 lần không nghe. Hẹn giao lại chiều mai.',
            ])
            ->assertSessionHas('success');

        $this->assertSame(
            'Đã gọi 2 lần không nghe. Hẹn giao lại chiều mai.',
            $order->fresh()->admin_note,
        );
    }

    #[Test]
    public function khach_KHONG_nhin_thay_ghi_chu_noi_bo(): void
    {
        // Ghi chú nội bộ hay chứa thứ không nên để khách đọc: "khách khó
        // tính", "gọi mãi không nghe". Lọt sang trang khách là hỏng.
        $khach = User::factory()->create();
        $order = $this->order(OrderStatus::Confirmed, PaymentStatus::Unpaid);
        $order->user_id = $khach->id;
        $order->admin_note = 'GHI CHU NOI BO KHONG DUOC LO';
        $order->save();

        $this->actingAs($khach)
            ->get('/don-hang/'.$order->order_number)
            ->assertOk()
            ->assertDontSee('GHI CHU NOI BO KHONG DUOC LO');
    }

    #[Test]
    public function khach_thuong_khong_doi_duoc_trang_thai_thanh_toan(): void
    {
        $order = $this->order(OrderStatus::Confirmed, PaymentStatus::Unpaid);

        $this->actingAs(User::factory()->create())
            ->patch('/admin/orders/'.$order->order_number.'/thanh-toan', [
                'payment_status' => 'paid',
            ]);

        $this->assertSame(PaymentStatus::Unpaid, $order->fresh()->payment_status);
    }
}
