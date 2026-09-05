@extends('layouts.admin')

@section('title', 'Mã giảm giá')

@section('content')

    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <h1 class="admin-page-title">Mã giảm giá</h1>
            <p class="admin-page-subtitle">
                Mã do khách tự nhập ở bước thanh toán — khác với Khuyến mại,
                thứ cửa hàng chủ động áp cho sản phẩm.
            </p>
        </div>

        <a href="{{ route('admin.coupons.create') }}" class="btn btn-primary-brand px-4">
            + Thêm mã
        </a>
    </div>

    <x-admin.filter-bar
    :action="route('admin.coupons.index')"
    placeholder="Tìm theo mã hoặc tên chương trình…"
    :total="$coupons->total()"
>
    <select name="status" class="form-select" aria-label="Lọc theo trạng thái">
        <option value="">Mọi trạng thái</option>
        @foreach(\App\Enums\PromotionStatus::cases() as $st)
            <option value="{{ $st->value }}" @selected(request('status') === $st->value)>
                {{ $st->label() }}
            </option>
        @endforeach
    </select>

    {{--
        "Đang dùng được" KHÁC "trạng thái = đang chạy".

        Mã còn trạng thái active nhưng đã qua ngày kết thúc hoặc hết lượt
        thì khách vẫn không dùng được. Đây là ô admin cần khi khách gọi
        kêu "mã của tôi báo lỗi".
    --}}
    <select name="dung_duoc" class="form-select" aria-label="Lọc mã đang dùng được">
        <option value="">Tất cả</option>
        <option value="co" @selected(request('dung_duoc') === 'co')>Khách đang dùng được</option>
    </select>
</x-admin.filter-bar>

<div class="admin-panel">

        @if($coupons->isEmpty())

            <div class="p-4 text-center admin-page-subtitle">
                <p class="mb-0">Chưa có mã giảm giá nào.</p>
            </div>

        @else

            <div class="table-responsive">
                <table class="admin-table align-middle mb-0">

                    <thead>
                        <tr>
                            <x-admin.sort-header khoa="ma" nhan="Mã" />
                            <th>Chương trình</th>
                            <th>Giảm</th>
                            <th>Điều kiện</th>
                            <x-admin.sort-header khoa="luot-dung" nhan="Lượt dùng" dau="giam" />
                            <x-admin.sort-header khoa="het-han" nhan="Hiệu lực" />
                            <x-admin.sort-header khoa="trang-thai" nhan="Trạng thái" />
                            <th class="text-end">Thao tác</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach($coupons as $coupon)
                            <tr>
                                <td class="fw-bold">{{ $coupon->code }}</td>

                                <td>
                                    {{ $coupon->name }}
                                    @if($coupon->description)
                                        <div class="admin-page-subtitle">{{ $coupon->description }}</div>
                                    @endif
                                </td>

                                <td>
                                    {{ rtrim(rtrim(number_format((float) $coupon->value, 2, ',', '.'), '0'), ',') }}{{ $coupon->type->unit() }}
                                </td>

                                <td class="admin-page-subtitle">{{ $coupon->conditionText() }}</td>

                                <td>
                                    {{ $coupon->used_count }}{{ $coupon->usage_limit ? ' / ' . $coupon->usage_limit : '' }}
                                    @if($coupon->isExhausted())
                                        <div class="admin-page-subtitle">Đã hết lượt</div>
                                    @endif
                                </td>

                                <td class="admin-page-subtitle">
                                    {{ $coupon->starts_at?->format('d/m/Y') ?? 'Không giới hạn' }}
                                    &rarr;
                                    {{ $coupon->ends_at?->format('d/m/Y') ?? 'Không giới hạn' }}
                                </td>

                                <td>
                                    <span class="status-pill status-pill--{{ $coupon->isRunning() ? 'success' : 'secondary' }}">
                                        {{ $coupon->status->label() }}
                                    </span>
                                </td>

                                <td class="text-end">
                                    <a href="{{ route('admin.coupons.edit', $coupon) }}" class="btn btn-outline-admin btn-sm">
                                        Sửa
                                    </a>

                                    {{-- Mã đã có người dùng thì không xoá được, xem CouponController::destroy --}}
                                    @if($coupon->used_count === 0)
                                        <form method="POST" action="{{ route('admin.coupons.destroy', $coupon) }}"
                                              class="d-inline"
                                              onsubmit="return confirm('Xoá mã {{ $coupon->code }}?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-outline-admin btn-sm">Xoá</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>

                </table>
            </div>

        @endif

    </div>

    @if($coupons->hasPages())
        <div class="mt-3">{{ $coupons->links() }}</div>
    @endif

@endsection
