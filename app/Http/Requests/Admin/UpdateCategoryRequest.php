<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $name = trim((string) $this->input('name'));

        $this->merge([
            'name' => $name,
            'slug' => $name !== ''
                ? Str::slug($name)
                : null,
        ]);
    }

    public function rules(): array
    {
        $categoryId = $this->route('category')->id;

        return [
            'name' => [
                'required',
                'string',
                'max:150',
                Rule::unique('categories', 'name')
                    ->ignore($categoryId),
            ],

            'slug' => [
                'required',
                'string',
                'max:180',
                Rule::unique('categories', 'slug')
                    ->ignore($categoryId),
            ],

            /*
             * NHÓM DANH MỤC: hoa & cây, hay phụ kiện & vật tư.
             *
             * Thiếu ô này thì mọi danh mục tạo từ trang quản trị đều là
             * "hoa & cây" — không tạo được danh mục cho trang /phu-kien.
             * Để trống được (bản ghi cũ); controller hiểu trống là giữ nguyên
             * khi sửa, là "hoa & cây" khi tạo mới.
             */
            'kind' => [
                'nullable',
                Rule::enum(\App\Enums\CategoryKind::class),
            ],

            'description' => [
                'nullable',
                'string',
                'max:1000',
            ],

            'image' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:2048',
            ],

            'is_active' => [
                'required',
                'boolean',
            ],

            'sort_order' => [
                'nullable',
                'integer',
                'min:0',
                'max:9999',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Vui lòng nhập tên danh mục.',
            'name.unique' => 'Tên danh mục này đã tồn tại.',

            'slug.required' => 'Không thể tạo slug cho danh mục.',
            'slug.unique' => 'Slug này đã tồn tại.',

            'description.max' => 'Mô tả không được vượt quá 1000 ký tự.',

            'image.image' => 'File tải lên phải là hình ảnh.',
            'image.mimes' => 'Ảnh phải có định dạng JPG, JPEG, PNG hoặc WEBP.',
            'image.max' => 'Ảnh không được lớn hơn 2MB.',

            'is_active.boolean' => 'Trạng thái không hợp lệ.',

            'sort_order.integer' => 'Thứ tự phải là số nguyên.',
            'sort_order.min' => 'Thứ tự không được nhỏ hơn 0.',
            'sort_order.max' => 'Thứ tự không được lớn hơn 9999.',
        ];
    }
}