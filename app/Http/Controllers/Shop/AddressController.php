<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Http\Requests\Shop\AddressRequest;
use App\Models\Address;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/** Sổ địa chỉ của khách. */
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

        if ($wasDefault) {
            Auth::user()->addresses()->first()?->makeDefault();
        }

        return back()->with('success', 'Đã xoá địa chỉ.');
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
