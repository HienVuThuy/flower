<?php

namespace App\Services\Order;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentTransactionStatus;
use App\Enums\PaymentStatus;
use App\Models\PaymentTransaction;
use App\Models\Order;
use App\Models\OrderStatusEvent;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Checkout\CheckoutBasket;
use App\Services\Checkout\CheckoutLine;
use App\Services\Audit\ActivityLogger;
use App\Services\Care\CareScheduler;
use App\Services\Coupon\CouponService;
use App\Services\Inventory\StockReturn;
use App\Services\Invoice\InvoiceService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/** Đặt hàng và đổi trạng thái đơn. */
class OrderService
{
    public function __construct(
        private readonly OrderNumberGenerator $numbers,
        private readonly CouponService $coupons,
        private readonly OrderMailer $mailer,
        private readonly CareScheduler $care,
        private readonly OrderRiskScorer $risk,
        private readonly ActivityLogger $audit,
        private readonly InvoiceService $invoices,
        private readonly StockReturn $stock = new StockReturn(),
    ) {
    }

    public function place(CheckoutBasket $basket, array $checkout, ?string $idempotencyKey = null): Order
    {
        if ($basket->isEmpty()) {
            throw new OrderException('Giỏ hàng đang trống.');
        }

        try {
            $order = DB::transaction(fn () => $this->createOrder($basket, $checkout, $idempotencyKey));

            $this->mailer->notifyShopOfNewOrder($order);

            return $order;
        } catch (QueryException $e) {
            if ($idempotencyKey && $this->isDuplicateKeyError($e)) {
                $existing = Order::where('idempotency_key', $idempotencyKey)->first();

                if ($existing) {
                    return $existing->load('items');
                }
            }

            throw $e;
        }
    }

    private function isDuplicateKeyError(QueryException $e): bool
    {
        return $e->getCode() === '23000'
            && str_contains($e->getMessage(), 'idempotency_key');
    }

    private function createOrder(CheckoutBasket $basket, array $checkout, ?string $idempotencyKey): Order
    {
        $tax = $basket->tax();

        $lines = [];

        foreach ($basket->lines->values() as $i => $line) {
            $lines[] = $this->buildLine($line, $tax->lines[$i] ?? null);
        }

        $subtotal = $basket->baseTotal();
        $discountTotal = $basket->discountTotal();
        $shippingFee = $basket->shippingFee();

        $grandTotal = $basket->grandTotal();

        $order = Order::create([
            'order_number' => $this->numbers->generate(),
            'idempotency_key' => $idempotencyKey,
            'user_id' => Auth::id(),
            'recipient_name' => $checkout['recipient_name'],
            'recipient_phone' => $checkout['recipient_phone'],
            'recipient_email' => $checkout['recipient_email'] ?? null,
            'shipping_address' => $checkout['shipping_address'],
            'shipping_ward' => $checkout['shipping_ward'] ?? null,
            'shipping_district' => $checkout['shipping_district'] ?? null,
            'shipping_province' => $checkout['shipping_province'],
            'delivery_date' => $checkout['delivery_date'] ?? null,
            'delivery_note' => $checkout['delivery_note'] ?? null,
            'payment_method' => $checkout['payment_method'],
            'subtotal' => $subtotal,
            'discount_total' => $discountTotal,
            'coupon_id' => $basket->coupon?->id,
            'coupon_code' => $basket->coupon?->code,
            'coupon_discount' => $basket->couponDiscount(),
            'points_used' => $basket->pointsUsed(),
            'member_tier_code' => $basket->memberTier?->code,
            'member_discount' => $basket->memberDiscount(),
            'points_discount' => $basket->pointsDiscount(),
            'shipping_fee' => $shippingFee,

            'to_district_id' => $basket->toDistrictId,
            'to_ward_code' => $basket->toWardCode,
            'ghn_total_fee' => app(\App\Services\Shipping\ShippingQuote::class)
                ->ghnFee($basket, $basket->toDistrictId, $basket->toWardCode),
            'grand_total' => $grandTotal,

            'tax_rate' => $tax->shippingRate,
            'tax_amount' => $tax->total(),
            'shipping_tax_amount' => $tax->shippingTax,
        ]);

        $order->items()->createMany($lines);

        app(\App\Services\Gift\GiftGranter::class)->tangChoDon($order, $basket, Auth::user());

        $order->load('items');
        $this->invoices->taoTuDon($order, $checkout);

        if ($order->payment_method === PaymentMethod::Cod) {
            PaymentTransaction::create([
                'order_id' => $order->id,
                'gateway' => PaymentMethod::Cod->value,
                'amount' => $order->grand_total,
                'status' => PaymentTransactionStatus::Pending,
                'message' => 'Thu tiền khi giao hàng',
            ]);
        }

        if ($order->payment_method === PaymentMethod::TraGop) {
            app(\App\Services\Installment\InstallmentService::class)
                ->taoKeHoach($order, Auth::user(), (int) ($checkout['so_ky'] ?? 0));
        }

        $this->risk->apply($order);

        if ($basket->coupon) {
            $this->coupons->redeem($basket->coupon, $order);
        }

        if ($basket->pointsUsed() > 0 && Auth::user() !== null) {
            app(\App\Services\Points\PointLedger::class)->dungChoDon(Auth::user(), $basket->pointsUsed(), $order);
        }

        OrderStatusEvent::create([
            'order_id' => $order->id,
            'status' => $order->status->value,
            'changed_by' => null,
            'note' => null,
        ]);

        return $order->load('items');
    }

