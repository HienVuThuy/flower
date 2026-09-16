<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Services\Shop\DisplayScheme;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Đổi chế độ sáng/tối. */
class DisplaySchemeController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'che_do' => ['required', Rule::in(DisplayScheme::choices())],
        ]);

        return back()->withCookie(DisplayScheme::cookie($data['che_do']));
    }
}
