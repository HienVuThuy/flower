<?php

namespace App\Http\Requests\Admin\Concerns;

use App\Enums\CategoryKind;
use App\Enums\ProductType;
use App\Enums\SellingForm;
use App\Models\Category;
use Illuminate\Validation\Validator;

/** Ba trường của sản phẩm phải kể cùng một câu chuyện. */
trait ValidatesProductClassification
{
    protected function validateClassification(Validator $validator): void
    {
        $type = ProductType::tryFrom((string) $this->input('product_type'));

        if ($type === null) {
            return;
        }

        $this->kiemTraDanhMuc($validator, $type);
        $this->kiemTraHinhThucBan($validator, $type);
    }

    private function kiemTraDanhMuc(Validator $validator, ProductType $type): void
    {
        $category = Category::find($this->input('category_id'));

        if (! $category) {
            return;
        }

        $kind = $category->kind instanceof CategoryKind
            ? $category->kind
            : CategoryKind::tryFrom((string) $category->kind);

        if ($type->fitsCategoryKind($kind)) {
            return;
        }

        $validator->errors()->add('category_id', sprintf(
            'Danh mục "%s" thuộc nhóm %s nên không nhận sản phẩm loại "%s". '
            .'Hãy chọn danh mục khác hoặc đổi lại loại sản phẩm.',
            $category->name,
            ($kind ?? CategoryKind::Plant) === CategoryKind::Supply ? 'vật tư' : 'cây và hoa',
            $type->label(),
        ));
    }

    private function kiemTraHinhThucBan(Validator $validator, ProductType $type): void
    {
        $form = SellingForm::tryFrom((string) $this->input('selling_form'));

        if ($form === null || in_array($form, $type->allowedSellingForms(), true)) {
            return;
        }

        $validator->errors()->add('selling_form', sprintf(
            'Sản phẩm loại "%s" không bán dưới hình thức "%s". Hình thức phù hợp: %s.',
            $type->label(),
            $form->label(),
            implode(', ', array_map(
                fn (SellingForm $f) => $f->label(),
                $type->allowedSellingForms(),
            )),
        ));
    }
}
