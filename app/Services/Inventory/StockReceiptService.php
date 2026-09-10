<?php

namespace App\Services\Inventory;

use App\Enums\StockReceiptStatus;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockReceipt;
use App\Services\Audit\ActivityLogger;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * NƠI DUY NHẤT cộng hàng vào kho.
 * ============================================================
 * Trước đây kho chỉ đổi qua ô nhập số ở trang sản phẩm — một phép GÁN
 * ĐÈ. Xem chú thích dài ở migration create_stock_receipts_tables.
 *
 * ============================================================
 * BA LUẬT KHÔNG ĐƯỢC PHÁ:
 *
 *   1. CỘNG THÊM, KHÔNG GÁN ĐÈ. `increment()` chứ không `update(['stock'
 *      => $n])`: giữa lúc admin mở phiếu và lúc bấm ghi sổ, có thể đã có
 *      đơn hàng trừ kho. Gán đè là xoá luôn phần đã bán đó.
 *
 *   2. GHI SỔ ĐÚNG MỘT LẦN. Bấm hai lần vì trang chậm là cộng kho hai
 *      lần — và không có gì trên màn hình cho thấy điều đó.
 *
 *   3. CẢ PHIẾU TRONG MỘT TRANSACTION. Phiếu 10 dòng mà lỗi ở dòng thứ
 *      7 thì sáu dòng đầu đã cộng vào kho, phiếu vẫn là nháp, và bấm lại
 *      sẽ cộng sáu dòng đó lần nữa.
 */
class StockReceiptService
{
    public function __construct(
        private readonly ActivityLogger $audit,
    ) {
    }

    /**
     * Mã phiếu dạng NK-260928-A3F2.
     *
     * Không dùng id tự tăng làm mã: nó để lộ tổng số phiếu, và không đọc
     * được qua điện thoại khi gọi cho nhà cung cấp.
     */
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

    /**
     * GHI SỔ: cộng từng dòng vào kho, rồi đánh dấu phiếu đã ghi.
     *
     * @throws StockReceiptException
     */
    public function ghiSo(StockReceipt $receipt): void
    {
        DB::transaction(function () use ($receipt) {
            /*
             * KHOÁ PHIẾU RỒI ĐỌC LẠI, TRƯỚC KHI KIỂM.
             *
             * Phép kiểm "đã ghi sổ chưa" dựa vào bản đã nạp từ trước là
             * vô nghĩa khi hai request cùng chạy:
             *
             *   A đọc "draft"  -> được ghi sổ
             *   B đọc "draft"  -> được ghi sổ
             *   A cộng kho, đánh dấu posted
             *   B cộng kho LẦN NỮA, đánh dấu posted
             *
             * Kho cộng gấp đôi cho một phiếu. Admin bấm hai lần vì trang
             * chậm là đủ để tái hiện. CƠ SỞ DỮ LIỆU là nơi phân xử.
             */
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

            foreach ($receipt->items as $dong) {
                $this->congVaoKho($dong->product_variant_id, $dong->product_id, $dong->quantity, $dong->product_name);
            }

            $receipt->forceFill([
                'status' => StockReceiptStatus::Posted,
                'posted_at' => now(),
            ])->save();
        });

        /*
         * NHẬT KÝ SAU TRANSACTION — nó không được quyền làm hỏng việc
         * chính. ActivityLogger đã tự nuốt lỗi, chỗ này chỉ cần đúng thứ
         * tự.
         */
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

    /**
     * Cộng một dòng vào đúng chỗ giữ tồn.
     *
     * QUY CÁCH GIỮ TỒN RIÊNG. Sản phẩm có quy cách thì `products
     * .stock_quantity` không phải thứ khách mua — cộng vào đó là cộng
     * vào một con số không ai đọc, còn quy cách thì vẫn hết hàng.
     */
    private function congVaoKho(?int $variantId, ?int $productId, int $soLuong, string $ten): void
    {
        if ($soLuong === 0) {
            return;
        }

        if ($variantId !== null) {
            $so = ProductVariant::whereKey($variantId)->lockForUpdate()->first();

            if (! $so) {
                throw new StockReceiptException(sprintf('Quy cách của "%s" không còn tồn tại.', $ten));
            }

            /*
             * KHÔNG THEO DÕI TỒN THÌ KHÔNG CỘNG.
             *
             * Cửa hàng cố ý khai mặt hàng này bán không giới hạn. Cộng
             * vào một cột không ai đọc là ghi một con số vô nghĩa, và
             * người lập phiếu tưởng mình vừa nhập kho xong.
             */
            if (! $so->track_inventory) {
                throw new StockReceiptException(sprintf(
                    'Quy cách "%s" không bật theo dõi tồn kho nên không nhập kho được.',
                    $so->name,
                ));
            }

            $so->increment('stock_quantity', $soLuong);

            return;
        }

        $sp = Product::whereKey($productId)->lockForUpdate()->first();

        if (! $sp) {
            throw new StockReceiptException(sprintf('Sản phẩm "%s" không còn tồn tại.', $ten));
        }

        if ($sp->variants()->where('is_active', true)->exists()) {
            throw new StockReceiptException(sprintf(
                'Sản phẩm "%s" có quy cách — phải chọn quy cách cụ thể để nhập.',
                $sp->name,
            ));
        }

        if (! $sp->track_inventory) {
            throw new StockReceiptException(sprintf(
                'Sản phẩm "%s" không bật theo dõi tồn kho nên không nhập kho được.',
                $sp->name,
            ));
        }

        $sp->increment('stock_quantity', $soLuong);
    }

    /**
     * Ghi lại ai lập phiếu, ngay lúc tạo.
     *
     * CHỤP CẢ TÊN, không chỉ id: xoá tài khoản thì khoá ngoại thành NULL
     * và dòng chứng từ mất hết ý nghĩa nếu không có tên.
     */
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
