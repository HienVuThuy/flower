{{-- $moiThamSoLoc do ProductController dựng — một nơi sở hữu duy nhất, dùng chung với khối mời "Chọn cây" ở… --}}
<form method="GET" action="{{ route('shop.products.index') }}" class="filter-panel" data-form-loc>

    @foreach($moiThamSoLoc as $thamSo)
        @continue(in_array($thamSo, ['q', 'sort'], true))
        @continue(! request()->filled($thamSo))

        <input type="hidden" name="{{ $thamSo }}" value="{{ request($thamSo) }}">
    @endforeach


    <div class="filter-panel__group">
        <label class="filter-panel__label" for="filter-q">Tìm kiếm</label>
        <input
            type="search"
            name="q"
            id="filter-q"
            value="{{ request('q') }}"
            class="form-control"
            maxlength="100"
            placeholder="Tên cây, hoa…"
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

    @if($careDifficulties->isNotEmpty())
        <div class="filter-panel__group">
            <span class="filter-panel__label">Độ khó chăm sóc</span>
            <div class="filter-chip-group">
                <a href="{{ request()->fullUrlWithQuery(['kinh-nghiem' => null]) }}"
                   class="filter-chip {{ ! request('kinh-nghiem') ? 'is-active' : '' }}">Tất cả</a>

                @foreach($careDifficulties as $row)
                    <a href="{{ request()->fullUrlWithQuery(['kinh-nghiem' => $row['difficulty']->value]) }}"
                       class="filter-chip {{ request('kinh-nghiem') === $row['difficulty']->value ? 'is-active' : '' }}"
                       title="{{ $row['difficulty']->hint() }}">
                        {{ $row['difficulty']->label() }}
                        <span class="filter-chip__count">{{ $row['total'] }}</span>
                    </a>
                @endforeach
            </div>

            <p class="filter-panel__note">Chỉ áp dụng cho cây trồng chậu, không tính hoa cắt cành.</p>
        </div>
    @endif

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
            <option value="noi_bat" @selected(request('sort') === 'noi_bat')>Nổi bật trước</option>
        </select>
    </div>

    <button type="submit" class="btn btn-primary-brand w-100 mt-2">Áp dụng</button>

    @if(request()->hasAny($moiThamSoLoc))
        <a href="{{ route('shop.products.index') }}" class="btn btn-ghost w-100 mt-2">Xóa bộ lọc</a>
    @endif

</form>
