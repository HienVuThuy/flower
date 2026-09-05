<?php

namespace App\Services\Analytics;

use App\Enums\OrderStatus;
use App\Enums\UserEventType;
use App\Models\Order;
use App\Models\UserEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Truy vấn cho trang Phân tích của admin.
 * ============================================================
 * MỌI CON SỐ Ở ĐÂY ĐỀU ĐẾM TỪ CƠ SỞ DỮ LIỆU. Không ước lượng, không
 * sinh dữ liệu mẫu, không "tạm để đó cho đẹp". Chưa có dữ liệu thì trả
 * về rỗng và để giao diện nói thẳng là chưa có.
 *
 * HAI NGUỒN DỮ LIỆU, KHÔNG ĐƯỢC TRỘN:
 *
 *   `user_events`  — HÀNH VI. Là nhật ký những việc đã xảy ra: ai xem
 *                    gì, thêm gì vào giỏ. Bản ghi ở lại kể cả khi đơn
 *                    hàng tương ứng bị xoá.
 *
 *   `orders`       — TIỀN VÀ ĐƠN. Là sự thật hiện tại của cửa hàng.
 *
 * Vì sao phải tách: hiện có 8 sự kiện `purchase` nhưng 0 đơn hàng, do
 * các đơn thử nghiệm đã bị xoá còn nhật ký thì không. Nếu tính doanh thu
 * từ `meta` của sự kiện (trong đó có sẵn quantity và unit_price) thì sẽ
 * báo cáo doanh thu của những đơn KHÔNG CÒN TỒN TẠI — tức là bịa tiền.
 * Doanh thu chỉ được đọc từ bảng `orders`.
 *
 * TẤT CẢ TRUY VẤN GOM VỀ MỘT LỚP để controller mỏng, để hai màn hình
 * (Bảng điều khiển và Phân tích) dùng chung một định nghĩa cho cùng một
 * chỉ số, và để sau này đổi cách tính chỉ phải sửa một nơi.
 */
class AnalyticsService
{
    /** Các khoảng thời gian cho ô chọn. */
    public const PERIODS = [
        '7' => '7 ngày qua',
        '30' => '30 ngày qua',
        'all' => 'Toàn bộ',
    ];

    private ?Carbon $since = null;

    /**
     * Mốc kết thúc khoảng đang xét.
     *
     * null = tới hiện tại, tức là khoảng "gần đây" bình thường. Chỉ khác
     * null khi đang nhìn về KỲ TRƯỚC để so sánh.
     */
    private ?Carbon $until = null;

    /**
     * Chốt khoảng thời gian cho mọi truy vấn sau đó.
     *
     * Trả về chính nó để controller viết được một dòng:
     *     $analytics->forPeriod($period)->funnel()
     */
    public function forPeriod(string $period): static
    {
        $this->since = self::startOf($period);
        $this->until = null;

        return $this;
    }

    /**
     * Chuyển sang KỲ TRƯỚC, dài đúng bằng kỳ hiện tại.
     * ============================================================
     * "30 ngày qua" thành "30 ngày trước đó nữa", tức là từ ngày thứ 60
     * tới ngày thứ 30 tính ngược từ hôm nay.
     *
     * VÌ SAO CẦN: một con số trần trụi ("12 đơn") không nói lên điều gì.
     * Chỉ khi đặt cạnh kỳ trước ("12 đơn, kỳ trước 20") thì admin mới
     * biết cửa hàng đang lên hay đang xuống — và đó mới là câu hỏi thật
     * sự cần trả lời khi mở trang này.
     *
     * HAI ĐẦU KHOẢNG PHẢI KHỚP NHAU, nếu không so sánh là vô nghĩa: kỳ
     * trước phải dài đúng bằng kỳ này, không được là "toàn bộ những gì
     * trước đó".
     *
     * Kỳ 'all' KHÔNG có kỳ trước — không có gì nằm trước "toàn bộ". Lúc
     * đó hàm trả về false và giao diện tự ẩn phần so sánh, thay vì bịa ra
     * một mốc.
     */
    public function forPreviousPeriod(string $period): bool
    {
        $start = self::startOf($period);

        if ($start === null) {
            return false;
        }

        $length = $start->diffInSeconds(now());

        $this->until = $start;
        $this->since = $start->copy()->subSeconds((int) $length);

        return true;
    }

