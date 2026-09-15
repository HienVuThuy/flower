<?php

namespace App\Services\Pricing;

use App\Enums\OrderStatus;
use App\Enums\UserEventType;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\UserEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Số liệu nhu cầu THẬT của từng sản phẩm trong một khoảng thời gian.
 * ============================================================
 * ⚠️ KHÔNG ĐỌC BẢNG NHẬT KÝ CÁ NHÂN. Lớp này chỉ đọc `user_events`,
 * `orders`, `order_items`, `products`. Xem QĐ-123 — và
 * `JournalPrivacyTest` canh điều đó ở tầng SQL.
 *
 * ============================================================
 * LỚP NÀY CHỈ ĐẾM, KHÔNG KHUYÊN.
 *
 * Tách đôi có chủ ý: đếm là việc của cơ sở dữ liệu và không có chỗ cho
 * ý kiến; khuyên là việc của `PricingAdvisor` và toàn là ý kiến. Trộn
 * hai thứ vào một lớp thì không còn phân biệt được "số liệu nói vậy" với
 * "chúng ta nghĩ vậy" — và khi admin hỏi "vì sao lại đề xuất giảm giá",
 * câu trả lời phải chỉ được vào một con số, không phải vào một đoạn mã.
 *
 * ============================================================
 * "BÁN ĐƯỢC" Ở ĐÂY LÀ ĐƠN ĐÃ ĐẶT, KHÔNG PHẢI ĐƠN ĐÃ GIAO.
 *
 * Khác với trang Phân tích (dùng `Completed` để tính doanh thu), và đó
 * là chủ ý: ở đây câu hỏi là "có bao nhiêu người MUỐN mua món này", mà
 * ý muốn thể hiện ngay lúc đặt. Một đơn đang trên đường giao vẫn là một
 * lần khách quyết định mua.
 *
 * Chỉ trừ đơn ĐÃ HUỶ — đơn huỷ là ý muốn đã rút lại.
 *
 * Vì hai trang trả lời hai câu hỏi khác nhau nên con số sẽ khác nhau, và
 * giao diện PHẢI ghi rõ "đơn đã đặt" để admin không tưởng một trong hai
 * đang sai.
 */
class DemandSignals
{
    /**
     * @param  int  $days  độ dài cửa sổ quan sát, tính bằng ngày
     */
    public function __construct(
        private readonly int $days = 30,
    ) {
    }

    public function days(): int
    {
        return $this->days;
    }

    public function since(): Carbon
    {
        return now()->subDays($this->days)->startOfDay();
    }

    /**
     * Số liệu cho MỌI sản phẩm đang bán, gộp từ bốn nguồn.
     *
     * BỐN TRUY VẤN GỘP THEO id, không phải bốn truy vấn MỖI SẢN PHẨM.
     * Cách kia đọc dễ hơn nhiều nhưng với vài chục sản phẩm là hơn hai
     * trăm lượt đi cơ sở dữ liệu cho một lần mở trang.
     *
     * @return Collection<int, ProductDemand>
     */
    public function all(): Collection
    {
        $tu = $this->since();

        $products = Product::query()
            // `promotions` cần cho ProductDemand::isDiscounted(); nạp ngay ở
            // đây để nó không phải tự nạp cho từng sản phẩm một.
            ->with(['category:id,name', 'promotions'])
            ->where('status', 'active')
            ->get([
                'id', 'category_id', 'name', 'slug', 'base_price',
                'track_inventory', 'stock_quantity', 'created_at',
            ]);

        if ($products->isEmpty()) {
            return collect();
        }

        $ids = $products->pluck('id')->all();

        $luotXem = $this->demSuKien($ids, UserEventType::ProductView, $tu);
        $themGio = $this->demSuKien($ids, UserEventType::AddToCart, $tu);
        $banRa = $this->banRa($ids, $tu);
        $banGanNhat = $this->banGanNhat($ids);

        return $products->map(fn (Product $p) => new ProductDemand(
            product: $p,
            views: (int) ($luotXem[$p->id] ?? 0),
            addToCarts: (int) ($themGio[$p->id] ?? 0),
            unitsSold: (int) ($banRa[$p->id]['qty'] ?? 0),
            ordersWith: (int) ($banRa[$p->id]['orders'] ?? 0),
            lastSoldAt: ($banGanNhat[$p->id] ?? null) ? Carbon::parse($banGanNhat[$p->id]) : null,
            windowDays: $this->days,
        ))->keyBy(fn (ProductDemand $d) => $d->product->id);
    }

