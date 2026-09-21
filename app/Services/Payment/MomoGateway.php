<?php

namespace App\Services\Payment;

use App\Enums\MomoFlow;
use App\Enums\PaymentTransactionStatus;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Services\Payment\Contracts\PaymentGateway;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/** Lớp nối thẳng tới API thanh toán của MoMo. */
class MomoGateway implements PaymentGateway
{
    public const GATEWAY = 'momo';

    /** extraData của lượt trả phiếu chăm hộ, để không bị nhầm là mã đơn hàng. */
    public const TIEN_TO_CHAM_HO = 'chamho:';

    public function configured(): bool
    {
        return (bool) config('payment.gateways.momo.enabled', false);
    }

    public function createPayment(Order $order, ?MomoFlow $flow = null, ?\App\Models\InstallmentPayment $ky = null): string
    {
        if (! $this->configured()) {
            throw new PaymentException('Cổng MoMo chưa được cấu hình.');
        }

        $flow ??= MomoFlow::macDinh();

        $transaction = PaymentTransaction::create([
            'order_id' => $order->id,
            'installment_payment_id' => $ky?->id,
            'gateway' => self::GATEWAY,
            'amount' => $ky?->amount ?? $order->grand_total,
            'status' => PaymentTransactionStatus::Pending,
        ]);

        return $this->guiYeuCau(
            $transaction,
            (string) $order->order_number,
            (string) $order->order_number,
            'Thanh toan don hang ' . $order->order_number,
            $flow,
            '. Bạn có thể thử lại, hoặc chọn thanh toán khi nhận hàng.',
        );
    }

    /** Lượt trả online cho phiếu chăm cây hộ — cùng cổng, cùng chữ ký, cùng IPN với đơn hàng. */
    public function createBoardingPayment(\App\Models\BoardingBooking $phieu, string $soTien, ?MomoFlow $flow = null): string
    {
        if (! $this->configured()) {
            throw new PaymentException('Cổng MoMo chưa được cấu hình.');
        }

        $transaction = PaymentTransaction::create([
            'boarding_booking_id' => $phieu->id,
            'gateway' => self::GATEWAY,
            'amount' => $soTien,
            'status' => PaymentTransactionStatus::Pending,
        ]);

        return $this->guiYeuCau(
            $transaction,
            (string) $phieu->code,
            self::TIEN_TO_CHAM_HO . $phieu->code,
            'Thanh toan phieu cham cay ' . $phieu->code,
            $flow ?? MomoFlow::macDinh(),
            '. Bạn có thể thử lại, hoặc trả trực tiếp khi giao nhận cây.',
        );
    }

    private function guiYeuCau(PaymentTransaction $transaction, string $ma, string $extraData, string $orderInfo, MomoFlow $flow, string $loiThem): string
    {
        $data = $this->buildRequest($ma, $extraData, $orderInfo, $transaction, $flow);

        $transaction->update([
            'gateway_order_id' => $data['orderId'],
            'request_payload' => $this->khongLoKhoa($data),
        ]);

        $result = $this->send($data);

        $transaction->update([
            'response_payload' => $result,
            'result_code' => isset($result['resultCode']) ? (int) $result['resultCode'] : null,
            'message' => $result['message'] ?? null,
            'status' => isset($result['payUrl'])
                ? PaymentTransactionStatus::Initiated
                : PaymentTransactionStatus::Failed,
        ]);

        if (empty($result['payUrl'])) {
            Log::error('MoMo từ chối tạo yêu cầu thanh toán.', [
                'ma' => $ma,
                'result_code' => $result['resultCode'] ?? null,
                'message' => $result['message'] ?? null,
            ]);

            throw new PaymentException(
                'Chưa kết nối được tới MoMo'
                . (isset($result['message']) ? ': ' . $result['message'] : '')
                . $loiThem
            );
        }

        return (string) $result['payUrl'];
    }

    private function buildRequest(string $ma, string $extraData, string $orderInfo, PaymentTransaction $transaction, MomoFlow $flow): array
    {
        $partnerCode = (string) config('payment.gateways.momo.partner_code');
        $accessKey = (string) config('payment.gateways.momo.access_key');
        $secretKey = (string) config('payment.gateways.momo.secret_key');

        $amount = (string) (int) round((float) $transaction->amount);

        $orderId = $ma . '-' . $transaction->id;
        $requestId = $orderId . '-' . time();
        $requestType = $flow->requestType();
        $redirectUrl = $this->redirectUrl();
        $ipnUrl = $this->ipnUrl();

        $rawHash = 'accessKey=' . $accessKey
            . '&amount=' . $amount
            . '&extraData=' . $extraData
            . '&ipnUrl=' . $ipnUrl
            . '&orderId=' . $orderId
            . '&orderInfo=' . $orderInfo
            . '&partnerCode=' . $partnerCode
            . '&redirectUrl=' . $redirectUrl
            . '&requestId=' . $requestId
            . '&requestType=' . $requestType;

        return [
            'partnerCode' => $partnerCode,
            'partnerName' => (string) config('payment.gateways.momo.partner_name', 'Angevil'),
            'storeId' => (string) config('payment.gateways.momo.store_id', 'AngevilStore'),
            'requestId' => $requestId,
            'amount' => $amount,
            'orderId' => $orderId,
            'orderInfo' => $orderInfo,
            'redirectUrl' => $redirectUrl,
            'ipnUrl' => $ipnUrl,
            'lang' => 'vi',
            'extraData' => $extraData,
            'requestType' => $requestType,
            'signature' => hash_hmac('sha256', $rawHash, $secretKey),
        ];
    }

