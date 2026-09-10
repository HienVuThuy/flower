<?php

namespace App\Services\Analytics;

use App\Enums\OrderStatus;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Support\Collection;

/**
 * Báo cáo tồn kho.
 * ============================================================
 * TRẢ LỜI BA CÂU HỎI mà bảng "sản phẩm" không trả lời được:
 *
 *   1. Sắp hết cái gì?      → còn bán được mấy ngày nữa
 *   2. Đang mất đơn ở đâu?  → hết hàng nhưng vẫn đang bày bán
 *   3. Tiền nằm chết ở đâu? → còn hàng nhưng cả kỳ không bán được cái nào
 *
 * Cột `stock_quantity` một mình không trả lời được câu nào trong ba câu
 * đó. "Còn 5" là nhiều hay ít phụ thuộc hoàn toàn vào bán được bao
 * nhiêu mỗi ngày: 5 chậu sen đá bán 3 cái/ngày là sắp hết, 5 bình gốm
 * bán 1 cái/tháng là thừa.
 *
 * ============================================================
 * ⚠️ KHÔNG TÍNH ĐƯỢC LỢI NHUẬN, VÀ KHÔNG BỊA RA.
 *
 * Cơ sở dữ liệu KHÔNG có giá vốn — bảng `products` chỉ có `base_price`
 * (giá bán). Vì thế:
 *
 *   - "Giá trị tồn kho" ở đây là theo GIÁ BÁN, không phải vốn bỏ ra.
 *   - Không có báo cáo lãi/lỗ, biên lợi nhuận, hay vòng quay vốn.
 *
 * Muốn có thì phải thêm cột giá vốn và nhập số thật vào. Ước lượng bằng
 * một tỉ lệ phần trăm nghĩ ra là bịa một con số kế toán, và nó sẽ được
 * dùng để ra quyết định.
 *
 * ============================================================
 * ĐƠN VỊ KHO LÀ (SẢN PHẨM, QUY CÁCH), không phải sản phẩm.
 *
 * "Lưỡi hổ mini" có hai chậu, mỗi chậu một kho riêng. Gom về một dòng
 * thì báo cáo nói "còn 12" trong khi chậu sứ đã hết sạch và khách không
 * mua được — đúng thứ báo cáo này sinh ra để phát hiện.
 */
class InventoryReport
{
    /** Số ngày dùng để tính tốc độ bán. */
    private int $ngay = 30;

    public function trongVong(int $ngay): static
    {
        $this->ngay = max(1, $ngay);

        return $this;
    }

    /**
     * Mọi đơn vị kho ĐANG THEO DÕI TỒN, kèm tốc độ bán.
     *
     * Bỏ qua thứ không theo dõi tồn (`track_inventory = false`): với
     * chúng, "còn bao nhiêu" không phải một câu hỏi có nghĩa — cửa hàng
     * cố ý khai là bán không giới hạn.
     *
     * @return Collection<int, array{
     *     product: Product, variant_id: ?int, name: string, variant: ?string,
     *     stock: int, sold: int, per_day: float, cover: ?float,
     *     price: float, value: float, selling: bool
     * }>
     */
    public function rows(): Collection
    {
        $daBan = $this->daBanTrongKy();

        $dong = collect();

        Product::query()
            ->with(['variants' => fn ($q) => $q->where('is_active', true)])
            ->chunk(200, function ($sanPham) use (&$dong, $daBan) {
                foreach ($sanPham as $p) {
                    $quyCach = $p->variants;

                    if ($quyCach->isNotEmpty()) {
                        foreach ($quyCach as $v) {
                            if (! $v->track_inventory) {
                                continue;
                            }

                            $dong->push($this->dungDong(
                                $p,
                                $v->id,
                                $v->name,
                                (int) $v->stock_quantity,
                                // Quy cách có giá riêng thì dùng giá đó;
                                // không thì lùi về giá sản phẩm.
                                (float) ($v->price ?? $p->base_price),
                                $daBan,
                            ));
                        }

                        continue;
                    }

                    if (! $p->track_inventory) {
                        continue;
                    }

                    $dong->push($this->dungDong($p, null, null, (int) $p->stock_quantity, (float) $p->base_price, $daBan));
                }
            });

        return $dong->values();
    }

