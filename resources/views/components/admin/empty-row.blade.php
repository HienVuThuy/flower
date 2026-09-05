@props([
    'colspan' => 6,
    // Câu hiện khi bảng thật sự chưa có dữ liệu nào.
    'empty' => 'Chưa có dữ liệu.',
])

@php
    // Đang lọc hay không — `page` không tính, xem filter-bar.
    $filtering = collect(request()->query())
        ->except('page')
        ->filter(fn ($v) => $v !== '' && $v !== null)
        ->isNotEmpty();
@endphp

{{--
    DÒNG TRỐNG CỦA BẢNG — phân biệt HAI tình huống khác hẳn nhau.

    "Chưa có sản phẩm nào" khi admin vừa lọc theo danh mục là câu SAI:
    kho có 43 sản phẩm, chỉ là không cái nào khớp. Admin đọc câu đó rồi
    tưởng mất dữ liệu, hoặc tưởng bộ lọc hỏng.

    Đang lọc thì nói đúng điều đó, và cho luôn đường thoát.
--}}
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
