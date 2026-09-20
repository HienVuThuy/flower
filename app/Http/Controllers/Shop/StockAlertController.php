<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Catalog\StockAlertService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** Khách đăng ký / huỷ nhận báo khi sản phẩm có hàng lại. */
class StockAlertController extends Controller
{
    public function __construct(
        private readonly StockAlertService $bao,
    ) {
    }

    public function store(Request $request, Product $product): RedirectResponse
    {
        $quyCach = $this->quyCach($request, $product);

        if (! $this->bao->dangKyDuoc($product, $quyCach)) {
            return back()->with('error', 'Sản phẩm đang còn hàng — bạn mua được ngay.');
        }

        $this->bao->dangKy(Auth::user(), $product, $quyCach);

        return back()->with('success', 'Đã ghi nhận. Hàng về là cửa hàng báo bạn ngay, chỉ một lần.');
    }

    public function destroy(Request $request, Product $product): RedirectResponse
    {
        $this->bao->huy(Auth::user(), $product, $this->quyCach($request, $product));

        return back()->with('success', 'Đã bỏ nhận báo hàng về.');
    }

    private function quyCach(Request $request, Product $product): ?ProductVariant
    {
        $id = $request->input('product_variant_id');

        if ($id === null || $id === '') {
            return null;
        }

        return ProductVariant::where('product_id', $product->id)->findOrFail($id);
    }
}