    /** Mốc bắt đầu của một kỳ, hoặc null với 'all'. */
    private static function startOf(string $period): ?Carbon
    {
        return match ($period) {
            '7' => now()->subDays(7)->startOfDay(),
            '30' => now()->subDays(30)->startOfDay(),
            default => null,
        };
    }

    /**
     * Phần trăm thay đổi giữa kỳ này và kỳ trước.
     *
     * Trả về null khi KHÔNG SO SÁNH ĐƯỢC, và đó là trường hợp phải xử lý
     * cẩn thận: kỳ trước bằng 0 thì mọi con số dương đều là "tăng vô
     * hạn". In ra "+∞%" hay "+100%" đều là bịa. null để giao diện nói
     * "kỳ trước chưa có dữ liệu" — đúng sự thật và hữu ích hơn.
     */
    public static function change(float $now, float $before): ?float
    {
        if ($before <= 0.0) {
            return null;
        }

        return round(($now - $before) / $before * 100, 1);
    }

    /* ================= HÀNH VI (user_events) ================= */

    /**
     * Phễu chuyển đổi: xem → thêm giỏ → mua.
     *
     * Đếm theo PHIÊN chứ không theo lượt.
     *
     * Đếm lượt sẽ cho ra tỷ lệ vô nghĩa: một người xem đi xem lại một
     * sản phẩm 20 lần rồi mua 1 lần thành "tỷ lệ chuyển đổi 5%", trong
     * khi thực tế người đó mua 100%. Phễu phải trả lời "bao nhiêu phiên
     * đi được tới bước này", đó mới là câu hỏi kinh doanh.
     *
     * @return array{views: int, carts: int, purchases: int,
     *               view_to_cart: float|null, cart_to_purchase: float|null,
     *               view_to_purchase: float|null}
     */
    public function funnel(): array
    {
        $views = $this->distinctSessions(UserEventType::ProductView);
        $carts = $this->distinctSessions(UserEventType::AddToCart);
        $purchases = $this->distinctSessions(UserEventType::Purchase);

        return [
            'views' => $views,
            'carts' => $carts,
            'purchases' => $purchases,
            'view_to_cart' => $this->rate($carts, $views),
            'cart_to_purchase' => $this->rate($purchases, $carts),
            'view_to_purchase' => $this->rate($purchases, $views),
        ];
    }

    /** Tổng số lượt (không phải số phiên) theo từng loại sự kiện. */
    public function eventTotals(): Collection
    {
        return $this->events()
            ->selectRaw('event_type, COUNT(*) as total')
            ->groupBy('event_type')
            ->pluck('total', 'event_type');
    }

    /**
     * Sản phẩm được quan tâm nhất theo một loại sự kiện.
     *
     * Nạp kèm `product` bằng một truy vấn (whereIn) thay vì để Blade tự
     * gọi — nếu không thì 10 dòng là 10 truy vấn.
     *
     * @return Collection<int, array{product: \App\Models\Product|null, total: int}>
     */
    public function topProducts(UserEventType $type, int $limit = 8): Collection
    {
        $rows = $this->events()
            ->where('event_type', $type)
            ->whereNotNull('product_id')
            ->selectRaw('product_id, COUNT(*) as total')
            ->groupBy('product_id')
            ->orderByDesc('total')
            ->limit($limit)
            ->get();

        $products = \App\Models\Product::whereIn('id', $rows->pluck('product_id'))
            ->get()
            ->keyBy('id');

        return $rows->map(fn ($r) => [
            // Sản phẩm có thể đã bị xoá — nhật ký vẫn còn. Để null và
            // giao diện tự hiển thị "(đã xoá)", không được nổ.
            'product' => $products->get($r->product_id),
            'total' => (int) $r->total,
        ]);
    }

