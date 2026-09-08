<?php

namespace App\Services\Invoice;

use App\Enums\InvoiceBuyerType;
use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\Order;
use Illuminate\Support\Str;

/**
 * NƠI DUY NHẤT dựng dữ liệu hoá đơn từ một đơn hàng.
 * ============================================================
 * ⚠️ DỰNG DỮ LIỆU, KHÔNG PHÁT HÀNH. Hoá đơn điện tử hợp lệ phải được
 * phát hành theo đúng quy trình và định dạng của quy định về hoá đơn
 * điện tử, thường qua một nhà cung cấp dịch vụ. Lớp này gom đủ và đúng
 * những trường mà hoá đơn cần, để khi cửa hàng ký hợp đồng thì chỉ việc
 * đẩy sang. Vì thế mọi hoá đơn tạo ra ở đây đều mang trạng thái `Draft`.
 *
 * ============================================================
 * MỌI CON SỐ ĐỌC TỪ BẢN CHỤP TRONG ĐƠN, không tính lại.
 *
 * Đơn còn sửa được (admin đổi phí giao, huỷ một dòng hàng), còn số trên
 * chứng từ thì phải đứng yên kể từ lúc lập. Tính lại mỗi lần đọc là để
 * một chứng từ tự đổi nội dung sau lưng người đã nhận nó.
 */
class InvoiceService
{
    /**
     * Lập dữ liệu hoá đơn cho một đơn.
     *
     * @param  array<string, mixed>  $checkout  dữ liệu bước thanh toán
     */
    public function taoTuDon(Order $order, array $checkout): ?Invoice
    {
        if (! ($checkout['want_invoice'] ?? false)) {
            return null;
        }

        /*
         * KHÔNG CÓ SỐ LIỆU THUẾ THÌ KHÔNG LẬP HOÁ ĐƠN.
         *
         * `tax_amount` NULL nghĩa là cửa hàng đang tắt tính thuế. Lập
         * một hoá đơn với tiền thuế 0₫ trong tình huống đó là ghi vào
         * chứng từ một điều chưa ai xác nhận — rằng đơn này không chịu
         * thuế. Thà không có hoá đơn còn hơn có một hoá đơn sai.
         */
        if ($order->tax_amount === null) {
            return null;
        }

        $loai = InvoiceBuyerType::tryFrom((string) ($checkout['invoice_buyer_type'] ?? ''))
            ?? InvoiceBuyerType::Personal;

        return Invoice::create([
            'order_id' => $order->id,
            'invoice_number' => $this->sinhSo(),
            'buyer_type' => $loai,

            /*
             * TÊN TRÊN HOÁ ĐƠN LÙI VỀ TÊN NGƯỜI NHẬN nếu khách bỏ trống.
             *
             * Biểu mẫu đã bắt buộc ô này khi tích lấy hoá đơn, nên đây
             * là lưới đỡ cho đường gọi từ mã nguồn khác — không phải chỗ
             * để nới lỏng quy tắc.
             */
            'buyer_name' => trim((string) ($checkout['invoice_buyer_name'] ?? '')) ?: $order->recipient_name,

            // Cá nhân không có mã số thuế: ghi NULL, không ghi chuỗi rỗng.
            'buyer_tax_code' => $loai->requiresTaxCode()
                ? (trim((string) ($checkout['invoice_tax_code'] ?? '')) ?: null)
                : null,

            'buyer_address' => trim((string) ($checkout['invoice_address'] ?? '')) ?: null,
            'buyer_email' => trim((string) ($checkout['invoice_email'] ?? '')) ?: null,

            /*
             * `subtotal` Ở ĐÂY LÀ TIỀN CHƯA THUẾ — khác `orders.subtotal`
             * (đã gồm thuế, vì giá niêm yết đã gồm thuế). Xem chú thích
             * ở App\Models\Invoice.
             */
            'subtotal' => $order->netTotal(),
            'tax_total' => $order->tax_amount,
            'grand_total' => $order->grand_total,

            // Bảng tách theo mức, chụp lại tại thời điểm lập.
            'rate_breakdown' => $order->taxByRate(),

            'status' => InvoiceStatus::Draft,
        ]);
    }

    /**
     * Số hiệu nội bộ dạng HD-260908-K3P9.
     *
     * KHÔNG dùng id tự tăng: cùng lý do với mã đơn hàng — nó để lộ số
     * lượng chứng từ và cho phép dò của người khác bằng cách đếm lên.
     *
     * ĐÂY KHÔNG PHẢI SỐ HOÁ ĐƠN THEO QUY ĐỊNH. Khi phát hành qua nhà
     * cung cấp, số thật do bên đó cấp và phải lưu ở một cột riêng để đối
     * chiếu được với số nội bộ này.
     */
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