    private function buildLine(CheckoutLine $line, ?array $thue = null): array
    {
        $product = $line->product;
        $variant = $line->variant;

        $this->consumeStock($product, $variant, $line->quantity);

        return [
            'product_id' => $product->id,
            'product_variant_id' => $variant?->id,
            'product_name' => $product->name,
            'product_sku' => $product->product_code,
            'variant_name' => $variant?->name,
            'promotion_name' => $line->promotionName(),
            'unit_base_price' => $line->unitBasePrice(),
            'unit_price' => $line->unitPrice(),
            'quantity' => $line->quantity,
            'line_total' => $line->lineTotal(),

            'discount_amount' => $thue['discount'] ?? '0.00',
            'tax_rate' => $thue['rate'] ?? null,
            'tax_amount' => $thue['tax'] ?? null,
        ];
    }

    private function assertStillSellable(Product $product, ?ProductVariant $variant): void
    {
        $fresh = Product::whereKey($product->id)->first();

        if (! $fresh || $fresh->status !== 'active') {
            throw new OrderException(sprintf(
                'Sản phẩm "%s" vừa ngừng bán. Vui lòng bỏ khỏi giỏ hàng rồi đặt lại.',
                $product->name,
            ));
        }

        if ($fresh->base_price === null) {
            throw new OrderException(sprintf(
                'Sản phẩm "%s" nay chỉ nhận báo giá, không bán trực tiếp.',
                $product->name,
            ));
        }

        if (! $variant) {
            $coQuyCach = $fresh->variants()->where('is_active', true)->exists();

            if ($coQuyCach) {
                throw new OrderException(sprintf(
                    'Sản phẩm "%s" nay bán theo quy cách. Vui lòng bỏ khỏi giỏ hàng '
                    .'rồi chọn lại quy cách bạn muốn.',
                    $product->name,
                ));
            }

            return;
        }

        $freshVariant = ProductVariant::whereKey($variant->id)->first();

        if (! $freshVariant
            || ! $freshVariant->is_active
            || $freshVariant->product_id !== $fresh->id) {
            throw new OrderException(sprintf(
                'Quy cách "%s" của sản phẩm "%s" vừa ngừng bán.',
                $variant->name,
                $product->name,
            ));
        }
    }

