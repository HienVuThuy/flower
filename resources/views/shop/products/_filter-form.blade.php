<form method="GET" action="{{ route('shop.products.index') }}" class="filter-panel">

    <div class="filter-panel__group">
        <label class="filter-panel__label" for="filter-q">Tìm kiếm</label>
        {{--
            maxlength khớp với TextNormalizer::MAX_QUERY_LENGTH.
            Chặn ngay ở ô nhập thì khách biết giới hạn lúc đang gõ, thay
            vì gõ xong mới thấy phần đuôi bị cắt mất không rõ vì sao.
            Máy chủ vẫn tự cắt lần nữa — thuộc tính HTML không phải là
            biện pháp bảo vệ, chỉ là lời nhắc.
        --}}
        <input
            type="search"
            name="q"
            id="filter-q"
            value="{{ request('q') }}"
            class="form-control"
            maxlength="100"
            placeholder="Gõ có dấu hay không dấu đều được"
        >
    </div>

    <div class="filter-panel__group">
        <span class="filter-panel__label">Danh mục</span>
        <div class="filter-chip-group">
            <a href="{{ request()->fullUrlWithQuery(['category' => null]) }}" class="filter-chip {{ !request('category') ? 'is-active' : '' }}">Tất cả</a>
            @foreach($categories as $category)
                <a href="{{ request()->fullUrlWithQuery(['category' => $category->slug]) }}" class="filter-chip {{ request('category') === $category->slug ? 'is-active' : '' }}">
                    {{ $category->name }}
                </a>
            @endforeach
        </div>
    </div>

    <div class="filter-panel__group">
        <span class="filter-panel__label">Hình thức</span>
        <div class="filter-chip-group">
            <a href="{{ request()->fullUrlWithQuery(['selling_form' => null]) }}" class="filter-chip {{ !request('selling_form') ? 'is-active' : '' }}">Tất cả</a>
            @foreach($sellingForms as $value => $label)
                <a href="{{ request()->fullUrlWithQuery(['selling_form' => $value]) }}" class="filter-chip {{ request('selling_form') === $value ? 'is-active' : '' }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>
    </div>

    {{--
        NHÓM TIÊU CHÍ SINH THÁI — màu, dạng sống, môi trường sống, dáng,
        vị trí đặt, hợp mệnh.
        ============================================================
        MỘT VÒNG LẶP CHO CẢ SÁU, không phải sáu khối chép tay. Danh sách
        nằm ở TraitType::filterable() và tên tham số URL ở queryKey(),
        nên thêm một tiêu chí mới về sau chỉ là thêm một case vào enum.

        CHỈ HIỆN GIÁ TRỊ CÓ HÀNG. $traitOptions do controller đếm sẵn từ
        chính tập sản phẩm mà bộ lọc sẽ tìm trên đó. Dựng thẳng từ enum
        thì bộ lọc liệt kê đủ mười dạng sống kể cả những thứ cửa hàng
        chưa từng bán — và khách bấm vào sẽ nhận màn hình trống. Một lựa
        chọn dẫn tới ngõ cụt là một lựa chọn không nên hiện ra.
    --}}
    @foreach(\App\Enums\TraitType::filterable() as $type)
        @php
            $dangCo = $traitOptions[$type->value] ?? [];
            $key = $type->queryKey();
            $dangChon = request($key);
        @endphp

        @continue(empty($dangCo))

        <div class="filter-panel__group">
            <span class="filter-panel__label">{{ $type->label() }}</span>
            <div class="filter-chip-group">
                <a href="{{ request()->fullUrlWithQuery([$key => null]) }}"
                   class="filter-chip {{ ! $dangChon ? 'is-active' : '' }}">Tất cả</a>

                @foreach($type->options() as $value => $label)
                    @continue(! isset($dangCo[$value]))

                    <a href="{{ request()->fullUrlWithQuery([$key => $value]) }}"
                       class="filter-chip {{ $dangChon === (string) $value ? 'is-active' : '' }}">
                        @if($type === \App\Enums\TraitType::Color)
                            {{--
                                Chấm màu để mắt quét nhanh. Nó KHÔNG tả
                                đúng sản phẩm — một bó hồng đỏ có hàng
                                chục sắc đỏ; ảnh sản phẩm mới nói thật về
                                màu. "Nhiều màu" không có màu nào đại
                                diện nên vẽ dải chuyển thay vì chọn bừa.
                            --}}
                            <span class="filter-chip__dot"
                                  @if($hex = \App\Enums\PlantColor::from($value)->hex())
                                      style="background: {{ $hex }}"
                                  @else
                                      style="background: linear-gradient(135deg,#ec8ba7,#f2c229,#4c8b5b)"
                                  @endif
                                  aria-hidden="true"></span>
                        @endif
                        {{ $label }}
                        <span class="filter-chip__count">{{ $dangCo[$value] }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    @endforeach

    <div class="filter-panel__group">
        <label class="filter-panel__label" for="filter-sort">Sắp xếp</label>
        <select name="sort" id="filter-sort" class="form-select" onchange="this.form.submit()">
            <option value="" @selected(!request('sort'))>Mới nhất</option>
            <option value="price_asc" @selected(request('sort') === 'price_asc')>Giá tăng dần</option>
            <option value="price_desc" @selected(request('sort') === 'price_desc')>Giá giảm dần</option>
            <option value="popular" @selected(request('sort') === 'popular')>Xem nhiều nhất</option>
        </select>
    </div>

    <button type="submit" class="btn btn-primary-brand w-100 mt-2">Áp dụng</button>

    {{--
        Danh sách tham số lấy từ TraitType::filterable(), không chép tay.
        Chép tay thì thêm một tiêu chí mới là nút "Xoá bộ lọc" lặng lẽ
        không nhận ra nó nữa — khách lọc xong không có cách nào quay lại.
    --}}
    @php
        $moiThamSo = array_merge(
            ['q', 'category', 'selling_form', 'sort', 'care_difficulty', 'loai'],
            array_map(fn ($t) => $t->queryKey(), \App\Enums\TraitType::filterable()),
        );
    @endphp

    @if(request()->hasAny($moiThamSo))
        <a href="{{ route('shop.products.index') }}" class="btn btn-ghost w-100 mt-2">Xóa bộ lọc</a>
    @endif

</form>
