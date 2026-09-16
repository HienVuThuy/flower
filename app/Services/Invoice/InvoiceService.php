<?php

namespace App\Services\Invoice;

use App\Enums\InvoiceBuyerType;
use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\Order;
use Illuminate\Support\Str;

/**
 * NƠI DUY NHẤT dựng dữ liệu hoá đơn từ một đơn hàng.
 * ⚠️ DỰNG DỮ LIỆU, KHÔNG PHÁT HÀNH. Hoá đơn điện tử hợp lệ phải được
 */
class InvoiceService
{
    public function taoTuDon(Order $order, array $checkout): ?Invoice
    {
        if (! ($checkout['want_invoice'] ?? false)) {
            return null;
        }

        if ($order->tax_amount === null) {
            return null;
        }

        $loai = InvoiceBuyerType::tryFrom((string) ($checkout['invoice_buyer_type'] ?? ''))
            ?? InvoiceBuyerType::Personal;

        return Invoice::create([
            'order_id' => $order->id,
            'invoice_number' => $this->sinhSo(),
            'buyer_type' => $loai,

            'buyer_name' => trim((string) ($checkout['invoice_buyer_name'] ?? '')) ?: $order->recipient_name,

            'buyer_tax_code' => $loai->requiresTaxCode()
                ? (trim((string) ($checkout['invoice_tax_code'] ?? '')) ?: null)
                : null,

            'buyer_address' => trim((string) ($checkout['invoice_address'] ?? '')) ?: null,
            'buyer_email' => trim((string) ($checkout['invoice_email'] ?? '')) ?: null,

            'subtotal' => $order->netTotal(),
            'tax_total' => $order->tax_amount,
            'grand_total' => $order->grand_total,

            'rate_breakdown' => $order->taxByRate(),

            'status' => InvoiceStatus::Draft,
        ]);
    }

    private function sinhSo(): string
    {
        for ($lan = 0; $lan < 5; $lan++) {
            $so = sprintf('HD-%s-%s', now()->format('ymd'), Str::upper(Str::random(4)));

            if (! Invoice::where('invoice_number', $so)->exists()) {
                return $so;
            }
        }

        throw new \RuntimeException('Không sinh được số hoá đơn, vui lòng thử lại.');
    }
}