    private function consumeStock(Product $product, ?ProductVariant $variant, int $quantity): void
    {
        $this->assertStillSellable($product, $variant);

        if ($variant) {
            if (! $variant->track_inventory) {
                return;
            }

            $fresh = ProductVariant::whereKey($variant->id)->lockForUpdate()->first();

            if (! $fresh || $fresh->stock_quantity < $quantity) {
                throw new OrderException(sprintf(
                    'Sản phẩm "%s - %s" không đủ hàng (còn %d).',
                    $product->name,
                    $variant->name,
                    (int) ($fresh->stock_quantity ?? 0),
                ));
            }

            $fresh->decrement('stock_quantity', $quantity);

            return;
        }

        if (! $product->track_inventory) {
            return;
        }

        $fresh = Product::whereKey($product->id)->lockForUpdate()->first();

        if (! $fresh || $fresh->stock_quantity < $quantity) {
            throw new OrderException(sprintf(
                'Sản phẩm "%s" không đủ hàng (còn %d).',
                $product->name,
                (int) ($fresh->stock_quantity ?? 0),
            ));
        }

        $fresh->decrement('stock_quantity', $quantity);
    }

    public function changeStatus(
        Order $order,
        OrderStatus $target,
        ?string $reason = null,
        bool $tuDong = false,
    ): void {
        $truocDo = $order->status;

        DB::transaction(function () use ($order, $target, $reason, $tuDong) {
            $locked = Order::whereKey($order->id)->lockForUpdate()->first();

            if (! $locked) {
                throw new OrderException('Không tìm thấy đơn hàng.');
            }

            if (! $locked->status->canTransitionTo($target)) {
                throw new OrderException(sprintf(
                    'Không thể chuyển từ "%s" sang "%s".',
                    $locked->status->label(),
                    $target->label(),
                ));
            }

            if (in_array($target, [OrderStatus::Preparing, OrderStatus::Shipping, OrderStatus::Completed], true)
                && $locked->choDoiTraGop()) {
                throw new OrderException('Đơn trả góp chưa trả đủ các kỳ nên chưa chuẩn bị hay giao được.');
            }

            if ($target === OrderStatus::Cancelled
                && $locked->ghn_order_code
                && $locked->shipping_status !== 'cancel') {
                throw new OrderException(sprintf(
                    'Đơn đã bàn giao cho GHN (mã %s). Huỷ vận đơn GHN trước, rồi mới huỷ đơn — '
                    .'nếu không, hệ thống ghi "đã huỷ" trong khi shipper vẫn đang giao.',
                    $locked->ghn_order_code,
                ));
            }

            $order->setRawAttributes($locked->getAttributes(), sync: true);

            $restoreStock = $target === OrderStatus::Cancelled && $order->status->holdsStock();

            $order->status = $target;

            if ($target === OrderStatus::Confirmed) {
                $order->confirmed_at = now();
            }

            if ($target === OrderStatus::Completed) {
                $order->completed_at = now();
            }

            if ($target === OrderStatus::Cancelled) {
                $order->cancelled_at = now();
                $order->cancel_reason = $reason;
            }

            $order->save();

            OrderStatusEvent::create([
                'order_id' => $order->id,
                'status' => $target->value,
                'changed_by' => $tuDong ? null : Auth::id(),
                'note' => $reason,
            ]);

            if ($restoreStock) {
                $this->restoreStock($order);
            }

            if ($restoreStock) {
                $this->coupons->release($order);

                app(\App\Services\Points\PointLedger::class)->traDiemCuaDon($order);

                app(\App\Services\Gift\GiftGranter::class)->traSuatCuaDon($order);

                app(\App\Services\Installment\InstallmentService::class)->dongTheoDon($order);
            }
        });

        $this->audit->logChange(
            'order.status_changed',
            $order,
            'Trạng thái đơn '.$order->order_number,
            $truocDo->label(),
            $target->label(),
            array_filter([
                'order_number' => $order->order_number,
                'ly_do' => $reason,
            ]),
        );

        if ($target === OrderStatus::Completed) {
            try {
                $this->care->scheduleForOrder($order);
            } catch (\Throwable $e) {
                Log::error('Không dựng được lịch nhắc chăm cây.', [
                    'order_number' => $order->order_number,
                    'exception' => $e->getMessage(),
                ]);
            }

            try {
                app(\App\Services\Points\PointEarning::class)->donHoanTat($order);
            } catch (\Throwable $e) {
                Log::error('Không cộng được điểm mua hàng.', [
                    'order_number' => $order->order_number,
                    'exception' => $e->getMessage(),
                ]);
            }
        }

        $this->mailer->sendStatusUpdate($order);
    }

