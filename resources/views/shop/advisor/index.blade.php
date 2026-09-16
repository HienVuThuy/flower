@extends('layouts.app')

@section('title', 'Chọn cây theo nhu cầu')

@section('content')

<section class="section-sm">
    <div class="container-shop">

        <x-site.breadcrumb :items="[['label' => 'Chọn cây theo nhu cầu']]" />

        <div class="section-header">
            <div>
                <span class="text-label section-header__eyebrow d-block">Tư vấn</span>
                <h1 class="text-h2 section-header__title">Bạn cần cây cho chỗ nào?</h1>
                <p class="mb-0">
                    Chọn điều kiện thật ở nhà bạn, chúng tôi lọc ra những cây sống được ở đó.
                </p>
            </div>
        </div>

        <div class="advisor-filters">

            <div class="advisor-filters__group">
                <p class="advisor-filters__label">Đặt ở đâu</p>

                <div class="advisor-filters__options">
                    @foreach($placements as $row)
                        @php($p = $row['placement'])
                        <a href="{{ request()->fullUrlWithQuery(['vi-tri' => $placement === $p ? null : $p->value]) }}"
                           class="advisor-chip {{ $placement === $p ? 'is-active' : '' }}">
                            <x-site.icon :name="$p->icon()" class="advisor-chip__icon" />
                            <span class="advisor-chip__body">
                                <span class="advisor-chip__name">{{ $p->label() }}</span>
                                <span class="advisor-chip__hint">{{ $p->hint() }}</span>
                            </span>
                            <span class="advisor-chip__count">{{ $row['total'] }}</span>
                        </a>
                    @endforeach
                </div>
            </div>

            @if($elements->isNotEmpty())
                <div class="advisor-filters__group">
                    <p class="advisor-filters__label">
                        Hợp mệnh
                        <span class="advisor-filters__note">theo quan niệm phong thuỷ dân gian</span>
                    </p>

                    <div class="advisor-filters__options">
                        @foreach($elements as $row)
                            @php($e = $row['element'])
                            <a href="{{ request()->fullUrlWithQuery(['menh' => $element === $e ? null : $e->value]) }}"
                               class="advisor-chip {{ $element === $e ? 'is-active' : '' }}">
                                <span class="advisor-chip__body">
                                    <span class="advisor-chip__name">{{ $e->label() }}</span>
                                    <span class="advisor-chip__hint">{{ $e->colorHint() }}</span>
                                </span>
                                <span class="advisor-chip__count">{{ $row['total'] }}</span>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            @if($difficulties->isNotEmpty())
                <div class="advisor-filters__group">
                    <p class="advisor-filters__label">
                        Kinh nghiệm trồng cây
                        <span class="advisor-filters__note">chọn mức bạn thấy đúng với mình</span>
                    </p>

                    <div class="advisor-filters__options">
                        @foreach($difficulties as $row)
                            @php($d = $row['difficulty'])
                            <a href="{{ request()->fullUrlWithQuery(['kinh-nghiem' => $difficulty === $d ? null : $d->value]) }}"
                               class="advisor-chip {{ $difficulty === $d ? 'is-active' : '' }}">
                                <span class="advisor-chip__body">
                                    <span class="advisor-chip__name">{{ $d->experienceLabel() }}</span>
                                    <span class="advisor-chip__hint">{{ $d->hint() }}</span>
                                </span>
                                <span class="advisor-chip__count">{{ $row['total'] }}</span>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            @foreach($nhomSinhThai as $nhom)
                <div class="advisor-filters__group">
                    <p class="advisor-filters__label">{{ $nhom['type']->label() }}</p>

                    <div class="advisor-filters__options">
                        @foreach($nhom['values'] as $row)
                            @php($dangChon = $nhom['dangChon'] === $row['value'])

                            <a href="{{ request()->fullUrlWithQuery([
                                   $nhom['type']->queryKey() => $dangChon ? null : $row['value'],
                               ]) }}"
                               class="advisor-chip {{ $dangChon ? 'is-active' : '' }}">
                                <span class="advisor-chip__body">
                                    <span class="advisor-chip__name">{{ $row['label'] }}</span>
                                    @if($row['hint'])
                                        <span class="advisor-chip__hint">{{ $row['hint'] }}</span>
                                    @endif
                                </span>
                                <span class="advisor-chip__count">{{ $row['total'] }}</span>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endforeach

            <p class="advisor-filters__note mt-2 mb-0">
                Biết tên loài rồi?
                <a href="{{ route('shop.taxa.index') }}">Duyệt theo phân loại sinh học</a>
            </p>

            @if($hasFilter)
                <a href="{{ route('shop.advisor.index') }}" class="btn btn-ghost btn-sm">Bỏ hết điều kiện</a>
            @endif

        </div>

        <div class="advisor-go mt-4">
            @if($hasFilter)
                <a href="{{ $ketQuaUrl }}" class="btn btn-primary-brand btn-lg">
                    Xem cây phù hợp
                </a>

                <p class="advisor-go__note">
                    Sang trang sản phẩm với đúng điều kiện bạn vừa chọn —
                    ở đó lọc thêm được theo giá, và sắp xếp được.
                </p>
            @else
                <a href="{{ route('shop.products.index', ['kinh-nghiem' => \App\Enums\CareDifficulty::Easy->value]) }}"
                   class="btn btn-secondary-brand btn-lg">
                    Chưa biết chọn gì? Xem cây dễ chăm
                </a>

                <p class="advisor-go__note">
                    Hoặc chọn vài điều kiện ở trên rồi bấm xem kết quả.
                </p>
            @endif
        </div>

        <p class="text-caption mt-4 mb-0">
            Thông tin vị trí đặt và độ khó chăm dựa trên đặc tính trồng trọt của
            từng loại cây. Phần hợp mệnh là quan niệm phong thuỷ dân gian, cửa hàng
            ghi lại để bạn tham khảo chứ không khẳng định thay bạn.
        </p>

    </div>
</section>

@endsection
