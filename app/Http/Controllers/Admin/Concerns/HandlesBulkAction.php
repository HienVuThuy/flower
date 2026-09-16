<?php

namespace App\Http\Controllers\Admin\Concerns;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Nền chung cho các thao tác hàng loạt ở khu quản trị. */
trait HandlesBulkAction
{
    protected function validateBulk(Request $request, array $viecChoPhep, string $bang): array
    {
        $data = $request->validate([
            'viec' => ['required', Rule::in($viecChoPhep)],

            'ids' => ['required', 'array', 'min:1'],

            'ids.*' => ['integer', Rule::exists($bang, 'id')],
        ], [
            'viec.required' => 'Hãy chọn một thao tác.',
            'viec.in' => 'Thao tác không hợp lệ.',
            'ids.required' => 'Hãy tích chọn ít nhất một dòng.',
            'ids.min' => 'Hãy tích chọn ít nhất một dòng.',
        ]);

        return [
            'viec' => $data['viec'],
            'ids' => array_values(array_unique(array_map('intval', $data['ids']))),
        ];
    }
}
