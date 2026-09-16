<?php

namespace App\Http\Requests\Admin;

use Illuminate\Validation\Rule;

/** Sửa danh mục: cùng luật với thêm mới, trùng tên / slug thì bỏ qua chính danh mục đang sửa. */
class UpdateCategoryRequest extends StoreCategoryRequest
{
    public function rules(): array
    {
        $categoryId = $this->route('category')->id;

        return array_merge(parent::rules(), [
            'name' => ['required', 'string', 'max:150', Rule::unique('categories', 'name')->ignore($categoryId)],

            'slug' => ['required', 'string', 'max:180', Rule::unique('categories', 'slug')->ignore($categoryId)],
        ]);
    }
}
