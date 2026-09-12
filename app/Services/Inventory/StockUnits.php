<?php

namespace App\Services\Inventory;

use App\Models\Product;
use Illuminate\Support\Collection;

/**
 * Mọi ĐƠN VỊ KHO có theo dõi tồn: (sản phẩm, quy cách) — cho các biểu mẫu
 * chứng từ kho (phiếu nhập, kiểm kê).
 *
 * Trước đây nằm riêng trong StockReceiptController. Phiếu kiểm kê cần đúng
 * danh sách này; chép sang là hai nơi quyết định "cái gì nhập được vào kho",
 * và một nơi sẽ quên bỏ hàng không theo dõi tồn.
 *
 * CHỈ HÀNG CÓ BẬT THEO DÕI TỒN: bày ra thứ mà lúc ghi sổ sẽ bị từ chối là để
 * người dùng gõ xong cả phiếu rồi mới biết mình chọn sai.
 *
 * ============================================================
 * HOA TƯƠI: BỎ Ở PHIẾU NHẬP, GIỮ Ở KIỂM KÊ.
 *
 * Đây là ranh giới giữa hai cách tính giá vốn, và để hở nó là ĐẾM HAI
 * LẦN — đúng lỗi đã suýt xảy ra: 9 sản phẩm hoa đang bật theo dõi tồn,
 * nên trước bản này có thể vừa lập phiếu nhập cho "Bó tulip Hà Lan" vừa
 * ghi lô hoa tulip, và hai con số cùng vào giá vốn ở hai báo cáo nằm
 * chung một trang.
 *
 * Ranh giới chính xác KHÔNG phải "hoa không có tồn kho" mà là:
 *
 *   - GIÁ VỐN của hoa đến từ LÔ, không đến từ phiếu nhập. Nên phiếu
 *     nhập (chứng từ vừa khai số lượng vừa khai tiền) không nhận hoa.
 *     Vả lại không ai "nhập kho" 14 bó tulip từ nhà cung cấp — bó là
 *     thứ cửa hàng tự bó ra từ cành.
 *
 *   - SỐ LƯỢNG BÁN ĐƯỢC của một bó làm sẵn thì vẫn đếm được, và vẫn nên
 *     đếm nếu cửa hàng muốn chặn bán quá. Nên KIỂM KÊ vẫn nhận hoa: nó
 *     chỉ sửa số lượng, không bao giờ đụng tới tiền.
 */
class StockUnits
{
    /**
     * @return Collection<int, array{value: string, label: string, product_id: int, variant_id: ?int,
     *                               ten: string, quy_cach: ?string, ton: int}>
     */
    /**
     * @param  bool  $boQuaHoa  bỏ hoa tươi ra khỏi danh sách (dùng cho
     *                          chứng từ có khai TIỀN — xem chú thích lớp)
     */
    public function danhSach(bool $boQuaHoa = false): Collection
    {
        return Product::query()
            ->with(['variants' => fn ($q) => $q->where('is_active', true)])
            ->when($boQuaHoa, fn ($q) => $q->where('product_type', '!=', \App\Enums\ProductType::Flower->value))
            ->orderBy('name')
            ->get()
            ->flatMap(function (Product $p) {
                if ($p->variants->isNotEmpty()) {
                    return $p->variants
                        ->filter(fn ($v) => $v->track_inventory)
                        ->map(fn ($v) => [
                            'value' => $p->id . ':' . $v->id,
                            'label' => $p->name . ' — ' . $v->name,
                            'product_id' => $p->id,
                            'variant_id' => $v->id,
                            'ten' => $p->name,
                            'quy_cach' => $v->name,
                            'ton' => (int) $v->stock_quantity,
                        ]);
                }

                if (! $p->track_inventory) {
                    return [];
                }

                return [[
                    'value' => $p->id . ':',
                    'label' => $p->name,
                    'product_id' => $p->id,
                    'variant_id' => null,
                    'ten' => $p->name,
                    'quy_cach' => null,
                    'ton' => (int) $p->stock_quantity,
                ]];
            })
            ->values();
    }
}