    /** Danh mục được xem nhiều nhất. */
    public function topCategories(int $limit = 6): Collection
    {
        $rows = $this->events()
            ->whereIn('event_type', [UserEventType::ProductView, UserEventType::CategoryView])
            ->whereNotNull('category_id')
            ->selectRaw('category_id, COUNT(*) as total')
            ->groupBy('category_id')
            ->orderByDesc('total')
            ->limit($limit)
            ->get();

        $categories = \App\Models\Category::whereIn('id', $rows->pluck('category_id'))
            ->get()
            ->keyBy('id');

        return $rows->map(fn ($r) => [
            'category' => $categories->get($r->category_id),
            'total' => (int) $r->total,
        ]);
    }

    /**
     * Từ khoá khách tìm nhiều nhất.
     *
     * Từ khoá nằm trong cột JSON `meta->q`. Gom nhóm ở PHP chứ không ở
     * SQL: cú pháp truy vấn JSON khác nhau giữa MySQL và các hệ khác, mà
     * số dòng `search` luôn nhỏ hơn nhiều so với `product_view`.
     *
     * @return Collection<int, array{term: string, total: int}>
     */
    public function topSearches(int $limit = 10): Collection
    {
        $terms = $this->events()
            ->where('event_type', UserEventType::Search)
            ->pluck('meta')
            ->map(fn ($meta) => is_array($meta) ? trim((string) ($meta['q'] ?? '')) : '')
            ->filter()
            // Gộp "Hoa" với "hoa" — người tìm không phân biệt hoa thường.
            ->map(fn (string $q) => mb_strtolower($q));

        return $terms->countBy()
            ->sortDesc()
            ->take($limit)
            ->map(fn ($total, $term) => ['term' => $term, 'total' => $total])
            ->values();
    }

    /**
     * Hoạt động theo ngày, dùng để vẽ biểu đồ cột.
     *
     * ĐIỀN ĐỦ CẢ NHỮNG NGÀY KHÔNG CÓ SỰ KIỆN. Nếu chỉ trả về các ngày có
     * dữ liệu thì biểu đồ sẽ nối liền ngày 1 với ngày 5 như thể chúng
     * liền nhau — nhìn tưởng hoạt động đều, thực tế có ba ngày chết.
     *
     * @return Collection<int, array{date: string, label: string, total: int}>
     */
    public function dailyActivity(int $days = 14): Collection
    {
        $from = now()->subDays($days - 1)->startOfDay();

        $counts = UserEvent::where('created_at', '>=', $from)
            ->selectRaw('DATE(created_at) as d, COUNT(*) as total')
            ->groupBy('d')
            ->pluck('total', 'd');

        return collect(range(0, $days - 1))->map(function (int $i) use ($from, $counts) {
            $day = $from->copy()->addDays($i);
            $key = $day->toDateString();

            return [
                'date' => $key,
                'label' => $day->format('d/m'),
                'total' => (int) ($counts[$key] ?? 0),
            ];
        });
    }

    /* ================= TIỀN VÀ ĐƠN (orders) ================= */

    /**
     * Số liệu đơn hàng — đọc từ bảng `orders`, KHÔNG từ nhật ký sự kiện.
     *
     * Doanh thu chỉ tính đơn ĐÃ GIAO. Đơn đang xử lý chưa phải là tiền
     * đã thu; gộp vào là báo cáo doanh thu cao hơn sự thật.
     *
     * @return array{total: int, completed: int, cancelled: int,
     *               revenue: float, average: float|null}
     */
    public function orderStats(): array
    {
        $query = $this->applyWindow(Order::query(), 'created_at');

        $total = (clone $query)->count();
        $completed = (clone $query)->where('status', OrderStatus::Completed)->count();
        $cancelled = (clone $query)->where('status', OrderStatus::Cancelled)->count();
        $revenue = (float) (clone $query)->where('status', OrderStatus::Completed)->sum('grand_total');

        return [
            'total' => $total,
            'completed' => $completed,
            'cancelled' => $cancelled,
            'revenue' => $revenue,
            // Chia cho 0 là lỗi; chưa có đơn nào giao thì không có giá
            // trị trung bình, và null khác 0 — giao diện hiển thị khác nhau.
            'average' => $completed > 0 ? $revenue / $completed : null,
        ];
    }

