<div class="row g-4">

    <div class="col-lg-8">

        <div class="admin-panel p-4 mb-4">

            <h2 class="h5 fw-bold mb-4">
                Thông tin sản phẩm
            </h2>

            <div class="mb-3">

                <label class="form-label fw-semibold">
                    Tên sản phẩm
                    <span class="text-danger">*</span>
                </label>

                <input
                    type="text"
                    name="name"
                    class="form-control @error('name') is-invalid @enderror"
                    value="{{ old('name', $product->name ?? '') }}"
                    placeholder="Ví dụ: Hoa hồng đỏ Ecuador"
                >

                <x-form-error name="name"/>

            </div>

            <div class="mb-3">

                <label class="form-label fw-semibold">
                    Mã sản phẩm
                    <span class="text-danger">*</span>
                </label>

                <input
                    type="text"
                    name="product_code"
                    class="form-control @error('product_code') is-invalid @enderror"
                    value="{{ old('product_code', $product->product_code ?? '') }}"
                    placeholder="Ví dụ: HR-EQ-001"
                >

                <x-form-error name="product_code"/>

            </div>

            <div class="mb-3">

                <label class="form-label fw-semibold">
                    Mô tả ngắn
                </label>

                <textarea
                    name="short_description"
                    rows="3"
                    class="form-control @error('short_description') is-invalid @enderror"
                    placeholder="Mô tả ngắn hiển thị ở danh sách sản phẩm..."
                >{{ old('short_description', $product->short_description ?? '') }}</textarea>

                <x-form-error name="short_description"/>

            </div>

            <div class="mb-3">

                <label class="form-label fw-semibold">
                    Mô tả chi tiết
                </label>

                <textarea
                    name="description"
                    rows="8"
                    class="form-control @error('description') is-invalid @enderror"
                    placeholder="Mô tả chi tiết sản phẩm..."
                >{{ old('description', $product->description ?? '') }}</textarea>

                <x-form-error name="description"/>

            </div>

        </div>

        {{--
            KHỐI THÔNG TIN CHĂM SÓC — sinh theo HÌNH THỨC BÁN.

            Guide mục 4.4: cây chậu cần ánh sáng/đất/phân bón..., còn
            "đối với bó hoa/cành hoa thì thông tin chăm sóc lại khác".
            Trước đây khối này in cứng 9 ô của cây chậu cho MỌI sản phẩm.

            Mỗi hồ sơ được in ra một lần, chỉ hồ sơ khớp hình thức đang
            chọn là hiện. JS đổi hiển thị khi người dùng đổi hình thức;
            server vẫn lọc lại ở prepareForValidation() nên ẩn/hiện ở
            trình duyệt chỉ là tiện lợi, không phải hàng rào.
        --}}
        @php
            $currentForm = old('selling_form', $product->selling_form?->value ?? '');
            $currentProfile = \App\Enums\SellingForm::tryFrom((string) $currentForm)?->careProfile();
        @endphp

        @foreach(\App\Enums\CareProfile::cases() as $profile)
            <div
                class="admin-panel p-4 mb-4 care-panel"
                data-care-panel="{{ $profile->value }}"
                @if($currentProfile !== $profile) hidden @endif
            >

                <h2 class="h5 fw-bold mb-1">{{ $profile->label() }}</h2>

                <p class="text-muted small mb-4">
                    @switch($profile)
                        @case(\App\Enums\CareProfile::LivingPlant)
                            Áp dụng cho cây chậu và cây nguyên bản — cây sống cần chăm sóc lâu dài.
                            @break
                        @case(\App\Enums\CareProfile::CutFlower)
                            Áp dụng cho hoa đã cắt (bó, cành, giỏ, hộp, lẵng) — hướng dẫn giữ hoa tươi.
                            @break
                        @default
                            Áp dụng cho set, quà tặng và các hình thức khác — chỉ cần ghi chú chung.
                    @endswitch
                </p>

                <div class="row g-3">
                    @foreach($profile->fields() as $key => $field)
                        <div class="{{ $field['input'] === 'textarea' ? 'col-12' : 'col-md-6' }}">

                            <label class="form-label" for="care-{{ $profile->value }}-{{ $key }}">
                                {{ $field['label'] }}
                            </label>

                            @php $old = old("care_info.$key", $product->care_info[$key] ?? ''); @endphp

                            @if($field['input'] === 'textarea')
                                <textarea
                                    id="care-{{ $profile->value }}-{{ $key }}"
                                    name="care_info[{{ $key }}]"
                                    rows="3"
                                    class="form-control"
                                    placeholder="{{ $field['placeholder'] }}"
                                >{{ $old }}</textarea>

                            @elseif($field['input'] === 'number')
                                {{-- min=1: chu ky 0 ngay nghia la nhac lien tuc,
                                     va so am thi vo nghia. max=365 chan nham
                                     kieu go 3000 thay vi 30. --}}
                                <input
                                    type="number"
                                    id="care-{{ $profile->value }}-{{ $key }}"
                                    name="care_info[{{ $key }}]"
                                    value="{{ $old }}"
                                    min="1"
                                    max="365"
                                    class="form-control"
                                    placeholder="{{ $field['placeholder'] }}"
                                >

                            @elseif($field['input'] === 'difficulty')
                                <select id="care-{{ $profile->value }}-{{ $key }}"
                                        name="care_info[{{ $key }}]" class="form-select">
                                    <option value="">— Không áp dụng —</option>
                                    {{-- Sinh từ App\Enums\CareDifficulty thay vì gõ tay ba
                                         dòng: quy tắc kiểm tra ở StoreProductRequest và bộ
                                         lọc ở trang danh sách cũng đọc cùng enum đó, nên ba
                                         nơi không thể lệch nhau nữa. --}}
                                    @foreach(\App\Enums\CareDifficulty::cases() as $level)
                                        <option value="{{ $level->value }}" @selected($old === $level->value)>
                                            {{ $level->label() }} — {{ $level->hint() }}
                                        </option>
                                    @endforeach
                                </select>

                            @else
                                <input
                                    type="text"
                                    id="care-{{ $profile->value }}-{{ $key }}"
                                    name="care_info[{{ $key }}]"
                                    value="{{ $old }}"
                                    class="form-control"
                                    placeholder="{{ $field['placeholder'] }}"
                                >
                            @endif

                            <x-form-error name="care_info.{{ $key }}"/>

                        </div>
                    @endforeach
                </div>

            </div>
        @endforeach


        {{--
            ============ NHÃN PHÂN LOẠI ============

            Ba nhóm ô đánh dấu: vị trí đặt, hợp mệnh, dùng kèm.
            Dữ liệu này là NGUỒN DUY NHẤT cho trang "Tư vấn chọn cây" và
            cho gợi ý mua kèm — hệ thống KHÔNG tự suy ra cây nào hợp ban
            công hay hợp mệnh Kim, vì suy là bịa.

            Sinh vòng lặp từ enum TraitType nên thêm một loại nhãn mới chỉ
            cần thêm một case, không phải sửa Blade.
        --}}
        <div class="admin-panel p-4 mb-4">

            <h2 class="h6 fw-bold mb-1">Nhãn phân loại</h2>
            <p class="admin-page-subtitle mb-4">
                Dùng cho trang tư vấn chọn cây và gợi ý mua kèm. Bỏ trống thì sản
                phẩm không xuất hiện ở những chỗ đó — không sao, chỉ là mất một
                đường để khách tìm ra nó.
            </p>

            <div class="row g-4">
                @foreach(\App\Enums\TraitType::cases() as $traitType)
                    @php
                        /*
                         * old() có ưu tiên cao hơn dữ liệu trong cơ sở dữ
                         * liệu: form gửi lên bị lỗi validation thì phải
                         * giữ nguyên những gì admin vừa tích, không được
                         * quay về giá trị cũ.
                         */
                        /*
                         * isset($product) LÀ BẮT BUỘC ở tệp này.
                         *
                         * Trang "Thêm sản phẩm" KHÔNG truyền $product —
                         * cả biểu mẫu này dựa vào toán tử ?? để nuốt biến
                         * chưa tồn tại ($product->name ?? ''). Viết
                         * $product->exists mà không kiểm tra trước thì
                         * trang tạo sản phẩm lỗi 500, còn trang sửa vẫn
                         * chạy bình thường — sai một nửa nên rất dễ lọt.
                         */
                        $selected = old("traits.{$traitType->value}")
                            ?? (isset($product) && $product->exists
                                ? $product->traitValues($traitType)
                                : []);
                    @endphp

                    <div class="col-md-4">
                        <p class="form-label mb-2">{{ $traitType->label() }}</p>

                        @if($traitType === \App\Enums\TraitType::FengShui)
                            {{-- Nói rõ đây là tập quán văn hoá, không phải
                                 chỉ số kỹ thuật — cửa hàng không có tư cách
                                 khẳng định thay khách. --}}
                            <p class="admin-page-subtitle mb-2" style="font-size: .78rem;">
                                Theo quan niệm phong thuỷ dân gian. Chỉ gán khi cửa hàng
                                thật sự tư vấn được.
                            </p>
                        @elseif($traitType === \App\Enums\TraitType::AccessoryFor)
                            <p class="admin-page-subtitle mb-2" style="font-size: .78rem;">
                                Chỉ tích cho <strong>phụ kiện</strong> (chậu, đĩa lót, phân bón).
                                Cây và hoa để trống.
                            </p>
                        @endif

                        @foreach($traitType->options() as $value => $label)
                            <div class="form-check">
                                <input class="form-check-input"
                                       type="checkbox"
                                       id="trait-{{ $traitType->value }}-{{ $value }}"
                                       name="traits[{{ $traitType->value }}][]"
                                       value="{{ $value }}"
                                       @checked(in_array($value, $selected, true))>
                                <label class="form-check-label" for="trait-{{ $traitType->value }}-{{ $value }}">
                                    {{ $label }}
                                    @if($traitType === \App\Enums\TraitType::Placement)
                                        <span class="admin-page-subtitle d-block" style="font-size: .72rem;">
                                            {{ \App\Enums\Placement::from($value)->hint() }}
                                        </span>
                                    @endif
                                </label>
                            </div>
                        @endforeach
                    </div>
                @endforeach
            </div>

        </div>


        @php

        $oldVariants = old('variants');

        if ($oldVariants !== null) {

            $variantRows = $oldVariants;

        } elseif (isset($product)) {

            $variantRows =
                $product->variants
                    ->map(function ($variant) {

                        return [
                            'id' =>
                                $variant->id,

                            'name' =>
                                $variant->name,

                            'code' =>
                                $variant->code,

                            'price' =>
                                $variant->price,

                            'description' =>
                                $variant->description,

                            'is_active' =>
                                $variant->is_active
                                    ? 1
                                    : 0,

                            'sort_order' =>
                                $variant->sort_order,

                            '_delete' => 0,
                        ];

                    })
                    ->values()
                    ->all();

        } else {

            $variantRows = [];

        }

    @endphp


    <div
        id="variants"
        class="admin-panel p-4 mb-4"
        data-variant-manager
        data-next-index="{{ count($variantRows) }}"
    >

        <div
            class="d-flex flex-column
                flex-md-row
                justify-content-between
                align-items-md-center
                gap-3 mb-3"
        >

            <div>

                <h2 class="h5 fw-bold mb-1">
                    Biến thể sản phẩm
                </h2>

                <p class="text-muted small mb-0">
                    Các quy cách hoặc phiên bản bán khác nhau
                    của sản phẩm.
                </p>

            </div>

            <button
                type="button"
                class="btn btn-outline-success px-4"
                data-add-variant
            >
                + Thêm biến thể
            </button>

        </div>


        <div
            class="d-flex flex-column gap-3"
            data-variant-list
        >

            @foreach($variantRows as $index => $variant)

                <div
                    class="border rounded-4 p-3"
                    data-variant-row
                >

                    <input
                        type="hidden"
                        name="variants[{{ $index }}][id]"
                        value="{{ $variant['id'] ?? '' }}"
                        data-variant-id
                    >

                    <input
                        type="hidden"
                        name="variants[{{ $index }}][_delete]"
                        value="{{ $variant['_delete'] ?? 0 }}"
                        data-delete-input
                    >


                    <div
                        class="d-flex
                            justify-content-between
                            align-items-center
                            mb-3"
                    >

                        <div class="fw-semibold">
                            Biến thể #{{ $index + 1 }}
                        </div>

                        <button
                            type="button"
                            class="btn btn-sm btn-outline-danger"
                            data-remove-variant
                        >
                            Xóa
                        </button>

                    </div>


                    <div class="row g-3">

                        <div class="col-md-6">

                            <label class="form-label">
                                Tên biến thể
                                <span class="text-danger">*</span>
                            </label>

                            <input
                                type="text"
                                name="variants[{{ $index }}][name]"
                                value="{{ $variant['name'] ?? '' }}"
                                class="form-control"
                                placeholder="Ví dụ: Bó 20 cành"
                            >

                            <x-form-error
                                name="variants.{{ $index }}.name"
                            />

                        </div>


                        <div class="col-md-6">

                            <label class="form-label">
                                Mã biến thể
                            </label>

                            <input
                                type="text"
                                name="variants[{{ $index }}][code]"
                                value="{{ $variant['code'] ?? '' }}"
                                class="form-control"
                                placeholder="Ví dụ: HRD-20"
                            >

                        </div>


                        <div class="col-md-6">

                            <label class="form-label">
                                Giá biến thể
                            </label>

                            <div class="input-group">

                                <input
                                    type="number"
                                    name="variants[{{ $index }}][price]"
                                    value="{{ $variant['price'] ?? '' }}"
                                    min="0"
                                    step="1000"
                                    class="form-control"
                                    placeholder="0"
                                >

                                <span class="input-group-text">
                                    VNĐ
                                </span>

                            </div>

                        </div>


                        <div class="col-md-3">

                            <label class="form-label">
                                Tồn kho
                            </label>

                            <input
                                type="number"
                                name="variants[{{ $index }}][stock_quantity]"
                                value="{{ $variant['stock_quantity'] ?? '' }}"
                                min="0"
                                class="form-control"
                                placeholder="Không giới hạn"
                            >

                        </div>


                        <div class="col-md-3">

                            <label class="form-label">
                                Trạng thái
                            </label>

                            <select
                                name="variants[{{ $index }}][is_active]"
                                class="form-select"
                            >

                                <option
                                    value="1"
                                    @selected(
                                        ($variant['is_active'] ?? 1) == 1
                                    )
                                >
                                    Đang bán
                                </option>

                                <option
                                    value="0"
                                    @selected(
                                        ($variant['is_active'] ?? 1) == 0
                                    )
                                >
                                    Tạm ẩn
                                </option>

                            </select>

                        </div>


                        <div class="col-md-3">

                            <label class="form-label">
                                Thứ tự
                            </label>

                            <input
                                type="number"
                                name="variants[{{ $index }}][sort_order]"
                                value="{{ $variant['sort_order'] ?? ($index + 1) }}"
                                min="0"
                                class="form-control"
                                data-sort-order
                            >

                        </div>


                        <div class="col-12">

                            <label class="form-label">
                                Mô tả biến thể
                            </label>

                            <textarea
                                name="variants[{{ $index }}][description]"
                                rows="3"
                                class="form-control"
                                placeholder="Mô tả thêm nếu cần..."
                            >{{ $variant['description'] ?? '' }}</textarea>

                        </div>

                    </div>

                </div>

            @endforeach

        </div>


        @if(count($variantRows) === 0)

            <div
                class="text-center py-4
                    border rounded-4"
                data-empty-variant
            >

                <div class="mb-2 text-muted">
                    <x-site.leaf-placeholder style="width: 28px; height: 28px;" class="mx-auto" />
                </div>

                <div class="fw-semibold mb-1">
                    Chưa có biến thể
                </div>

                <div class="text-muted small">
                    Có thể thêm biến thể ngay trong lúc
                    tạo hoặc sửa sản phẩm.
                </div>

            </div>

        @endif


        <template data-variant-template>

            <div
                class="border rounded-4 p-3"
                data-variant-row
            >

                <input
                    type="hidden"
                    name="variants[__INDEX__][id]"
                    value=""
                    data-variant-id
                >

                <input
                    type="hidden"
                    name="variants[__INDEX__][_delete]"
                    value="0"
                    data-delete-input
                >


                <div
                    class="d-flex
                        justify-content-between
                        align-items-center
                        mb-3"
                >

                    <div class="fw-semibold">
                        Biến thể mới
                    </div>

                    <button
                        type="button"
                        class="btn btn-sm btn-outline-danger"
                        data-remove-variant
                    >
                        Xóa
                    </button>

                </div>


                <div class="row g-3">

                    <div class="col-md-6">

                        <label class="form-label">
                            Tên biến thể
                            <span class="text-danger">*</span>
                        </label>

                        <input
                            type="text"
                            name="variants[__INDEX__][name]"
                            class="form-control"
                            placeholder="Ví dụ: Bó 20 cành"
                        >

                    </div>


                    <div class="col-md-6">

                        <label class="form-label">
                            Mã biến thể
                        </label>

                        <input
                            type="text"
                            name="variants[__INDEX__][code]"
                            class="form-control"
                            placeholder="Ví dụ: HRD-20"
                        >

                    </div>


                    <div class="col-md-6">

                        <label class="form-label">
                            Giá biến thể
                        </label>

                        <div class="input-group">

                            <input
                                type="number"
                                name="variants[__INDEX__][price]"
                                min="0"
                                step="1000"
                                class="form-control"
                                placeholder="0"
                            >

                            <span class="input-group-text">
                                VNĐ
                            </span>

                        </div>

                    </div>


                    <div class="col-md-3">

                        <label class="form-label">
                            Tồn kho
                        </label>

                        <input
                            type="number"
                            name="variants[__INDEX__][stock_quantity]"
                            min="0"
                            class="form-control"
                            placeholder="Không giới hạn"
                        >

                    </div>


                    <div class="col-md-3">

                        <label class="form-label">
                            Trạng thái
                        </label>

                        <select
                            name="variants[__INDEX__][is_active]"
                            class="form-select"
                        >

                            <option
                                value="1"
                                selected
                            >
                                Đang bán
                            </option>

                            <option value="0">
                                Tạm ẩn
                            </option>

                        </select>

                    </div>


                    <div class="col-md-3">

                        <label class="form-label">
                            Thứ tự
                        </label>

                        <input
                            type="number"
                            name="variants[__INDEX__][sort_order]"
                            value="0"
                            min="0"
                            class="form-control"
                            data-sort-order
                        >

                    </div>


                    <div class="col-12">

                        <label class="form-label">
                            Mô tả biến thể
                        </label>

                        <textarea
                            name="variants[__INDEX__][description]"
                            rows="3"
                            class="form-control"
                            placeholder="Mô tả thêm nếu cần..."
                        ></textarea>

                    </div>

                </div>

            </div>

        </template>

    </div>

        <details class="form-panel form-panel--collapsible">

            <summary class="form-panel__title">SEO &amp; metadata</summary>

            <div class="mb-3 mt-3">

                <label class="form-label">
                    Meta title
                </label>

                <input
                    type="text"
                    name="meta_title"
                    class="form-control"
                    value="{{ old('meta_title', $product->meta_title ?? '') }}"
                >

                <x-form-error name="meta_title"/>

            </div>

            <div>

                <label class="form-label">
                    Meta description
                </label>

                <textarea
                    name="meta_description"
                    rows="3"
                    class="form-control"
                >{{ old('meta_description', $product->meta_description ?? '') }}</textarea>

                <x-form-error name="meta_description"/>

            </div>

        </details>

    </div>

    <div class="col-lg-4">

        <div class="admin-panel p-4 mb-4">

            <h2 class="h6 fw-bold mb-3">
                Phân loại
            </h2>

            <div class="mb-3">

                <label class="form-label">
                    Danh mục
                    <span class="text-danger">*</span>
                </label>

                <select
                    name="category_id"
                    class="form-select @error('category_id') is-invalid @enderror"
                >

                    <option value="">
                        -- Chọn danh mục --
                    </option>

                    @foreach($categories as $category)

                        <option
                            value="{{ $category->id }}"
                            @selected(
                                old(
                                    'category_id',
                                    $product->category_id ?? ''
                                ) == $category->id
                            )
                        >
                            {{ $category->name }}
                        </option>

                    @endforeach

                </select>

                <x-form-error name="category_id"/>

            </div>

            <div class="mb-3">

                <label class="form-label">
                    Loại sản phẩm
                    <span class="text-danger">*</span>
                </label>

                <select
                    name="product_type"
                    class="form-select"
                >

                    {{--
                        Danh sách lấy từ App\Enums\ProductType.

                        Bảng chép tay cũ còn 'gift'/'event'/'wedding' — ba
                        giá trị trả lời "mua để làm gì" chứ không phải
                        "đây là cái gì". Trục dịp thuộc về Category
                        (xem QĐ-08).
                    --}}
                    @php $productTypes = \App\Enums\ProductType::options(); @endphp

                    <option value="">
                        -- Chọn loại --
                    </option>

                    @foreach($productTypes as $value => $label)

                        <option
                            value="{{ $value }}"
                            {{-- Cột đã cast sang enum nên phải so bằng ->value. --}}
                            @selected(
                                old(
                                    'product_type',
                                    $product->product_type?->value ?? ''
                                ) === $value
                            )
                        >
                            {{ $label }}
                        </option>

                    @endforeach

                </select>

                <x-form-error name="product_type"/>

            </div>

            <div>

                <label class="form-label">
                    Hình thức bán
                    <span class="text-danger">*</span>
                </label>

                @php $sellingForms = \App\Enums\SellingForm::options(); @endphp

                <select
                    name="selling_form"
                    class="form-select"
                
                    data-selling-form
                    {{-- Bản đồ hình thức -> hồ sơ chăm sóc do SERVER in ra,
                         để JavaScript không chép lại logic của
                         SellingForm::careProfile(). --}}
                    data-care-profiles="{{ json_encode(
                        collect(\App\Enums\SellingForm::cases())
                            ->mapWithKeys(fn ($f) => [$f->value => $f->careProfile()->value])
                    ) }}"
                >

                    <option value="">
                        -- Chọn hình thức --
                    </option>

                    @foreach($sellingForms as $value => $label)

                        <option
                            value="{{ $value }}"
                            @selected(
                                old(
                                    'selling_form',
                                    $product->selling_form?->value ?? ''
                                ) === $value
                            )
                        >
                            {{ $label }}
                        </option>

                    @endforeach

                </select>

                <x-form-error name="selling_form"/>

            </div>

        </div>

        <div class="admin-panel p-4 mb-4">

            <h2 class="h6 fw-bold mb-3">
                Giá & trạng thái
            </h2>

            <div class="mb-3">

                <label class="form-label">
                    Giá cơ bản
                </label>

                <div class="input-group">

                    <input
                        type="number"
                        name="base_price"
                        min="0"
                        step="1000"
                        class="form-control"
                        value="{{ old('base_price', $product->base_price ?? '') }}"
                        placeholder="0"
                    >

                    <span class="input-group-text">
                        VNĐ
                    </span>

                </div>

                <div class="form-text">
                    Có thể bỏ trống nếu sản phẩm cần báo giá.
                </div>

                <x-form-error name="base_price"/>

            </div>

            {{--
                Khuyến mại KHÔNG còn nhập tại đây.
                Giá giảm do Chương trình khuyến mại quyết định, nên
                admin không phải mở từng sản phẩm để sửa giá dịp lễ.
                Khối dưới chỉ để xem và điều hướng.
            --}}
            <div class="mb-3">

                <label class="form-label">
                    Khuyến mại
                </label>

                @php
                    $joinedPromotions = isset($product)
                        ? $product->promotions()->orderByDesc('priority')->get()
                        : collect();
                @endphp

                @if($joinedPromotions->isEmpty())

                    <div class="form-text mb-2">
                        Sản phẩm chưa nằm trong chương trình nào.
                    </div>

                @else

                    <div class="d-flex flex-column gap-1 mb-2">
                        @foreach($joinedPromotions as $promo)
                            <div class="d-flex align-items-center gap-2">
                                <span class="status-chip {{ $promo->effectiveStatus()->chipClass() }}">
                                    {{ $promo->effectiveStatus()->label() }}
                                </span>
                                <a href="{{ route('admin.promotions.edit', $promo) }}">
                                    {{ $promo->name }}
                                </a>
                            </div>
                        @endforeach
                    </div>

                @endif

                <a href="{{ route('admin.promotions.index') }}" class="btn btn-secondary-brand btn-sm">
                    <x-site.icon name="megaphone" />
                    Quản lý chương trình khuyến mại
                </a>

            </div>

            <div class="row g-2 mb-3">

                {{-- col-12: hai ô này nằm trong cột phụ vốn đã hẹp, chia đôi nữa
                     thì nhãn lựa chọn dài bị cắt cụt ("Không (bán the…"). --}}
                <div class="col-12">

                    <label class="form-label">
                        Quản lý tồn kho
                    </label>

                    @php
                        $trackInventoryOld = old(
                            'track_inventory',
                            isset($product) ? (int) $product->track_inventory : 0
                        );
                    @endphp

                    <select name="track_inventory" class="form-select @error('track_inventory') is-invalid @enderror">
                        <option value="0" @selected($trackInventoryOld == 0)>Không (bán theo mùa/đặt trước)</option>
                        <option value="1" @selected($trackInventoryOld == 1)>Có</option>
                    </select>

                    <x-form-error name="track_inventory"/>

                </div>

                <div class="col-12">

                    <label class="form-label">
                        Số lượng tồn
                    </label>

                    <input
                        type="number"
                        name="stock_quantity"
                        min="0"
                        class="form-control @error('stock_quantity') is-invalid @enderror"
                        value="{{ old('stock_quantity', $product->stock_quantity ?? '') }}"
                    >

                    <div class="form-text">
                        Chỉ áp dụng khi bật quản lý tồn kho.
                    </div>

                    <x-form-error name="stock_quantity"/>

                </div>

            </div>

            <div>

                <label class="form-label">
                    Trạng thái
                </label>

                @php
                    $statuses = [
                        'draft' => 'Bản nháp',
                        'active' => 'Đang bán',
                        'inactive' => 'Tạm ẩn',
                        'out_of_stock' => 'Hết hàng',
                    ];
                @endphp

                <select
                    name="status"
                    class="form-select"
                >

                    @foreach($statuses as $value => $label)

                        <option
                            value="{{ $value }}"
                            @selected(
                                old(
                                    'status',
                                    $product->status ?? 'draft'
                                ) === $value
                            )
                        >
                            {{ $label }}
                        </option>

                    @endforeach

                </select>

                <x-form-error name="status"/>

            </div>

        </div>

        <div class="admin-panel p-4">

            <h2 class="h6 fw-bold mb-3">
                Ảnh đại diện
            </h2>

            <input
                type="file"
                name="main_image"
                class="form-control"
                accept=".jpg,.jpeg,.png,.webp"
            >

            <div class="form-text">
                JPG, JPEG, PNG hoặc WEBP. Tối đa 4MB.
            </div>

            <x-form-error name="main_image"/>

            @if(isset($product) && $product->main_image)

                <img
                    src="{{ asset('storage/' . $product->main_image) }}"
                    alt="{{ $product->name }}"
                    class="img-fluid rounded mt-3"
                >

            @endif

        </div>

        <div class="admin-panel p-4 mt-4">

            <h2 class="h6 fw-bold mb-1">
                Thư viện ảnh
            </h2>

            <p class="text-muted small mb-3">
                Ảnh phụ hiển thị ở trang chi tiết, khách có thể bấm để xem.
                Ảnh đại diện luôn đứng đầu.
            </p>

            <input
                type="file"
                name="gallery[]"
                class="form-control @error('gallery') is-invalid @enderror"
                accept=".jpg,.jpeg,.png,.webp"
                multiple
            >

            <div class="form-text">
                Chọn nhiều ảnh cùng lúc. Tối đa 8 ảnh mỗi lần, mỗi ảnh 4MB.
            </div>

            <x-form-error name="gallery"/>
            <x-form-error name="gallery.*"/>

            @if(isset($product) && $product->images->isNotEmpty())

                <div class="gallery-manager mt-3">
                    @foreach($product->images as $image)
                        <label class="gallery-manager__item">
                            <input
                                type="checkbox"
                                name="remove_images[]"
                                value="{{ $image->id }}"
                                class="gallery-manager__check"
                            >
                            <img src="{{ asset('storage/' . $image->path) }}" alt="">
                            <span class="gallery-manager__label">Xóa</span>
                        </label>
                    @endforeach
                </div>

                <div class="form-text mt-2">
                    Tích vào ảnh muốn xóa rồi bấm lưu.
                </div>

            @endif

        </div>

    </div>

</div>