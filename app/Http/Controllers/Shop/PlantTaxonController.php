<?php

namespace App\Http\Controllers\Shop;

use App\Enums\TaxonRank;
use App\Http\Controllers\Controller;
use App\Models\PlantTaxon;
use App\Models\Product;
use Illuminate\View\View;

/**
 * Duyệt cây theo phân loại sinh học.
 * ============================================================
 * VÌ SAO TÁCH KHỎI "DANH MỤC": hai cách chia hoàn toàn khác nhau, và
 * gộp lại thì cả hai đều hỏng.
 *
 *   - Danh mục là cách CỬA HÀNG bày hàng: "Hoa cưới", "Hoa quà tặng",
 *     "Cây để bàn". Nó theo DỊP MUA và có thể đổi theo mùa.
 *   - Phân loại sinh học là cách THIÊN NHIÊN xếp: Họ Ráy, Chi Monstera.
 *     Nó không đổi theo cửa hàng và không quan tâm ai bán gì.
 *
 * Cùng một cây nằm ở cả hai chỗ, ở hai vị trí không liên quan gì nhau:
 * "Trầu bà leo cột" thuộc danh mục *Cây cảnh* và thuộc *Họ Ráy*. Ép
 * chúng thành một cây duy nhất là buộc phải chọn bỏ một trong hai cách
 * tìm.
 *
 * ============================================================
 * AI DÙNG TRANG NÀY: người mua cây theo hiểu biết chứ không theo dịp —
 * "tôi có ba cây họ Ráy rồi, cho tôi xem còn cây nào cùng họ", "nhà tôi
 * hợp cây mọng nước". Họ là số ít, nhưng họ mua nhiều và mua đúng.
 */
class PlantTaxonController extends Controller
{
    /**
     * Trang gốc: các Ngành, và những Họ có nhiều hàng nhất.
     *
     * KHÔNG ĐỔ CẢ CÂY 66 NÚT RA MÀN HÌNH. Bắt đầu bằng ba Ngành là ba
     * lựa chọn đọc hết trong ba giây; đổ hết ra là bắt người ta đọc một
     * danh sách dài để tìm chỗ bắt đầu.
     */
    public function index(): View
    {
        $goc = PlantTaxon::roots()->with('children')->get();

        /*
         * Lối tắt tới bậc HỌ — bậc hữu ích nhất cho người mua.
         *
         * Ngành và Lớp quá rộng (gần như mọi cây đều là "Hạt kín"), Chi
         * và Loài quá hẹp (một chi thường chỉ có một sản phẩm). Họ là
         * bậc mà "cho tôi xem cây cùng nhóm" bắt đầu có nghĩa.
         */
        $ho = PlantTaxon::rank(TaxonRank::Family)
            ->orderBy('name')
            ->get()
            ->map(fn (PlantTaxon $t) => [
                'taxon' => $t,
                'soSanPham' => $this->demSanPham($t),
            ])
            // Họ không có hàng nào thì không hiện: một lựa chọn dẫn tới
            // màn hình trống là một lựa chọn không nên bày ra.
            ->filter(fn (array $r) => $r['soSanPham'] > 0)
            ->sortByDesc('soSanPham')
            ->values();

        return view('shop.taxa.index', [
            'goc' => $goc,
            'ho' => $ho,
        ]);
    }

    /**
     * Một nút: đường dẫn phân loại, các nhánh con, và hàng đang bán.
     */
    public function show(PlantTaxon $taxon): View
    {
        $products = Product::query()
            ->with(['category', 'promotions',
                'variants' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')])
            ->withAvg(['reviews as rating_avg' => fn ($q) => $q->visible()], 'rating')
            ->withCount(['reviews as rating_count' => fn ($q) => $q->visible()])
            ->mainCatalog()
            ->whereIn('status', ['active', 'out_of_stock'])
            ->inTaxon($taxon)
            ->latest()
            ->paginate(12)
            ->withQueryString();

        /*
         * Đếm hàng cho từng nhánh con NGAY Ở ĐÂY, không để view tự hỏi.
         *
         * View gọi trong vòng lặp là N+1, và mỗi lần đếm còn kéo theo
         * một lượt dựng lại cây con — đắt gấp đôi một truy vấn thường.
         */
        $nhanhCon = $taxon->children->map(fn (PlantTaxon $con) => [
            'taxon' => $con,
            'soSanPham' => $this->demSanPham($con),
        ]);

        return view('shop.taxa.show', [
            'taxon' => $taxon,
            'chain' => $taxon->chain(),
            'nhanhCon' => $nhanhCon,
            'products' => $products,
        ]);
    }

    /** Số hàng đang bán trong CẢ NHÁNH của một nút. */
    private function demSanPham(PlantTaxon $taxon): int
    {
        return Product::query()
            ->mainCatalog()
            ->whereIn('status', ['active', 'out_of_stock'])
            ->inTaxon($taxon)
            ->count();
    }
}
