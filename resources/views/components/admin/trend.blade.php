@props(['now', 'before' => null, 'invert' => false, 'format' => 'so'])

{{--
    So sánh một chỉ số với kỳ trước.

    BA TRẠNG THÁI KHÁC NHAU, và gộp chúng lại là nói dối:

      $before === null   kỳ 'Toàn bộ' không có kỳ trước để so  -> không hiện gì
      $before == 0       kỳ trước bằng 0, mọi % đều vô nghĩa   -> nói thẳng
      còn lại            hiện % tăng/giảm

    Trường hợp giữa là chỗ dễ sai nhất: chia cho 0 trong PHP không ném lỗi
    với phép chia số thực mà cho ra INF, và in ra thành "+INF%". Hoặc tệ
    hơn, ai đó "chữa" bằng cách coi như +100% — một con số hoàn toàn bịa.

    `invert` cho các chỉ số mà TĂNG LÀ XẤU (đơn huỷ). Không có nó thì số
    đơn huỷ tăng vọt sẽ hiện màu xanh lá kèm mũi tên lên, đọc như tin vui.
--}}

@php
    $change = $before === null
        ? null
        : \App\Services\Analytics\AnalyticsService::change((float) $now, (float) $before);

    $good = $change === null ? null : ($invert ? $change < 0 : $change > 0);

    /*
     * ĐỊNH DẠNG KIỂU VIỆT: dấu chấm ngăn nghìn, dấu phẩy thập phân.
     *
     * Bản trước gọi number_format() với tham số mặc định, tức là kiểu
     * Anh — "so với 13,810,000 kỳ trước" và "46.1%". Đặt cạnh con số
     * chính "7.450.000₫" thì cùng một dòng có hai quy ước, và "13,810"
     * đọc theo kiểu Việt là mười ba phẩy tám.
     *
     * `format="tien"` cho chỉ số tiền: mốc so sánh cũng phải có đơn vị,
     * nếu không người đọc không biết 13.810.000 là đồng hay là lượt xem.
     */
    $moc = $before === null ? null : ($format === 'tien'
        ? \App\Services\Shop\Money::format((string) round((float) $before))
        : number_format((float) $before, 0, ',', '.'));
@endphp

@if($before !== null)
    <span class="admin-trend {{ $change === null ? 'admin-trend--flat' : ($good ? 'admin-trend--up' : 'admin-trend--down') }}">
        @if($change === null)
            kỳ trước chưa có dữ liệu
        @elseif($change == 0)
            không đổi so với kỳ trước
        @else
            {{ $change > 0 ? '▲' : '▼' }}
            {{ number_format(abs($change), 1, ',', '.') }}%
            <span class="admin-trend__base">so với {{ $moc }} kỳ trước</span>
        @endif
    </span>
@endif
