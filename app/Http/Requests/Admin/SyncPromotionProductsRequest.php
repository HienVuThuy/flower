<?php

namespace App\Http\Requests\Admin;

use App\Enums\PromotionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Danh sách sản phẩm của một chương trình, mỗi dòng có thể có ưu đãi riêng: mức giảm khác hoặc quà riêng. */
class SyncPromotionProductsRequest extends FormRequest
{
    public const MA_QUA = '/^(vp:\d+|sp:\d+(:\d+)?)$/';

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'products' => ['nullable', 'array', 'max:500'],
            'products.*.id' => ['required', 'integer', 'distinct', 'exists:products,id'],

            'products.*.discount_type' => [
                'nullable',
                Rule::in(array_map(fn ($t) => $t->value, PromotionType::selectable())),
            ],

            'products.*.discount_value' => [
                'nullable', 'numeric', 'min:0', 'max:999999999999.99',
            ],

            'products.*.qua' => ['nullable', 'string', 'regex:' . self::MA_QUA],
            'products.*.gift_quantity' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $km = $this->route('promotion');

            foreach ((array) $this->input('products', []) as $i => $row) {
                $type = $row['discount_type'] ?? null;
                $value = $row['discount_value'] ?? null;
                $kieu = $type ? PromotionType::tryFrom($type) : $km?->type;

                if ($kieu === PromotionType::TangQua) {
                    if (($row['qua'] ?? '') === '' && $km?->gift_item_id === null) {
                        $validator->errors()->add(
                            "products.{$i}.qua",
                            'Chọn quà cho sản phẩm này (chương trình chưa có quà chung).'
                        );
                    }

                    continue;
                }

                if ($type && ($value === null || $value === '')) {
                    $validator->errors()->add(
                        "products.{$i}.discount_value",
                        'Đã chọn mức giảm riêng thì phải nhập mức giảm.'
                    );
                }

                if ($type === PromotionType::Percent->value && (float) $value > 100) {
                    $validator->errors()->add(
                        "products.{$i}.discount_value",
                        'Giảm theo phần trăm không được vượt quá 100%.'
                    );
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'products.*.id.exists' => 'Sản phẩm không tồn tại.',
            'products.*.id.distinct' => 'Một sản phẩm chỉ được thêm một lần.',
            'products.*.discount_value.numeric' => 'Mức giảm phải là số.',
            'products.*.discount_value.min' => 'Mức giảm không được âm.',
            'products.*.qua.regex' => 'Quà đã chọn không hợp lệ.',
        ];
    }
}
