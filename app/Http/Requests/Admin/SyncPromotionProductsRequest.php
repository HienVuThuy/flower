<?php

namespace App\Http\Requests\Admin;

use App\Enums\PromotionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Cập nhật danh sách sản phẩm áp dụng cho một chương trình, kèm mức giảm ghi đè cho từng sản phẩm (nếu có). */
class SyncPromotionProductsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'products' => ['nullable', 'array', 'max:500'],
            'products.*.id' => ['required', 'integer', 'exists:products,id'],

            'products.*.discount_type' => [
                'nullable',
                Rule::in(array_map(fn ($t) => $t->value, PromotionType::selectable())),
            ],

            'products.*.discount_value' => [
                'nullable', 'numeric', 'min:0', 'max:999999999999.99',
            ],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            foreach ((array) $this->input('products', []) as $i => $row) {
                $type = $row['discount_type'] ?? null;
                $value = $row['discount_value'] ?? null;

                if ($type && ($value === null || $value === '')) {
                    $validator->errors()->add(
                        "products.{$i}.discount_value",
                        'Đã chọn hình thức ghi đè thì phải nhập mức giảm.'
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
            'products.*.discount_value.numeric' => 'Mức giảm phải là số.',
            'products.*.discount_value.min' => 'Mức giảm không được âm.',
        ];
    }
}
