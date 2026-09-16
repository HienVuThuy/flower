<?php

namespace App\Services\Inventory;

use App\Enums\StockReceiptStatus;
use App\Models\StockReceipt;
use App\Services\Audit\ActivityLogger;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/** NƠI DUY NHẤT cộng hàng vào kho. */
class StockReceiptService
{
    public function __construct(
        private readonly ActivityLogger $audit,
        private readonly StockAdjuster $kho = new StockAdjuster(),
    ) {
    }

    public function sinhMa(): string
    {
        for ($lan = 0; $lan < 5; $lan++) {
            $ma = sprintf('NK-%s-%s', now()->format('ymd'), Str::upper(Str::random(4)));

            if (! StockReceipt::where('code', $ma)->exists()) {
                return $ma;
            }
        }

        throw new StockReceiptException('Không sinh được mã phiếu, vui lòng thử lại.');
    }

    public function ghiSo(StockReceipt $receipt): void
    {
        DB::transaction(function () use ($receipt) {
            $khoa = StockReceipt::whereKey($receipt->id)->lockForUpdate()->first();

            if (! $khoa) {
                throw new StockReceiptException('Không tìm thấy phiếu nhập.');
            }

            if ($khoa->status === StockReceiptStatus::Posted) {
                throw new StockReceiptException('Phiếu này đã ghi sổ rồi.');
            }

            $receipt->load('items');

            if ($receipt->items->isEmpty()) {
                throw new StockReceiptException('Phiếu chưa có dòng hàng nào để ghi sổ.');
            }

            if ($khoa->kind->congVaoKho()) {
                foreach ($receipt->items as $dong) {
                    $this->congVaoKho($dong->product_variant_id, $dong->product_id, $dong->quantity, $dong->product_name);
                }
            }

            $receipt->forceFill([
                'status' => StockReceiptStatus::Posted,
                'posted_at' => now(),
            ])->save();
        });

        $this->audit->log(
            'kho.ghi-so-phieu-nhap',
            sprintf(
                'Ghi sổ phiếu nhập %s: %d dòng, %d đơn vị',
                $receipt->code,
                $receipt->items->count(),
                $receipt->totalQuantity(),
            ),
            $receipt,
            [
                'code' => $receipt->code,
                'so_dong' => $receipt->items->count(),
                'so_luong' => $receipt->totalQuantity(),
            ],
        );

        Log::info('Đã ghi sổ phiếu nhập kho.', [
            'code' => $receipt->code,
            'so_luong' => $receipt->totalQuantity(),
        ]);
    }

    private function congVaoKho(?int $variantId, ?int $productId, int $soLuong, string $ten): void
    {
        $this->kho->dieuChinh($variantId, $productId, $soLuong, $ten);
    }

    public function gan(StockReceipt $receipt): StockReceipt
    {
        $nguoi = Auth::user();

        $receipt->forceFill([
            'created_by' => $nguoi?->id,
            'created_by_name' => $nguoi?->name,
        ])->save();

        return $receipt;
    }
}
