@extends('layouts.admin')

@section('title', 'Chương trình khuyến mại')

@section('content')

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">

    <div>
        <h1 class="admin-page-title">Chương trình khuyến mại</h1>
        <p class="admin-page-subtitle">
            Tạo một chương trình rồi gắn nhiều sản phẩm — không cần sửa giá từng sản phẩm.
        </p>
    </div>

    <div class="d-flex gap-2">

        <form method="GET">
            <select name="status" class="form-select" onchange="this.form.submit()">
                <option value="">Tất cả trạng thái</option>
                @foreach($statuses as $status)
                    <option value="{{ $status->value }}" @selected(request('status') === $status->value)>
                        {{ $status->label() }}
                    </option>
                @endforeach
            </select>
        </form>

        <a href="{{ route('admin.promotions.create') }}" class="btn btn-primary-brand px-4 text-nowrap">
            + Tạo chương trình
        </a>

    </div>

</div>

<x-admin.filter-bar
    :action="route('admin.promotions.index')"
    placeholder="Tìm theo tên chương trình…"
    :total="$promotions->total()"
>
    <select name="status" class="form-select" aria-label="Lọc theo trạng thái">
        <option value="">Mọi trạng thái</option>
        @foreach(\App\Enums\PromotionStatus::cases() as $st)
            <option value="{{ $st->value }}" @selected(request('status') === $st->value)>
                {{ $st->label() }}
            </option>
        @endforeach
    </select>
</x-admin.filter-bar>

<div class="admin-panel">

    <div class="table-responsive">

        <table class="admin-table align-middle mb-0">

            <thead>
                <tr>
                    <th>Chương trình</th>
                    <th>Theme</th>
                    <th>Thời gian</th>
                    <th>Mức giảm</th>
                    <th>Sản phẩm</th>
                    <th>Ưu tiên</th>
                    <th>Trạng thái</th>
                    <th class="text-end">Thao tác</th>
                </tr>
            </thead>

            <tbody>

            @forelse($promotions as $promotion)

                <tr>
                    <td>
                        <div class="fw-semibold">{{ $promotion->name }}</div>
                        @if($promotion->short_description)
                            <small class="text-muted">{{ Str::limit($promotion->short_description, 60) }}</small>
                        @endif
                    </td>

                    <td>
                        @if($promotion->theme_key)
                            <span class="tag">{{ app(\App\Services\Theme\ThemeRegistry::class)->label($promotion->theme_key) }}</span>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>

                    <td>
                        <small class="text-muted">
                            <x-site.time :at="$promotion->starts_at" format="d/m/Y">—</x-site.time>
                            &rarr;
                            <x-site.time :at="$promotion->ends_at" format="d/m/Y">—</x-site.time>
                        </small>
                    </td>

                    <td class="fw-semibold">
                        {{ rtrim(rtrim(number_format($promotion->discount_value, 2, ',', '.'), '0'), ',') }}{{ $promotion->type->unit() }}
                    </td>

                    <td>
                        <span class="badge text-bg-light">{{ $promotion->products_count }}</span>
                    </td>

                    <td>{{ $promotion->priority }}</td>

                    <td>
                        <span class="status-chip {{ $promotion->effectiveStatus()->chipClass() }}">
                            {{ $promotion->effectiveStatus()->label() }}
                        </span>
                    </td>

                    <td class="text-end">
                        <div class="d-inline-flex gap-2">
                            <a href="{{ route('admin.promotions.edit', $promotion) }}" class="btn btn-sm btn-outline-secondary">
                                Sửa
                            </a>

                            <form
                                action="{{ route('admin.promotions.destroy', $promotion) }}"
                                method="POST"
                                onsubmit="return confirm('Xóa chương trình này? Sản phẩm sẽ trở về giá gốc.');"
                            >
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger">Xóa</button>
                            </form>
                        </div>
                    </td>
                </tr>

            @empty

                <tr>
                    <td colspan="8" class="p-0">
                        <x-site.empty-state
                            title="Chưa có chương trình khuyến mại"
                            text="Tạo chương trình đầu tiên để áp dụng giảm giá cho nhiều sản phẩm cùng lúc."
                        />
                    </td>
                </tr>

            @endforelse

            </tbody>

        </table>

    </div>

    @if($promotions->hasPages())
        <div class="p-3 border-top">{{ $promotions->links() }}</div>
    @endif

</div>

@endsection
