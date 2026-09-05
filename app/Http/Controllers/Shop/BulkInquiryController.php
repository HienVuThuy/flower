<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBulkOrderInquiryRequest;
use App\Models\BulkOrderInquiry;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BulkInquiryController extends Controller
{
    public function create(Request $request): View
    {
        $product = null;

        if ($request->filled('product')) {
            $product = Product::query()
                ->where('slug', $request->string('product'))
                ->first();
        }

        return view('shop.bulk-inquiry.create', compact('product'));
    }

    public function store(StoreBulkOrderInquiryRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['user_id'] = $request->user()?->id;

        BulkOrderInquiry::create($data);

        return redirect()
            ->route('welcome')
            ->with('success', 'Đã gửi yêu cầu! Chúng tôi sẽ liên hệ lại với bạn sớm nhất.');
    }
}
