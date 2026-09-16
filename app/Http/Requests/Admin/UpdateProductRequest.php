<?php

namespace App\Http\Requests\Admin;

use Illuminate\Validation\Rule;

/** Sửa sản phẩm: cùng luật với thêm mới, chỉ khác phần trùng tên và danh mục / nhóm thuế đang gắn. */
class UpdateProductRequest extends StoreProductRequest
{
    public function rules(): array
    {
        $product = $this->route('product');

        return array_merge(parent::rules(), [
            'category_id' => [
                'required',
                'integer',
                Rule::exists('categories', 'id')->where(fn ($q) => $q->where('is_active', true)->orWhere('id', $product?->category_id)),
            ],

            'tax_class_id' => [
                'nullable',
                'integer',
                Rule::exists('tax_classes', 'id')->where(fn ($q) => $q->where('is_active', true)->orWhere('id', $product?->tax_class_id)),
            ],

            'name' => ['required', 'string', 'max:180', Rule::unique('products', 'name')->ignore($product->id)],

            'slug' => ['required', 'string', 'max:220', Rule::unique('products', 'slug')->ignore($product->id)],

            'product_code' => ['required', 'string', 'max:80', Rule::unique('products', 'product_code')->ignore($product->id)],

            'variants.*.id' => ['nullable', 'integer'],
        ]);
    }

    public function messages(): array
    {
        return parent::messages() + [
            'variants.*.id.integer' => 'ID biến thể không hợp lệ.',
        ];
    }
}