    /**
     * @param  Collection<string, int>  $daBan
     */
    private function dungDong(Product $p, ?int $variantId, ?string $tenQuyCach, int $ton, float $gia, Collection $daBan): array
    {
        $ban = (int) ($daBan[$this->khoa($p->id, $variantId)] ?? 0);
        $moiNgay = $ban / $this->ngay;

        return [
            'product' => $p,
            'variant_id' => $variantId,
            'name' => $p->name,
            'variant' => $tenQuyCach,
            'stock' => $ton,
            'sold' => $ban,
            'per_day' => $moiNgay,

            /*
             * CÒN BÁN ĐƯỢC MẤY NGÀY NỮA.
             *
             * null khi CẢ KỲ KHÔNG BÁN ĐƯỢC CÁI NÀO — mẫu số bằng 0.
             * Trả về một số rất lớn ở đây thì hàng chết vốn lại đứng đầu
             * bảng "còn nhiều nhất", đúng chỗ nó không nên đứng. Và "vô
             * hạn ngày" là một câu vô nghĩa: không bán được thì không có
             * ngày nào để đếm.
             */
            'cover' => $moiNgay > 0 ? $ton / $moiNgay : null,

            'price' => $gia,
            'value' => $ton * $gia,

            // Còn bày bán hay không — quyết định việc hết hàng có đang
            // làm mất đơn hay không.
            'selling' => $p->status === 'published',
        ];
    }

    /**
     * Số lượng đã bán trong kỳ, theo từng đơn vị kho.
     *
     * CHỈ ĐƠN ĐÃ GIAO. Đơn đang xử lý có thể bị huỷ, và tính vào tốc độ
     * bán thì báo cáo giục nhập hàng cho những đơn chưa chắc có thật.
     *
     * @return Collection<string, int>
     */
    private function daBanTrongKy(): Collection
    {
        return OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.status', OrderStatus::Completed->value)
            ->whereNull('orders.deleted_at')
            ->where('orders.created_at', '>=', now()->subDays($this->ngay))
            ->selectRaw('order_items.product_id, order_items.product_variant_id, SUM(order_items.quantity) as sl')
            ->groupBy('order_items.product_id', 'order_items.product_variant_id')
            ->get()
            ->mapWithKeys(fn ($r) => [$this->khoa((int) $r->product_id, $r->product_variant_id ? (int) $r->product_variant_id : null) => (int) $r->sl]);
    }

    private function khoa(?int $productId, ?int $variantId): string
    {
        return $productId . ':' . ($variantId ?? '0');
    }

    /**
     * Sắp hết — xếp theo CÒN BÁN ĐƯỢC ÍT NGÀY NHẤT, không theo số lượng.
     *
     * Xếp theo `stock_quantity` tăng dần là cách xếp sai: "còn 2" của
     * món bán 5 cái/ngày gấp gáp hơn nhiều so với "còn 2" của món bán
     * một cái mỗi tháng, nhưng cả hai đứng cạnh nhau và trông như nhau.
     *
     * Món chưa bán được cái nào KHÔNG nằm ở đây — nó thuộc "chết vốn".
     */
    public function sapHet(float $nguong = 14, int $limit = 20): Collection
    {
        return $this->rows()
            ->filter(fn (array $r) => $r['cover'] !== null && $r['cover'] <= $nguong)
            ->sortBy('cover')
            ->take($limit)
            ->values();
    }

    /**
     * Hết hàng mà VẪN ĐANG BÀY BÁN — đang mất đơn ngay lúc này.
     *
     * Hết hàng của một sản phẩm đã ẩn thì không sao; hết hàng của một
     * sản phẩm khách vẫn bấm vào được mới là chuyện phải xử lý hôm nay.
     */
    public function daHet(int $limit = 50): Collection
    {
        return $this->rows()
            ->filter(fn (array $r) => $r['stock'] <= 0 && $r['selling'])
            // Món bán chạy mà hết thì mất nhiều đơn nhất — lên đầu.
            ->sortByDesc('sold')
            ->take($limit)
            ->values();
    }

    /**
     * Còn hàng nhưng cả kỳ KHÔNG bán được cái nào.
     *
     * Đây là tiền đang nằm im trên giá. Xếp theo giá trị giảm dần: món
     * đắt nằm chết đáng chú ý hơn món rẻ, dù cùng không bán được.
     */
    public function chetVon(int $limit = 20): Collection
    {
        return $this->rows()
            ->filter(fn (array $r) => $r['stock'] > 0 && $r['sold'] === 0)
            ->sortByDesc('value')
            ->take($limit)
            ->values();
    }

    /**
     * Mấy con số tổng.
     *
     * `value` là giá trị theo GIÁ BÁN, không phải vốn — cơ sở dữ liệu
     * không có giá vốn. Xem chú thích đầu lớp.
     *
     * @return array{units: int, skus: int, value: float, out: int, low: int, dead: int}
     */
    public function tongQuan(): array
    {
        $rows = $this->rows();

        return [
            'units' => (int) $rows->sum('stock'),
            'skus' => $rows->count(),
            'value' => (float) $rows->sum('value'),
            'out' => $rows->filter(fn ($r) => $r['stock'] <= 0 && $r['selling'])->count(),
            'low' => $rows->filter(fn ($r) => $r['cover'] !== null && $r['cover'] <= 14 && $r['stock'] > 0)->count(),
            'dead' => $rows->filter(fn ($r) => $r['stock'] > 0 && $r['sold'] === 0)->count(),
        ];
    }
}
