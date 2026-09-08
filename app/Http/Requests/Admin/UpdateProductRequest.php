<?php

namespace App\Http\Requests\Admin;

use App\Enums\ProductType;
use App\Http\Requests\Admin\Concerns\ValidatesProductClassification;
use App\Enums\SellingForm;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    use ValidatesProductClassification;

    public function authorize(): bool
    {
        return true;
    }


    protected function prepareForValidation(): void
    {
        $name = trim(
            (string) $this->input('name')
        );

        $this->merge([
            'name' => $name,

            'slug' => $name !== ''
                ? Str::slug($name)
                : null,
        ]);
        /*
         * Lọc care_info theo HÌNH THỨC BÁN.
         *
         * Guide mục 4.4: bó hoa và cây chậu không dùng chung một bộ
         * thuộc tính chăm sóc. Không lọc thì đổi một cây chậu thành bó
         * hoa vẫn giữ nguyên các ô ánh sáng/đất/phân bón cũ — đúng lỗi
         * đang tồn tại trong cơ sở dữ liệu hiện tại.
         *
         * Lọc ở SERVER, không tin việc JavaScript đã ẩn ô ở trình duyệt.
         */
        $care = $this->input('care_info');

        if (is_array($care)) {
            $allowed = SellingForm::tryFrom((string) $this->input('selling_form'))
                ?->careProfile()
                ->keys() ?? [];

            $this->merge([
                'care_info' => array_intersect_key($care, array_flip($allowed)),
            ]);
        }
    }



    public function rules(): array
    {
        $product = $this->route('product');

        return [

            /*
             * =========================
             * PRODUCT
             * =========================
             */

            'category_id' => [
                'required',
                'integer',
                'exists:categories,id',
            ],

            /*
             * NHÓM THUẾ SUẤT — để trống là hợp lệ.
             *
             * Trống nghĩa là "dùng mức mặc định của cửa hàng", không
             * phải dữ liệu thiếu. Nhưng CÓ giá trị thì phải là một dòng
             * có thật: một id bịa trên biểu mẫu không được phép chui vào
             * cột khoá ngoại rồi làm hỏng phép tính thuế của mọi đơn sau
             * đó.
             */
            'tax_class_id' => [
                'nullable',
                'integer',
                'exists:tax_classes,id',
            ],

            'name' => [
                'required',
                'string',
                'max:180',

                Rule::unique(
                    'products',
                    'name'
                )->ignore($product->id),
            ],

            'slug' => [
                'required',
                'string',
                'max:220',

                Rule::unique(
                    'products',
                    'slug'
                )->ignore($product->id),
            ],

            'product_code' => [
                'required',
                'string',
                'max:80',

                Rule::unique(
                    'products',
                    'product_code'
                )->ignore($product->id),
            ],

            'short_description' => [
                'nullable',
                'string',
                'max:500',
            ],

            'description' => [
                'nullable',
                'string',
            ],

            'product_type' => [
                'required',

                // Danh sách từ enum, không chép tay. Bản cũ ở đây còn
                // 'gift'/'event'/'wedding' — thuộc trục dịp, xem QĐ-08.
                Rule::in(ProductType::values()),
            ],

            'selling_form' => [
                'required',

                Rule::in(SellingForm::values()),
            ],

            'base_price' => [
                'nullable',
                'numeric',
                'min:0',
                'max:999999999999.99',
            ],


            'track_inventory' => [
                'required',
                'boolean',
            ],

            'stock_quantity' => [
                'nullable',
                'integer',
                'min:0',
                'max:1000000',
            ],

            /*
             * Chỉ chấp nhận đúng những khoá thuộc hồ sơ chăm sóc của
             * hình thức bán đang chọn (Guide mục 4.4). Gửi khoá lạ, hoặc
             * gửi khoá của cây chậu cho một bó hoa, đều bị loại ở
             * prepareForValidation() bên dưới.
             */
            'care_info' => ['nullable', 'array'],
            'care_info.light' => ['nullable', 'string', 'max:255'],
            'care_info.water' => ['nullable', 'string', 'max:255'],
            'care_info.soil' => ['nullable', 'string', 'max:255'],
            'care_info.fertilizer' => ['nullable', 'string', 'max:255'],
            'care_info.temperature' => ['nullable', 'string', 'max:255'],
            'care_info.position' => ['nullable', 'string', 'max:255'],
            'care_info.frequency' => ['nullable', 'string', 'max:255'],
            // Danh sach do kho lay tu App\Enums\CareDifficulty — mot noi duy nhat.
            'care_info.difficulty' => ['nullable', Rule::in(\App\Enums\CareDifficulty::values())],
            'care_info.water_change' => ['nullable', 'string', 'max:255'],
            'care_info.trim' => ['nullable', 'string', 'max:255'],
            'care_info.placement' => ['nullable', 'string', 'max:255'],
            'care_info.lifespan' => ['nullable', 'string', 'max:255'],
            'care_info.notes' => ['nullable', 'string', 'max:1000'],

            /*
             * Chu kỳ nhắc chăm sóc — SỐ NGÀY, không phải chữ.
             * Bỏ trống thì sản phẩm không sinh lịch nhắc nào.
             * max:365 chặn kiểu gõ nhầm 3000 thay vì 30.
             */
            'care_info.water_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'care_info.fertilizer_days' => ['nullable', 'integer', 'min:1', 'max:365'],

            /*
             * NHÃN PHÂN LOẠI — giống hệt StoreProductRequest.
             * Chỉ kiểm tra khung; giá trị hợp lệ do ProductTrait::isValid()
             * quyết định lúc ghi, vì tập giá trị phụ thuộc loại nhãn.
             */
            'traits' => ['nullable', 'array'],
            'traits.*' => ['nullable', 'array'],
            'traits.*.*' => ['string', 'max:32'],

            'main_image' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:4096',
            ],

            // Ảnh phụ cho gallery ở trang chi tiết.
            'gallery' => [
                'nullable',
                'array',
                'max:8',
            ],

            'gallery.*' => [
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:4096',
            ],

            // Danh sách id ảnh phụ admin bấm xoá.
            'remove_images' => [
                'nullable',
                'array',
            ],

            'remove_images.*' => [
                'integer',
            ],

            'status' => [
                'required',

                Rule::in([
                    'draft',
                    'active',
                    'inactive',
                    'out_of_stock',
                ]),
            ],

            'meta_title' => [
                'nullable',
                'string',
                'max:255',
            ],

            'meta_description' => [
                'nullable',
                'string',
                'max:500',
            ],


            /*
             * =========================
             * VARIANTS
             * =========================
             */

            'variants' => [
                'nullable',
                'array',
                'max:50',
            ],

            'variants.*.id' => [
                'nullable',
                'integer',
            ],

            'variants.*.name' => [
                'required',
                'string',
                'max:180',
            ],

            'variants.*.code' => [
                'nullable',
                'string',
                'max:80',
            ],

            'variants.*.price' => [
                'nullable',
                'numeric',
                'min:0',
                'max:999999999999.99',
            ],

            'variants.*.description' => [
                'nullable',
                'string',
                'max:2000',
            ],

            'variants.*.is_active' => [
                'required',
                'boolean',
            ],

            'variants.*.sort_order' => [
                'required',
                'integer',
                'min:0',
            ],

            'variants.*.stock_quantity' => [
                'nullable',
                'integer',
                'min:0',
                'max:1000000',
            ],

            'variants.*._delete' => [
                'nullable',
                'boolean',
            ],
        ];
    }


    /**
     * Luật LIÊN TRƯỜNG — chạy sau khi từng ô đã hợp lệ.
     *
     * Đặt ở after() chứ không ở rules(): tới đây mới chắc chắn cả ba ô
     * đều có giá trị đọc được, nên thông báo lỗi nói được đúng tổ hợp
     * nào đang sai, thay vì bảo "trường này không hợp lệ".
     */
    public function after(): array
    {
        return [
            fn ($validator) => $this->validateClassification($validator),
        ];
    }

    public function messages(): array
    {
        return [

            /*
             * PRODUCT
             */

            'category_id.required' =>
                'Vui lòng chọn danh mục.',

            'category_id.exists' =>
                'Danh mục không tồn tại.',

            'tax_class_id.exists' =>
                'Nhóm thuế suất không tồn tại.',

            'name.required' =>
                'Vui lòng nhập tên sản phẩm.',

            'name.unique' =>
                'Tên sản phẩm này đã tồn tại.',

            'slug.required' =>
                'Không thể tạo slug cho sản phẩm.',

            'slug.unique' =>
                'Slug này đã tồn tại.',

            'product_code.required' =>
                'Vui lòng nhập mã sản phẩm.',

            'product_code.unique' =>
                'Mã sản phẩm này đã tồn tại.',

            'product_type.required' =>
                'Vui lòng chọn loại sản phẩm.',

            'product_type.in' =>
                'Loại sản phẩm không hợp lệ.',

            'selling_form.required' =>
                'Vui lòng chọn hình thức bán.',

            'selling_form.in' =>
                'Hình thức bán không hợp lệ.',

            'base_price.numeric' =>
                'Giá phải là số.',

            'base_price.min' =>
                'Giá không được âm.',




            'track_inventory.required' =>
                'Vui lòng chọn có quản lý tồn kho hay không.',

            'stock_quantity.integer' =>
                'Tồn kho phải là số nguyên.',

            'stock_quantity.min' =>
                'Tồn kho không được âm.',

            'care_info.difficulty.in' =>
                'Độ khó chăm sóc không hợp lệ.',

            'main_image.image' =>
                'File phải là hình ảnh.',

            'main_image.mimes' =>
                'Ảnh phải có định dạng JPG, JPEG, PNG hoặc WEBP.',

            'main_image.max' =>
                'Ảnh không được lớn hơn 4MB.',

            'gallery.max' =>
                'Chỉ được tải lên tối đa 8 ảnh phụ mỗi lần.',

            'gallery.*.image' =>
                'File trong thư viện ảnh phải là hình ảnh.',

            'gallery.*.mimes' =>
                'Ảnh phụ phải có định dạng JPG, JPEG, PNG hoặc WEBP.',

            'gallery.*.max' =>
                'Mỗi ảnh phụ không được lớn hơn 4MB.',

            'status.required' =>
                'Vui lòng chọn trạng thái.',

            'status.in' =>
                'Trạng thái không hợp lệ.',


            /*
             * VARIANTS
             */

            'variants.array' =>
                'Dữ liệu biến thể không hợp lệ.',

            'variants.max' =>
                'Một sản phẩm chỉ được có tối đa 50 biến thể.',

            'variants.*.id.integer' =>
                'ID biến thể không hợp lệ.',

            'variants.*.name.required' =>
                'Vui lòng nhập tên biến thể.',

            'variants.*.name.max' =>
                'Tên biến thể không được vượt quá 180 ký tự.',

            'variants.*.code.max' =>
                'Mã biến thể không được vượt quá 80 ký tự.',

            'variants.*.price.numeric' =>
                'Giá biến thể phải là số.',

            'variants.*.price.min' =>
                'Giá biến thể không được âm.',

            'variants.*.description.max' =>
                'Mô tả biến thể không được vượt quá 2000 ký tự.',

            'variants.*.is_active.required' =>
                'Vui lòng chọn trạng thái biến thể.',

            'variants.*.sort_order.required' =>
                'Vui lòng nhập thứ tự biến thể.',

            'variants.*.sort_order.integer' =>
                'Thứ tự biến thể phải là số nguyên.',

            'variants.*.sort_order.min' =>
                'Thứ tự biến thể không được nhỏ hơn 0.',

            'variants.*.stock_quantity.integer' =>
                'Tồn kho biến thể phải là số nguyên.',

            'variants.*.stock_quantity.min' =>
                'Tồn kho biến thể không được âm.',
        ];
    }
}