    /**
     * Sản phẩm bán chạy — đọc từ order_items của đơn ĐÃ GIAO.
     *
     * Dùng tên đã chụp trong đơn (`product_name`) chứ không join sang
     * bảng products: đơn hàng là bản chụp tại thời điểm mua, và sản phẩm
     * có thể đã đổi tên hoặc bị xoá.
     */
    public function bestSellers(int $limit = 8): Collection
    {
        $query = \App\Models\OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.status', OrderStatus::Completed->value)
            ->whereNull('orders.deleted_at');

        $this->applyWindow($query, 'orders.created_at');

        /*
         * GOM THEO product_id, KHÔNG THEO TÊN.
         *
         * Tên trong `order_items` là BẢN CHỤP lúc đặt hàng — cố ý như
         * vậy để hoá đơn cũ không đổi khi cửa hàng sửa tên sản phẩm.
         * Nhưng gom nhóm theo nó thì:
         *
         *   - Đổi tên "Hoa hồng đỏ" thành "Hoa hồng đỏ Ecuador" là MỘT
         *     sản phẩm bị tách làm hai dòng, cả hai đều thấp hơn thực
         *     tế, và có thể rơi khỏi top.
         *   - Hai sản phẩm khác nhau từng trùng tên thì bị GỘP thành
         *     một — con số cao hơn sự thật.
         *
         * `product_id` không đổi theo tên. Sản phẩm bị xoá hẳn thì id
         * thành null; nhóm đó gom chung và hiện bằng tên chụp, xem
         * phần map bên dưới.
         *
         * MAX(product_name) chứ không phải product_name trần: chuẩn SQL
         * cấm chọn cột không nằm trong GROUP BY, và MySQL bật
         * ONLY_FULL_GROUP_BY sẽ báo lỗi. Lấy tên mới nhất trong nhóm là
         * đủ đúng — đó là tên khách thấy gần đây nhất.
         */
        return $query
            ->selectRaw(
                'order_items.product_id,'
                .' MAX(order_items.product_name) as product_name,'
                .' SUM(order_items.quantity) as qty,'
                .' SUM(order_items.line_total) as revenue'
            )
            ->groupBy('order_items.product_id')
            ->orderByDesc('qty')
            ->limit($limit)
            ->get()
            ->map(fn ($r) => [
                'name' => $r->product_name,
                'quantity' => (int) $r->qty,
                'revenue' => (float) $r->revenue,
            ]);
    }

    /* ================= HỖ TRỢ ================= */

    /** Câu truy vấn nền, đã áp khoảng thời gian đang chọn. */
    private function events()
    {
        return $this->applyWindow(UserEvent::query(), 'created_at');
    }

    /**
     * Áp khoảng thời gian đang chọn lên một câu truy vấn bất kỳ.
     *
     * MỘT NƠI DUY NHẤT áp cả hai đầu khoảng. Trước đây ba chỗ (events,
     * orderStats, bestSellers) mỗi chỗ tự viết `if ($this->since)`, và
     * khi thêm mốc kết thúc thì chỉ cần sót một chỗ là kỳ trước lấy nhầm
     * luôn cả dữ liệu của kỳ này — con số vẫn hiện ra bình thường, chỉ là
     * sai, nên không ai phát hiện.
     *
     * @param  string  $column  tên cột thời gian, có tiền tố bảng khi cần join
     */
    private function applyWindow(mixed $query, string $column): mixed
    {
        if ($this->since) {
            $query->where($column, '>=', $this->since);
        }

        if ($this->until) {
            // `<` chứ không phải `<=`: mốc kết thúc của kỳ trước CHÍNH LÀ
            // mốc bắt đầu của kỳ này. Dùng `<=` thì bản ghi rơi đúng vào
            // giây đó bị đếm ở cả hai kỳ.
            $query->where($column, '<', $this->until);
        }

        return $query;
    }

    private function distinctSessions(UserEventType $type): int
    {
        return (int) $this->events()
            ->where('event_type', $type)
            ->distinct()
            ->count('session_id');
    }

    /** Tỷ lệ phần trăm, hoặc null khi mẫu số bằng 0. */
    private function rate(int $part, int $whole): ?float
    {
        return $whole > 0 ? round($part / $whole * 100, 1) : null;
    }
}