    public function refund(PaymentTransaction $daTra, int $soTien, string $maYeuCau, string $moTa): array
    {
        $partnerCode = (string) config('payment.gateways.momo.partner_code');
        $accessKey = (string) config('payment.gateways.momo.access_key');
        $secretKey = (string) config('payment.gateways.momo.secret_key');

        $transId = (string) $daTra->transaction_id;

        $raw = 'accessKey=' . $accessKey
            . '&amount=' . $soTien
            . '&description=' . $moTa
            . '&orderId=' . $maYeuCau
            . '&partnerCode=' . $partnerCode
            . '&requestId=' . $maYeuCau
            . '&transId=' . $transId;

        return $this->send([
            'partnerCode' => $partnerCode,
            'orderId' => $maYeuCau,
            'requestId' => $maYeuCau,
            'amount' => $soTien,
            'transId' => (int) $transId,
            'lang' => 'vi',
            'description' => $moTa,
            'signature' => hash_hmac('sha256', $raw, $secretKey),
        ], (string) config('payment.gateways.momo.refund_endpoint'));
    }

    private function send(array $data, ?string $endpoint = null): array
    {
        try {
            $response = Http::withOptions([
                'verify' => (bool) config('payment.gateways.momo.verify_ssl', true),
            ])
                ->timeout(20)
                ->acceptJson()
                ->post($endpoint ?? (string) config('payment.gateways.momo.endpoint'), $data);

            return $response->json() ?? [];
        } catch (ConnectionException $e) {
            Log::error('Không kết nối được tới MoMo.', ['exception' => $e->getMessage()]);

            return [];
        }
    }

    public function verifySignature(array $payload): bool
    {
        if (! isset($payload['signature']) || ! $this->configured()) {
            return false;
        }

        $accessKey = (string) config('payment.gateways.momo.access_key');
        $secretKey = (string) config('payment.gateways.momo.secret_key');

        $rawHash = 'accessKey=' . $accessKey
            . '&amount=' . ($payload['amount'] ?? '')
            . '&extraData=' . ($payload['extraData'] ?? '')
            . '&message=' . ($payload['message'] ?? '')
            . '&orderId=' . ($payload['orderId'] ?? '')
            . '&orderInfo=' . ($payload['orderInfo'] ?? '')
            . '&orderType=' . ($payload['orderType'] ?? '')
            . '&partnerCode=' . ($payload['partnerCode'] ?? '')
            . '&payType=' . ($payload['payType'] ?? '')
            . '&requestId=' . ($payload['requestId'] ?? '')
            . '&responseTime=' . ($payload['responseTime'] ?? '')
            . '&resultCode=' . ($payload['resultCode'] ?? '')
            . '&transId=' . ($payload['transId'] ?? '');

        return hash_equals(
            hash_hmac('sha256', $rawHash, $secretKey),
            (string) $payload['signature'],
        );
    }

    public function isSuccessful(array $payload): bool
    {
        return (string) ($payload['resultCode'] ?? '') === '0';
    }

    public function orderNumberFrom(array $payload): ?string
    {
        $ma = trim((string) ($payload['extraData'] ?? ''));

        return $ma === '' ? null : $ma;
    }

    public function transactionFrom(array $payload): ?PaymentTransaction
    {
        $ma = trim((string) ($payload['orderId'] ?? ''));

        if ($ma === '') {
            return null;
        }

        return PaymentTransaction::where('gateway', self::GATEWAY)
            ->where('gateway_order_id', $ma)
            ->first();
    }

    public function markPaid(PaymentTransaction $transaction, array $payload): void
    {
        $transaction->update([
            'transaction_id' => $payload['transId'] ?? null,
            'result_code' => (int) ($payload['resultCode'] ?? 0),
            'message' => $payload['message'] ?? null,
            'response_payload' => $payload,
            'status' => PaymentTransactionStatus::Paid,
            'paid_at' => now(),
        ]);
    }

    public function markFailed(PaymentTransaction $transaction, array $payload): void
    {
        if ($transaction->isPaid()) {
            return;
        }

        $transaction->update([
            'transaction_id' => $payload['transId'] ?? null,
            'result_code' => isset($payload['resultCode']) ? (int) $payload['resultCode'] : null,
            'message' => $payload['message'] ?? null,
            'response_payload' => $payload,
            'status' => PaymentTransactionStatus::Failed,
        ]);
    }

    private function redirectUrl(): string
    {
        return (string) (config('payment.gateways.momo.redirect_url')
            ?: route('shop.payment.momo.callback'));
    }

    private function ipnUrl(): string
    {
        return (string) (config('payment.gateways.momo.ipn_url')
            ?: route('shop.payment.momo.ipn'));
    }

    private function khongLoKhoa(array $data): array
    {
        unset($data['signature']);

        return $data;
    }
}
