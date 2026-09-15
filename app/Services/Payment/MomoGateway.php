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

/**
 * Lớp nối thẳng tới API thanh toán của MoMo.
 * ============================================================
 * Đặt trong `App\Services\Payment` chứ không phải `App\Services` trần
 * như tài liệu: dự án đã chia dịch vụ theo nghiệp vụ, và hợp đồng
 * PaymentGateway đã nằm sẵn ở đây.
 *
 * CHỮ KÝ LÀ RANH GIỚI TIN CẬY. Ai trên Internet cũng gửi được request
 * tới địa chỉ callback; thứ duy nhất phân biệt MoMo thật với người giả
 * mạo là chuỗi HMAC ký bằng khoá bí mật chỉ hai bên biết. Hai chuỗi ký
 * ở đây KHÁC NHAU và không được dùng lẫn:
 *
 *   - lúc TẠO yêu cầu: accessKey, amount, extraData, ipnUrl, orderId,
 *     orderInfo, partnerCode, redirectUrl, requestId, requestType
 *   - lúc NHẬN kết quả: accessKey, amount, extraData, message, orderId,
 *     orderInfo, orderType, partnerCode, payType, requestId,
 *     responseTime, resultCode, transId
 *
 * Thứ tự các trường là một phần của chuỗi ký — đảo một trường là chữ ký
 * sai và mọi callback bị vứt.
 */
class MomoGateway implements PaymentGateway
{
    public const GATEWAY = 'momo';

    public function configured(): bool
    {
        return (bool) config('payment.gateways.momo.enabled', false);
    }

    /**
     * Mở một lượt thanh toán mới và trả về nơi cần đưa khách tới.
     *
     * Mỗi lần gọi tạo MỘT bản ghi giao dịch mới, kể cả khi khách trả lại
     * lần thứ ba cho cùng một đơn — đó chính là mục đích của bảng
     * `payment_transactions`.
     *
     * @throws PaymentException
     */
    public function createPayment(Order $order, ?MomoFlow $flow = null, ?\App\Models\InstallmentPayment $ky = null): string
    {
        if (! $this->configured()) {
            throw new PaymentException('Cổng MoMo chưa được cấu hình.');
        }

        $flow ??= MomoFlow::macDinh();

        /*
         * TRẢ GÓP: lượt này thu ĐÚNG số tiền của một kỳ và ghi kỳ đó vào giao
         * dịch — callback đối chiếu số tiền với chính giao dịch, nên không ai
         * trả một kỳ 100.000₫ mà được ghi cả đơn.
         */
        $transaction = PaymentTransaction::create([
            'order_id' => $order->id,
            'installment_payment_id' => $ky?->id,
            'gateway' => self::GATEWAY,
            'amount' => $ky?->amount ?? $order->grand_total,
            'status' => PaymentTransactionStatus::Pending,
        ]);

        $data = $this->buildRequest($order, $transaction, $flow);

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
                'order' => $order->order_number,
                'result_code' => $result['resultCode'] ?? null,
                'message' => $result['message'] ?? null,
            ]);

            throw new PaymentException(
                'Chưa kết nối được tới MoMo'
                . (isset($result['message']) ? ': ' . $result['message'] : '')
                . '. Bạn có thể thử lại, hoặc chọn thanh toán khi nhận hàng.'
            );
        }

        return (string) $result['payUrl'];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildRequest(Order $order, PaymentTransaction $transaction, MomoFlow $flow): array
    {
        $partnerCode = (string) config('payment.gateways.momo.partner_code');
        $accessKey = (string) config('payment.gateways.momo.access_key');
        $secretKey = (string) config('payment.gateways.momo.secret_key');

        /*
         * MoMo chỉ nhận số nguyên VND, không có phần thập phân.
         * `grand_total` là decimal(12,2) nên phải ép trước khi ký — ký
         * chuỗi "580000.00" rồi gửi "580000" là chữ ký không khớp.
         */
        $amount = (string) (int) round((float) $transaction->amount);

        /*
         * DUY NHẤT CHO TỪNG LƯỢT, không phải cho từng đơn.
         *
         * Cột `(gateway, gateway_order_id)` là UNIQUE, và MoMo cũng từ
         * chối nhận lại một orderId đã dùng. Trả lại lần hai cho cùng
         * một đơn phải mang mã khác — kèm id giao dịch là đủ.
         */
        $orderId = $order->order_number . '-' . $transaction->id;

        /*
         * extraData mang MÃ ĐƠN, vì MoMo trả nguyên nó về trong
         * callback. Nhờ vậy tìm lại được đơn kể cả khi bản ghi giao dịch
         * có vấn đề. Dùng order_number chứ không dùng id tự tăng: id để
         * lộ tổng số đơn cửa hàng đã bán.
         */
        $extraData = (string) $order->order_number;

        $orderInfo = 'Thanh toan don hang ' . $order->order_number;
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

    /**
     * HOÀN TIỀN trên một giao dịch MoMo đã thành công.
     * ============================================================
     * Chuỗi ký của API hoàn tiền KHÁC hai chuỗi ở đầu lớp:
     *
     *     accessKey, amount, description, orderId, partnerCode,
     *     requestId, transId
     *
     * Đã thử trên cổng thử với một mã giao dịch không tồn tại (nên không
     * có đồng nào bị hoàn): chữ ký theo thứ tự này qua được bước kiểm,
     * còn khoá sai bị trả mã 11007 kèm đúng chuỗi gốc MoMo mong đợi.
     *
     * TRẢ VỀ GÓI TIN THÔ, không tự quyết thành công hay thất bại. Mảng
     * rỗng nghĩa là KHÔNG BIẾT: mất kết nối hoặc hết thời gian chờ thì
     * MoMo có thể đã hoàn rồi. Nơi gọi phải giữ khoản đó ở "chưa rõ kết
     * quả", không được coi là thất bại — coi là thất bại thì số tiền được
     * nhả ra, admin bấm hoàn lại, và khách nhận tiền hai lần.
     *
     * @param  string  $maYeuCau  duy nhất cho mỗi lần hoàn; MoMo từ chối mã đã dùng
     * @return array<string, mixed>
     */
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

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
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

        // hash_equals chứ không phải ===: so sánh chuỗi thường dừng ở
        // byte đầu tiên khác nhau, và thời gian dừng đó rò rỉ thông tin
        // đủ để dò dần ra chữ ký đúng.
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

    /** Lượt thanh toán mà gói tin này nói tới. */
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

    /**
     * @param  array<string, mixed>  $payload
     */
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

    /**
     * @param  array<string, mixed>  $payload
     */
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

    /**
     * Bỏ chữ ký trước khi lưu gói tin gửi đi.
     *
     * Chữ ký không phải khoá bí mật, nhưng nó là một chuỗi ký bằng khoá
     * đó; không có lý do gì để nó nằm trong cơ sở dữ liệu, nơi mọi
     * admin đọc được.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function khongLoKhoa(array $data): array
    {
        unset($data['signature']);

        return $data;
    }
}
