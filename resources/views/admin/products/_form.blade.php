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

                <div class="form-text">
                    Đoạn mở đầu, hiện trước các khối bên dưới. Cần chữ xen ảnh thì thêm khối.
                </div>

            </div>

            {{--
                MÔ TẢ CHI TIẾT THEO KHỐI: chữ – ảnh – chữ – ảnh…
                ============================================================
                Mỗi khối một dòng, thứ tự trên màn hình CHÍNH LÀ thứ tự hiện ra
                ở trang khách. Không có ô "số thứ tự" gõ tay: hai khối cùng mang
                số 3 thì thứ tự do database quyết định, không ai đoán được.

                Ảnh ở đây là TỆP CÓ CHỦ (một dòng trong bảng), không phải thẻ
                <img> nhét vào ô chữ — nhờ vậy xoá khối là xoá được cả tệp, và ô
                chữ không phải mở cửa cho thẻ ảnh tuỳ ý.
            --}}
            <div class="mb-3" data-blocks>

                <label class="form-label fw-semibold">Khối nội dung chi tiết</label>

                <p class="text-muted small mb-2">
                    Xếp chữ và ảnh xen kẽ tuỳ ý. Khối chữ để trống sẽ bị bỏ khi lưu;
                    khối ảnh chưa chọn ảnh cũng vậy.
                </p>

                @php
                    /*
                     * Dữ liệu dựng lại sau khi validation hỏng (old) phải thắng
                     * dữ liệu trong cơ sở dữ liệu — nếu không, người dùng sửa
                     * xong, gặp lỗi ở ô khác, và mất hết phần vừa gõ.
                     */
                    $khoiCu = old('blocks', isset($product)
                        ? $product->blocks->map(fn ($b) => [
                            'id' => $b->id,
                            'kind' => $b->kind,
                            'body' => $b->body,
                            'caption' => $b->caption,
                            'image_path' => $b->image_path,
                        ])->all()
                        : []);
                @endphp

                <div data-blocks-list>
                    @foreach($khoiCu as $i => $khoi)
                        @include('admin.products._block-row', ['i' => $i, 'khoi' => $khoi])
                    @endforeach
                </div>

                <div class="d-flex gap-2 mt-2">
                    <button type="button" class="btn btn-outline-admin btn-sm" data-block-add="text">+ Thêm khối chữ</button>
                    <button type="button" class="btn btn-outline-admin btn-sm" data-block-add="image">+ Thêm khối ảnh</button>
                </div>

                <x-form-error name="blocks.*.body"/>
                <x-form-error name="blocks.*.image"/>

                {{-- Mẫu dòng cho JavaScript nhân bản. Để trong <template> nên
                     trình duyệt không gửi các ô bên trong khi lưu. --}}
                <template data-block-template="text">
                    @include('admin.products._block-row', ['i' => '__INDEX__', 'khoi' => ['kind' => 'text']])
                </template>

                <template data-block-template="image">
                    @include('admin.products._block-row', ['i' => '__INDEX__', 'khoi' => ['kind' => 'image']])
                </template>

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
                ============ NHÓM THUẾ SUẤT ============

                ĐẶT NGAY DƯỚI Ô GIÁ, có chủ ý: giá của cửa hàng này ĐÃ
                BAO GỒM VAT, nên hai ô này nói về cùng một con số. Đặt
                nhóm thuế ở một thẻ khác thì người nhập giá không nhìn
                thấy nó, và mọi sản phẩm sẽ nằm mãi ở mức mặc định.

                ĐỂ TRỐNG LÀ MỘT LỰA CHỌN HỢP LỆ, không phải dữ liệu
                thiếu. Nó nghĩa là "dùng mức mặc định của cửa hàng" —
                đúng hành vi trước khi có bảng nhóm thuế, nên sản phẩm cũ
                không đổi gì cả.
            --}}
            <div class="mb-3">

                <label class="form-label" for="tax_class_id">
                    Nhóm thuế suất
                </label>

                <select name="tax_class_id" id="tax_class_id" class="form-select">
                    <option value="">
                        Dùng mức mặc định của cửa hàng ({{ app(\App\Services\Tax\TaxCalculator::class)->ratePercent() }}%)
                    </option>

                    @foreach($taxClasses as $nhom)
                        <option value="{{ $nhom->id }}"
                                @selected((string) old('tax_class_id', $product->tax_class_id ?? '') === (string) $nhom->id)>
                            {{ $nhom->name }}
                        </option>
                    @endforeach
                </select>

                <div class="form-text">
                    {{--
                        NÓI THẲNG RA GIỚI HẠN CỦA PHẦN MỀM.

                        Danh sách này là cấu hình, KHÔNG phải lời tư vấn
                        thuế. Mã nguồn không biết mặt hàng của cửa hàng
                        thuộc diện nào — hoa tươi, cây giống, chậu sứ và
                        dịch vụ chăm cây có thể mỗi thứ một mức. Chọn bừa
                        thì con số sai đi thẳng vào hoá đơn mà không có
                        gì báo.
                    --}}
                    Giá đã bao gồm VAT, nên mức này quyết định phần thuế
                    <strong>tách ra</strong> từ giá, không cộng thêm vào.
                    Việc phân loại phải theo mặt hàng thực tế và quy định
                    áp dụng — hỏi kế toán trước khi đổi.
                </div>

                <x-form-error name="tax_class_id"/>

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
                @php
                    $trackInventoryOld = old(
                        'track_inventory',
                        isset($product) ? (int) $product->track_inventory : 0
                    );
                @endphp

                {{--
                    HAI Ô ĐI VỚI NHAU nên nằm chung một khối `data-stock-group`:
                    ô số tồn chỉ gõ được khi ô trên đang bật.
                --}}
                <div class="col-12" data-stock-group>

                    <label class="form-label" for="track_inventory">
                        Quản lý tồn kho
                    </label>

                    <select id="track_inventory" name="track_inventory" data-stock-toggle
                            class="form-select @error('track_inventory') is-invalid @enderror">
                        <option value="0" @selected($trackInventoryOld == 0)>Không (bán theo mùa/đặt trước)</option>
                        <option value="1" @selected($trackInventoryOld == 1)>Có</option>
                    </select>

                    <x-form-error name="track_inventory"/>

                    <label class="form-label mt-3" for="stock_quantity">
                        Số lượng tồn
                    </label>

                    {{--
                        KHOÁ BẰNG readonly, KHÔNG PHẢI disabled.

                        Ô disabled không được gửi lên máy chủ: bật lại quản lý tồn
                        kho là con số cũ biến mất mà không ai bấm gì.

                        Trạng thái dựng SẴN Ở MÁY CHỦ, không đợi JavaScript — tắt
                        JS thì ô vẫn khoá đúng, và không có cú nháy "gõ được rồi
                        khoá lại" ngay sau khi trang hiện.
                    --}}
                    <input
                        type="number"
                        id="stock_quantity"
                        name="stock_quantity"
                        min="0"
                        data-stock-input
                        @readonly($trackInventoryOld != 1)
                        @class(['form-control', 'is-locked' => $trackInventoryOld != 1, 'is-invalid' => $errors->has('stock_quantity')])
                        value="{{ old('stock_quantity', $product->stock_quantity ?? '') }}"
                    >

                    <div class="form-text" data-stock-note @if($trackInventoryOld == 1) hidden @endif>
                        Đang tắt quản lý tồn kho nên ô này không dùng tới — chọn "Có" ở trên nếu muốn nhập số tồn.
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

            {{--
                VIDEO — hai đường, vì hai nhu cầu khác nhau.

                Link YouTube/Vimeo: không tốn chỗ trên máy chủ, nhưng kéo theo
                theo dõi người xem (đã dùng bản youtube-nocookie, và chỉ tải
                trình phát khi khách bấm).

                Tệp MP4: cửa hàng tự giữ, không ai theo dõi khách, nhưng tốn chỗ
                — nên giới hạn 20MB cho một clip ngắn quay bằng điện thoại.
            --}}
            <hr class="my-4">

            <h2 class="h6 fw-bold mb-1">Video</h2>

            <p class="text-muted small mb-3">
                Hiện chung dải với ảnh ở trang chi tiết, theo thứ tự thêm vào.
            </p>

            <label class="form-label" for="video_url_0">Link YouTube hoặc Vimeo</label>

            @for($i = 0; $i < 2; $i++)
                <input
                    type="url"
                    id="video_url_{{ $i }}"
                    name="video_urls[]"
                    value="{{ old('video_urls.' . $i) }}"
                    class="form-control mb-2 @error('video_urls.' . $i) is-invalid @enderror"
                    placeholder="https://www.youtube.com/watch?v=..."
                >
                <x-form-error :name="'video_urls.' . $i"/>
            @endfor

            <label class="form-label mt-2" for="video_files">Hoặc tải lên tệp MP4</label>

            <input
                type="file"
                id="video_files"
                name="video_files[]"
                class="form-control @error('video_files.0') is-invalid @enderror"
                accept="video/mp4"
                multiple
            >

            <div class="form-text">Tối đa 3 tệp, mỗi tệp 20MB. Dài hơn thì đăng YouTube rồi dán link.</div>

            <x-form-error name="video_files.0"/>
            <x-form-error name="video_files.1"/>
            <x-form-error name="video_files.2"/>

            @if(isset($product) && $product->videos->isNotEmpty())
                <div class="gallery-manager mt-3">
                    @foreach($product->videos as $video)
                        <label class="gallery-manager__item gallery-manager__item--video">
                            <input type="checkbox" name="remove_images[]" value="{{ $video->id }}" class="gallery-manager__check">
                            <span class="gallery-manager__video">
                                <x-site.icon name="eye" />
                                {{ $video->linkNhung() ? 'Link nhúng' : 'Tệp MP4' }}
                            </span>
                            <span class="gallery-manager__label">Xóa</span>
                        </label>
                    @endforeach
                </div>

                <div class="form-text mt-2">Tích vào video muốn xoá rồi bấm lưu.</div>
            @endif

        </div>

    </div>

</div>