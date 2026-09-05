<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Http\Requests\Shop\AddressRequest;
use App\Models\Address;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Sổ địa chỉ của khách.
 *
 * Toàn bộ nhóm route này nằm sau middleware auth, nhưng vẫn phải kiểm
 * tra CHỦ SỞ HỮU ở từng hành động: đăng nhập rồi không có nghĩa là được
 * sửa địa chỉ của người khác.
 */
class AddressController extends Controller
{
    public function index(): View
    {
        return view('shop.addresses.index', [
            'addresses' => Auth::user()->addresses,
        ]);
    }

    public function create(): View
    {
        return view('shop.addresses.form', [
            'address' => new Address(),
        ]);
    }

    public function store(AddressRequest $request): RedirectResponse
    {
        $address = Auth::user()->addresses()->create($request->validated());

        /*
         * Địa chỉ ĐẦU TIÊN luôn là mặc định, kể cả khách không tích ô.
         * Không có mặc định thì bước thanh toán không biết chọn cái nào.
         */
        if ($request->boolean('is_default') || Auth::user()->addresses()->count() === 1) {
            $address->makeDefault();
        }

        return redirect()
            ->route('shop.addresses.index')
            ->with('success', 'Đã lưu địa chỉ.');
    }

    public function edit(Address $address): View
    {
        $this->authorizeOwner($address);

        return view('shop.addresses.form', compact('address'));
    }

    public function update(AddressRequest $request, Address $address): RedirectResponse
    {
        $this->authorizeOwner($address);

        $address->update($request->validated());

        if ($request->boolean('is_default')) {
            $address->makeDefault();
        }

        return redirect()
            ->route('shop.addresses.index')
            ->with('success', 'Đã cập nhật địa chỉ.');
    }

    public function destroy(Address $address): RedirectResponse
    {
        $this->authorizeOwner($address);

        $wasDefault = $address->is_default;
        $address->delete();

        // Xoá đúng cái đang mặc định thì đưa mặc định sang địa chỉ còn lại.
        if ($wasDefault) {
            Auth::user()->addresses()->first()?->makeDefault();
        }

        return redirect()
            ->route('shop.addresses.index')
            ->with('success', 'Đã xoá địa chỉ.');
    }

    public function makeDefault(Address $address): RedirectResponse
    {
        $this->authorizeOwner($address);

        $address->makeDefault();

        return back()->with('success', 'Đã đặt làm địa chỉ mặc định.');
    }

    private function authorizeOwner(Address $address): void
    {
        abort_unless($address->user_id === Auth::id(), 403);
    }
}
