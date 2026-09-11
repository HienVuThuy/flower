@props([
    'label',

    // Chú thích một dòng: con số này ĐẾM CÁI GÌ. Bắt buộc phải có ở mọi
    // chỉ số tiền — "Doanh thu" một mình không nói được là đã trừ đơn
    // huỷ chưa, đã tính đơn đang giao chưa.
    'note' => null,

    // Cho phần so sánh kỳ trước. null = không có kỳ trước để so.
    'now' => null,
    'before' => null,

    // Chỉ số mà TĂNG LÀ XẤU (đơn huỷ).
    'invert' => false,

    // 'tien' để mốc kỳ trước cũng in có đơn vị đồng.
    'format' => 'so',

    'href' => null,
])

@php $tag = $href ? 'a' : 'div'; @endphp

{{--
    MỘT CHỈ SỐ, KÈM MỐC SO SÁNH.
    ============================================================
    Một con số trần trụi không nói lên điều gì. "12 đơn" là nhiều hay
    ít? Chỉ khi đặt cạnh kỳ trước ("12 đơn, kỳ trước 20") thì nó mới trả
    lời được câu hỏi thật sự của người mở trang này: cửa hàng đang lên
    hay đang xuống.

    Phần so sánh giao hết cho <x-admin.trend>, nơi đã xử lý ba trường
    hợp mà gộp lại là nói dối: không có kỳ trước, kỳ trước bằng 0, và
    trường hợp bình thường.
--}}
<{{ $tag }} @if($href) data-admin-link href="{{ $href }}" @endif class="admin-kpi">

    <span class="admin-kpi__label">{{ $label }}</span>

    <span class="admin-kpi__value">{{ $slot }}</span>

    @if($now !== null)
        <x-admin.trend :now="$now" :before="$before" :invert="$invert" :format="$format" />
    @endif

    @if($note)
        <span class="admin-kpi__note">{{ $note }}</span>
    @endif

</{{ $tag }}>
