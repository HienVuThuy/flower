<div class="row g-4">

    <div class="col-lg-8">

        <div class="admin-panel p-4 mb-4">

            <h2 class="h5 fw-bold mb-4">
                Thông tin danh mục
            </h2>

            <div class="mb-3">

                <label class="form-label">
                    Tên danh mục
                    <span class="text-danger">*</span>
                </label>

                <input
                    type="text"
                    name="name"
                    class="form-control @error('name') is-invalid @enderror"
                    value="{{ old('name', $category->name ?? '') }}"
                    placeholder="Ví dụ: Hoa hồng"
                >

                <x-form-error name="name"/>

            </div>

            <div>

                <label class="form-label">
                    Mô tả
                </label>

                <textarea
                    name="description"
                    rows="5"
                    class="form-control @error('description') is-invalid @enderror"
                    placeholder="Mô tả ngắn về danh mục..."
                >{{ old('description', $category->description ?? '') }}</textarea>

                <x-form-error name="description"/>

            </div>

        </div>

    </div>

    <div class="col-lg-4">

        <div class="admin-panel p-4 mb-4">

            <h2 class="h6 fw-bold mb-3">
                Hình ảnh
            </h2>

            <input
                type="file"
                name="image"
                class="form-control @error('image') is-invalid @enderror"
                accept=".jpg,.jpeg,.png,.webp"
            >

            <div class="form-text">
                JPG, JPEG, PNG hoặc WEBP. Tối đa 2MB.
            </div>

            <x-form-error name="image"/>

            @if(isset($category) && $category->image)

                <img
                    src="{{ asset('storage/' . $category->image) }}"
                    alt="{{ $category->name }}"
                    class="img-fluid rounded-4 mt-3"
                >

            @endif

        </div>

        <div class="admin-panel p-4">

            <h2 class="h6 fw-bold mb-3">
                Trạng thái &amp; thứ tự
            </h2>

            {{-- Nhóm quyết định danh mục hiện ở trang cây & hoa hay ở trang /phu-kien. --}}
            <div class="mb-3">
                <label class="form-label" for="kind">Nhóm danh mục</label>
                @php $kindOld = old('kind', isset($category) ? ($category->kind?->value ?? 'plant') : 'plant'); @endphp
                <select id="kind" name="kind" class="form-select @error('kind') is-invalid @enderror">
                    @foreach(\App\Enums\CategoryKind::cases() as $nhom)
                        <option value="{{ $nhom->value }}" @selected($kindOld === $nhom->value)>
                            {{ $nhom->label() }} — {{ $nhom->hint() }}
                        </option>
                    @endforeach
                </select>
                <x-form-error name="kind"/>
            </div>

            <div class="mb-3">

                <label class="form-label">
                    Trạng thái
                </label>

                @php
                    $isActiveOld = old(
                        'is_active',
                        isset($category) ? (int) $category->is_active : 1
                    );
                @endphp

                <select
                    name="is_active"
                    class="form-select @error('is_active') is-invalid @enderror"
                >
                    <option value="1" @selected($isActiveOld == 1)>
                        Đang hoạt động
                    </option>

                    <option value="0" @selected($isActiveOld == 0)>
                        Tạm ẩn
                    </option>
                </select>

                <x-form-error name="is_active"/>

            </div>

            <div>

                <label class="form-label">
                    Thứ tự hiển thị
                </label>

                <input
                    type="number"
                    name="sort_order"
                    min="0"
                    max="9999"
                    class="form-control @error('sort_order') is-invalid @enderror"
                    value="{{ old('sort_order', $category->sort_order ?? 0) }}"
                >

                <x-form-error name="sort_order"/>

            </div>

        </div>

    </div>

</div>
