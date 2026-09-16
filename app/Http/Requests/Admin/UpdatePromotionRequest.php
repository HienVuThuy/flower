<?php

namespace App\Http\Requests\Admin;

use Illuminate\Validation\Rule;

/** Giống StorePromotionRequest, chỉ khác luật unique phải bỏ qua chính bản ghi đang sửa. */
class UpdatePromotionRequest extends StorePromotionRequest
{
    public function rules(): array
    {
        $rules = parent::rules();

        $rules['slug'] = [
            'required', 'string', 'max:200',
            Rule::unique('promotions', 'slug')->ignore($this->route('promotion')->id),
        ];

        return $rules;
    }
}
