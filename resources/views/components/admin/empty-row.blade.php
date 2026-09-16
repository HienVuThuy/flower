@props([
    'colspan' => 6,
    'empty' => 'Chưa có dữ liệu.',
])

@php
    $filtering = collect(request()->query())
        ->except('page')
        ->filter(fn ($v) => $v !== '' && $v !== null)
        ->isNotEmpty();
@endphp

{{-- DÒNG TRỐNG CỦA BẢNG — phân biệt HAI tình huống khác hẳn nhau. --}}
<tr>
    <td colspan="{{ $colspan }}" class="text-center py-5 text-muted">
        @if($filtering)
            Không có kết quả nào khớp với bộ lọc.
            <a href="{{ url()->current() }}" class="ms-1">Xoá lọc</a>
        @else
            {{ $empty }}
        @endif
    </td>
</tr>
