<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Services\Shop\ImageCredits;
use Illuminate\View\View;

/**
 * Trang ghi công tác giả ảnh.
 *
 * Không phải trang trang trí: giấy phép CC BY và CC BY-SA của những ảnh
 * đang dùng BẮT BUỘC ghi tên tác giả ở nơi người xem thấy được. Xem
 * App\Services\Shop\ImageCredits.
 */
class CreditsController extends Controller
{
    public function __construct(
        private readonly ImageCredits $credits,
    ) {
    }

    public function index(): View
    {
        return view('shop.credits', [
            'groups' => $this->credits->grouped(),
            'total' => $this->credits->total(),
        ]);
    }
}
