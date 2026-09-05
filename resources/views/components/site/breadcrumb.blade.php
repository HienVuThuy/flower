@props(['items' => []])

@php
    /*
     * LUÔN BẮT ĐẦU BẰNG "TRANG CHỦ".
     * ============================================================
     * LỖI ĐÃ SỬA: trên /san-pham, cả dải này chỉ có đúng một chữ "Sản
     * phẩm" — một chữ xám, không bấm được, nằm ngay trên dòng nhãn cũng
     * viết "SẢN PHẨM" và tiêu đề "Tất cả sản phẩm". Ba lần cùng một từ,
     * xếp chồng lên nhau.
     *
     * Vấn đề không phải ở chỗ lặp chữ mà ở chỗ NÓ KHÔNG PHẢI MỘT ĐƯỜNG
     * DẪN. Breadcrumb tồn tại để nói "bạn đang ở đâu trong cây trang" và
     * cho bấm ngược lên. Một mục duy nhất, không link, thì không nói
     * được vị trí (đứng một mình thì so với cái gì?) và không dẫn đi
     * đâu — nó chỉ là cái nhãn thứ hai của trang.
     *
     * Thêm "Trang chủ" vào đầu là biến nó trở lại thành đường dẫn:
     * "Trang chủ / Sản phẩm" nói đúng một điều mà tiêu đề không nói, và
     * bấm được.
     *
     * TỰ THÊM chứ không bắt 18 trang tự khai: bỏ sót một trang là trang
     * đó lại rơi về đúng lỗi cũ, và không ai phát hiện vì trông vẫn "có
     * breadcrumb".
     */
    $home = ['label' => 'Trang chủ', 'url' => route('welcome')];

    /*
     * Trang nào đã tự khai "Trang chủ" thì không thêm lần nữa — hiện
     * chưa trang nào làm vậy, nhưng chỗ này phải chịu được điều đó chứ
     * không hiện "Trang chủ / Trang chủ / ...".
     */
    $first = $items[0]['label'] ?? null;
    $crumbs = $first === 'Trang chủ' ? $items : array_merge([$home], $items);
@endphp

<nav class="shop-breadcrumb" aria-label="breadcrumb">
    @foreach($crumbs as $item)
        @if(!$loop->last && ($item['url'] ?? null))
            <a href="{{ $item['url'] }}">{{ $item['label'] }}</a>
        @else
            <span aria-current="page">{{ $item['label'] }}</span>
        @endif

        @unless($loop->last)
            <span class="shop-breadcrumb__sep">/</span>
        @endunless
    @endforeach
</nav>
