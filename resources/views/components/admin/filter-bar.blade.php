@props([
    'action',

    'placeholder' => 'Tìm kiếm…',

    'searchable' => true,

    'total' => null,
])

@php
    $active = collect(request()->query())->except('page')->filter(fn ($v) => $v !== '' && $v !== null);
@endphp

<form method="GET" action="{{ $action }}" class="admin-filters" data-admin-filters>

    @if($searchable)
        <div class="admin-filters__search">
            <x-site.icon name="search" class="admin-filters__icon" />
            <input
                type="search"
                name="q"
                value="{{ request('q') }}"
                class="form-control"
                placeholder="{{ $placeholder }}"
                aria-label="{{ $placeholder }}"
            >
        </div>
    @endif

    {{ $slot }}

    <button type="submit" class="btn btn-secondary-brand">Lọc</button>

    @if($active->isNotEmpty())
        <a href="{{ $action }}" class="btn btn-ghost">Xoá lọc</a>
    @endif

    @if($total !== null)
        <span class="admin-filters__count">
            {{ number_format($total, 0, ',', '.') }} kết quả
        </span>
    @endif

</form>