    private function restoreStock(Order $order): void
    {
        foreach ($order->items as $item) {
            $this->stock->congLai($item, (int) $item->quantity);
        }
    }

    public function setPaymentStatus(Order $order, PaymentStatus $target): void
    {
        $current = $order->payment_status;

        if ($current === $target) {
            throw new OrderException(sprintf(
                'Đơn này đã ở trạng thái "%s" rồi.',
                $target->label(),
            ));
        }

        if (! $current->canTransitionTo($target)) {
            throw new OrderException(sprintf(
                'Không chuyển được từ "%s" sang "%s".',
                $current->label(),
                $target->label(),
            ));
        }

        if ($order->payment_method === PaymentMethod::TraGop) {
            $traDu = \App\Models\InstallmentPlan::query()
                ->where('order_id', $order->id)
                ->where('status', \App\Enums\InstallmentStatus::HoanTat->value)
                ->exists();

            if (! ($target === PaymentStatus::Paid && $traDu)) {
                throw new OrderException(
                    'Đơn trả góp tự chuyển "Đã thanh toán" khi khách trả đủ các kỳ — ghi nhận từng kỳ ở mục Trả góp của đơn.'
                );
            }
        }

        if ($target === PaymentStatus::Paid && $order->status === OrderStatus::Cancelled) {
            throw new OrderException(
                'Đơn đã huỷ nên không ghi nhận thanh toán được. '
                .'Nếu khách đã chuyển tiền, hãy hoàn tiền cho khách.'
            );
        }

        if ($target === PaymentStatus::Refunded && $order->status === OrderStatus::Cancelled) {
            throw new OrderException(
                'Hoàn tiền phải ghi ở mục "Hoàn tiền" của đơn: nhập số tiền, cách hoàn và mã giao dịch. '
                .'Trạng thái "Đã hoàn tiền" sẽ tự đặt khi đã hoàn đủ.'
            );
        }

        if ($target === PaymentStatus::Refunded && $order->status !== OrderStatus::Cancelled) {
            throw new OrderException(
                'Chỉ hoàn tiền cho đơn đã huỷ. '
                .'Đơn này đang ở trạng thái "'.$order->status->label().'" — '
                .'hãy huỷ đơn trước, rồi mới ghi nhận hoàn tiền.'
            );
        }

        if ($target === PaymentStatus::Unpaid && $order->refunds()->where('status', '!=', 'failed')->exists()) {
            throw new OrderException(
                'Đơn này đã có khoản hoàn tiền nên không gỡ đánh dấu thanh toán được.'
            );
        }

        if ($target === PaymentStatus::Unpaid && $order->status === OrderStatus::Completed) {
            throw new OrderException(
                'Đơn đã giao xong nên không gỡ đánh dấu thanh toán được. '
                .'Nếu ghi nhận sai, hãy liên hệ người quản trị hệ thống.'
            );
        }

        $order->payment_status = $target;
        $order->save();

        $this->audit->logChange(
            'order.payment_changed',
            $order,
            'Thanh toán đơn '.$order->order_number,
            $current->label(),
            $target->label(),
            ['order_number' => $order->order_number],
        );

        Log::info('Đổi trạng thái thanh toán.', [
            'order' => $order->order_number,
            'tu' => $current->value,
            'sang' => $target->value,
        ]);
    }

    public function owesRefund(Order $order): bool
    {
        return $order->status === OrderStatus::Cancelled
            && $order->payment_status !== PaymentStatus::Refunded
            && bccomp($order->refundableAmount(), '0', 2) > 0;
    }
}