    /* ================= TỪNG NGUỒN ================= */

    /**
     * @param  list<int>  $ids
     * @return array<int, int>
     */
    private function demSuKien(array $ids, UserEventType $loai, Carbon $tu): array
    {
        return UserEvent::query()
            ->whereIn('product_id', $ids)
            ->where('event_type', $loai->value)
            ->where('created_at', '>=', $tu)
            ->selectRaw('product_id, count(*) as c')
            ->groupBy('product_id')
            ->pluck('c', 'product_id')
            ->all();
    }

    /**
     * Số lượng bán ra và số đơn có chứa sản phẩm, trong cửa sổ.
     *
     * ĐẾM CẢ HAI vì chúng trả lời hai câu khác nhau: 20 cành hoa trong
     * một đơn duy nhất là một khách mua sỉ, còn 20 cành trong 20 đơn là
     * hai mươi người muốn. Chỉ nhìn số lượng thì hai tình huống đó giống
     * hệt nhau, và chúng gợi ý hai chính sách giá trái ngược.
     *
     * @param  list<int>  $ids
     * @return array<int, array{qty: int, orders: int}>
     */
    private function banRa(array $ids, Carbon $tu): array
    {
        $rows = OrderItem::query()
            ->hangBan() // quà tặng không phải nhu cầu mua — không kéo giá lên
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereIn('order_items.product_id', $ids)
            ->whereNull('orders.deleted_at')
            ->where('orders.status', '!=', OrderStatus::Cancelled->value)
            ->where('orders.created_at', '>=', $tu)
            ->selectRaw(
                'order_items.product_id,'
                .' SUM(order_items.quantity) as qty,'
                .' COUNT(DISTINCT order_items.order_id) as orders'
            )
            ->groupBy('order_items.product_id')
            ->get();

        $ket = [];

        foreach ($rows as $r) {
            $ket[(int) $r->product_id] = ['qty' => (int) $r->qty, 'orders' => (int) $r->orders];
        }

        return $ket;
    }

    /**
     * Lần bán gần nhất — KHÔNG giới hạn trong cửa sổ.
     *
     * Câu hỏi "món này nằm kho bao lâu rồi" chỉ có nghĩa khi nhìn ra
     * ngoài cửa sổ: một món không bán được suốt 30 ngày qua có thể vừa
     * bán hôm thứ 31, hoặc chưa bán lần nào từ ngày nhập. Hai chuyện đó
     * khác hẳn nhau và cần hai lời khuyên khác nhau.
     *
     * @param  list<int>  $ids
     * @return array<int, string|null>
     */
    private function banGanNhat(array $ids): array
    {
        return OrderItem::query()
            ->hangBan() // lần TẶNG gần nhất không phải lần bán gần nhất
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereIn('order_items.product_id', $ids)
            ->whereNull('orders.deleted_at')
            ->where('orders.status', '!=', OrderStatus::Cancelled->value)
            ->selectRaw('order_items.product_id, MAX(orders.created_at) as last_at')
            ->groupBy('order_items.product_id')
            ->pluck('last_at', 'product_id')
            ->all();
    }

    /**
     * Tổng số đơn (trừ đơn huỷ) trong cửa sổ — dùng để nói thẳng với
     * admin rằng mẫu đang lớn hay nhỏ.
     */
    public function totalOrders(): int
    {
        return Order::query()
            ->where('status', '!=', OrderStatus::Cancelled->value)
            ->where('created_at', '>=', $this->since())
            ->count();
    }
}
