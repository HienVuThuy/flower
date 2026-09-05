<?php

namespace App\Http\Requests\Admin\Concerns;

use App\Enums\CategoryKind;
use App\Enums\ProductType;
use App\Enums\SellingForm;
use App\Models\Category;
use Illuminate\Validation\Validator;

/**
 * Ba trường của sản phẩm phải kể cùng một câu chuyện.
 * ============================================================
 * category_id, product_type và selling_form đều hợp lệ khi xét RIÊNG
 * từng cái, nhưng ghép lại có thể vô nghĩa: một "cây cảnh" bán theo
 * "bó hoa", hay một bó hoa nằm trong danh mục vật tư.
 *
 * Không có gì ở tầng nào chặn được chuyện đó trước đây — biểu mẫu lưu
 * xong, không báo lỗi, và sản phẩm lặng lẽ nằm sai gian hàng hoặc mất
 * bộ thông tin chăm sóc của nó.
 *
 * DÙNG CHUNG CHO CẢ THÊM MỚI LẪN SỬA. Chép luật vào hai FormRequest thì
 * sớm muộn có một bên được sửa còn bên kia không — và đường "sửa sản
 * phẩm" mới chính là đường dễ tạo ra tổ hợp sai nhất, vì admin đổi một
 * trường mà quên hai trường kia.
 */
trait ValidatesProductClassification
{
    protected function validateClassification(Validator $validator): void
    {
        $type = ProductType::tryFrom((string) $this->input('product_type'));

        // Giá trị sai hẳn đã có rule 'in' báo rồi — không báo chồng lên
        // một lỗi thứ hai cho cùng một ô.
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
