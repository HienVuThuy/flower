<?php

namespace App\Services\Order;

use App\Enums\OrderStatus;
use App\Mail\OrderConfirmationMail;
use App\Mail\OrderCancelledByCustomerMail;
use App\Mail\NewBulkInquiryForShopMail;
use App\Mail\NewOrderForShopMail;
use App\Models\BulkOrderInquiry;
use App\Mail\OrderStatusMail;
use App\Mail\RefundMail;
use App\Enums\RefundStatus;
use App\Models\Refund;
use App\Models\Order;
use App\Models\Setting;
use App\Services\Mail\MailTransport;
use Illuminate\Support\Facades\Log;

/** Gửi email xác nhận đơn hàng. */
class OrderMailer
{
    public function __construct(
        private readonly MailTransport $transport,
    ) {
    }

    public function deliversForReal(): bool
    {
        return $this->transport->deliversForReal();
    }

    private function sendConfirmation(Order $order): bool
    {
        if (! $order->recipient_email) {
            return false;
        }

        try {
            $this->transport->deliver(new OrderConfirmationMail($order), $order->recipient_email);

            return true;
        } catch (\Throwable $e) {
            Log::error('Không gửi được email xác nhận đơn hàng.', [
                'order_number' => $order->order_number,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function sendStatusUpdate(Order $order): bool
    {
        if (! $order->status->notifiesCustomer() || ! $order->recipient_email) {
            return false;
        }

        if ($order->user && ! $order->user->wantsOrderUpdates()) {
            return false;
        }

        $mail = $order->status === OrderStatus::Confirmed
            ? null
            : new OrderStatusMail($order->loadMissing('items'));

        if ($mail === null) {
            return $this->sendConfirmation($order);
        }

        try {
            $this->transport->deliver($mail, $order->recipient_email);

            return true;
        } catch (\Throwable $e) {
            Log::error('Không gửi được email cập nhật trạng thái đơn.', [
                'order_number' => $order->order_number,
                'status' => $order->status->value,
                'exception' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function sendRefund(Refund $refund): bool
    {
        $refund->loadMissing('order', 'items.orderItem');

        if ($refund->status !== RefundStatus::Completed || ! $refund->order->recipient_email) {
            return false;
        }

        try {
            $this->transport->deliver(new RefundMail($refund), $refund->order->recipient_email);

            return true;
        } catch (\Throwable $e) {
            Log::error('Không gửi được email báo hoàn tiền.', [
                'refund' => $refund->code,
                'exception' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function notifyShopOfNewOrder(Order $order): bool
    {
        return $this->guiChoCuaHang(
            fn () => new NewOrderForShopMail($order->loadMissing('items')),
            'Không gửi được thông báo đơn mới cho cửa hàng.',
            ['order_number' => $order->order_number],
        );
    }

    public function notifyShopOfBulkInquiry(BulkOrderInquiry $inquiry): bool
    {
        return $this->guiChoCuaHang(
            fn () => new NewBulkInquiryForShopMail($inquiry->loadMissing('product')),
            'Không gửi được thông báo yêu cầu báo giá cho cửa hàng.',
            ['inquiry_id' => $inquiry->id],
        );
    }

    private function guiChoCuaHang(\Closure $taoThu, string $loiLog, array $nguCanh): bool
    {
        $shopEmail = Setting::get('site_email');

        if (! $shopEmail) {
            return false;
        }

        try {
            $this->transport->deliver($taoThu(), $shopEmail);

            return true;
        } catch (\Throwable $e) {
            Log::error($loiLog, $nguCanh + ['exception' => $e->getMessage()]);

            return false;
        }
    }

    public function notifyShopOfCancellation(Order $order): bool
    {
        $shopEmail = Setting::get('site_email');

        if (! $shopEmail) {
            return false;
        }

        try {
            $this->transport->deliver(
                new OrderCancelledByCustomerMail($order->loadMissing('items')),
                $shopEmail,
            );

            return true;
        } catch (\Throwable $e) {
            Log::error('Không gửi được thông báo huỷ đơn cho cửa hàng.', [
                'order_number' => $order->order_number,
                'exception' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
