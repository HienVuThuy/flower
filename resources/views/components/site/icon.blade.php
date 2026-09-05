@props(['name', 'label' => null])

{{--
    Icon chức năng. Mặc định aria-hidden (icon trang trí cạnh chữ);
    truyền `label` khi icon đứng một mình để screen reader đọc được.

    ⚠️ PHẢI DÙNG $attributes->merge(), KHÔNG ĐƯỢC in {{ $attributes }} trần.

    Bản trước viết `class="icon" ... {{ $attributes }}`, nên khi nơi gọi
    truyền thêm class thì thẻ <svg> có HAI thuộc tính class. HTML quy định
    thuộc tính trùng thì lấy CÁI ĐẦU TIÊN — tức class của nơi gọi bị vứt
    đi hoàn toàn, âm thầm, không lỗi, không cảnh báo.

    Hậu quả nhìn thấy được: kính lúp trong ô tìm kiếm mất
    `.header-search__icon` nên mất luôn `position: absolute`, rơi ra ngoài
    khung và nằm lệch hẳn lên trên. Mọi icon khác được truyền class cũng
    hỏng đúng như vậy — chỉ là ở chỗ dùng flex thì trông vẫn tạm ổn nên
    không ai để ý.

    merge() gộp hai danh sách class làm một, giữ `icon` và thêm class của
    nơi gọi vào sau.
--}}
<svg
    {{ $attributes->merge(['class' => 'icon']) }}
    width="1em"
    height="1em"
    fill="currentColor"
    @if($label)
        role="img" aria-label="{{ $label }}"
    @else
        aria-hidden="true" focusable="false"
    @endif
><use href="#i-{{ $name }}"></use></svg>
