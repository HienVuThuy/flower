<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\Search\ProductSearch;
use App\Services\Search\SearchTerms;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Gợi ý sản phẩm cho ô tìm kiếm trên thanh đầu trang.
 * ============================================================
 * VÌ SAO NẰM TRONG routes/web.php CHỨ KHÔNG PHẢI routes/api.php:
 * Dự án này là ứng dụng Blade dùng session, không phải backend API.
 * Thêm routes/api.php sẽ kéo theo cả tầng xác thực bằng token (Sanctum)
 * cho đúng MỘT endpoint công khai chỉ đọc — nhiều thứ để cấu hình sai
 * hơn là để dùng. Đường dẫn vẫn đặt tiền tố /api/ để ai đọc route list
 * cũng biết ngay đây trả JSON, không trả HTML.
 *
 * KHÔNG LẶP LOGIC TÌM KIẾM: mọi phép chuẩn hoá, sửa lỗi gõ và xếp hạng
 * đều gọi vào App\Services\Search\ProductSearch — đúng lớp mà trang danh
 * sách sản phẩm dùng. Nhờ vậy thứ hiện trong danh sách gợi ý và thứ hiện
 * sau khi bấm Enter luôn là một; nếu viết lại truy vấn riêng ở đây thì
 * hai bên sẽ lệch nhau ngay lần đầu ai đó chỉnh một bên.
 */
class SearchSuggestionController extends Controller
{
    /** Số gợi ý tối đa. Nhiều hơn thì danh sách dài quá màn hình. */
    private const LIMIT = 6;

    /** Ngắn hơn thế này thì gợi ý gần như là toàn bộ catalog. */
    private const MIN_LENGTH = 2;

    public function __construct(
        private readonly ProductSearch $search,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $terms = $this->search->terms($request->query('q'));

        $raw = trim((string) $request->query('q'));

        if ($terms->isEmpty() || mb_strlen($raw) < self::MIN_LENGTH) {
            return $this->reply($terms, []);
        }

        $query = Product::query()
            ->with(['category', 'promotions'])
            ->whereIn('status', ['active', 'out_of_stock']);

        $this->search->filter($query, $terms);
        $this->search->orderByRelevance($query, $terms);

        $products = $query->limit(self::LIMIT)->get();

        /*
         * Nới lỏng y như trang danh sách.
         *
         * Không có bước này thì gõ "hoa bon" trong ô tìm kiếm sẽ thấy
         * danh sách gợi ý trống, rồi bấm Enter lại ra đầy sản phẩm —
         * người dùng sẽ kết luận là ô gợi ý bị hỏng.
         */
        if ($products->isEmpty() && $terms->hasMultipleTokens()) {
            $relaxed = Product::query()
                ->with(['category', 'promotions'])
                ->whereIn('status', ['active', 'out_of_stock']);

            $this->search->filter($relaxed, $terms, matchAll: false);
            $this->search->orderByRelevance($relaxed, $terms);

            $products = $relaxed->limit(self::LIMIT)->get();
        }

        return $this->reply($terms, $products->all());
    }

    /**
     * @param  list<Product>  $products
     */
    private function reply(SearchTerms $terms, array $products): JsonResponse
    {
        return response()->json([
            'query' => $terms->original,

            // Để giao diện nói được "đang hiển thị kết quả cho ...".
            // Cùng nguyên tắc với trang danh sách: đã sửa chữ của khách
            // thì phải nói ra.
            'corrected' => $terms->wasCorrected() ? $terms->suggestion() : null,
            'alternative' => $terms->alternative,

            'items' => array_map(fn (Product $p) => [
                'name' => $p->name,
                'category' => $p->category?->name,
                'url' => route('shop.products.show', $p),
                /*
                 * Ảnh và giá dựng SẴN Ở MÁY CHỦ.
                 *
                 * Nếu trả về đường dẫn thô và giá thô rồi để JavaScript
                 * tự ghép, thì quy tắc ghép ảnh và quy tắc định dạng tiền
                 * sẽ tồn tại ở hai nơi — một bản trong Blade, một bản
                 * trong JS. Hai bản đó chắc chắn sẽ lệch nhau.
                 */
                'image' => $p->main_image ? asset('storage/'.$p->main_image) : null,
                'price' => $this->money($p->currentPrice()),
                'inStock' => $p->inStock(),
            ], $products),
        ]);
    }

    private function money(?string $amount): ?string
    {
        return $amount === null
            ? null
            : number_format((float) $amount, 0, ',', '.').'đ';
    }
}
