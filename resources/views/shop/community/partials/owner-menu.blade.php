{{--
    BA NÚT CHỦ BÀI TỰ QUẢN LÝ BÀI CỦA MÌNH — nằm trong menu "⋯".

    Để riêng một partial vì bảng tin và trang một bài cùng dùng: chép hai bản
    thì sửa luật một chỗ, chỗ kia vẫn còn nút cũ.

    Mỗi nút là MỘT form POST + @method('PATCH') + @csrf, không phải liên kết:
    đây là thao tác đổi dữ liệu, mà GET thì trình duyệt hay công cụ quét trang
    bấm hộ lúc nào không hay.

    Nút "Ghim" chỉ hiện khi bài ĐANG HIỂN THỊ (hoặc đang ghim, để còn bỏ ghim) —
    đúng bằng luật trong PostOwner::doiGhim(), khỏi bày ra nút bấm vào chỉ để
    nhận lỗi.
--}}
@php
    $dangTuAn = $post->tuAn();
    $daGhim = $post->daGhim();
    $dangKhoa = $post->khoaBinhLuan();
    $ghimDuoc = $daGhim || ($post->isApproved() && ! $post->isHidden() && ! $dangTuAn);
@endphp

<form method="POST" action="{{ route('shop.community.owner.hide', $post->id) }}">
    @csrf
    @method('PATCH')
    <button type="submit" class="post-menu__item">
        <x-site.icon :name="$dangTuAn ? 'eye' : 'eye-slash'" />
        {{ $dangTuAn ? 'Hiện lại bài' : 'Tạm ẩn bài' }}
    </button>
</form>

@if($ghimDuoc)
    <form method="POST" action="{{ route('shop.community.owner.pin', $post->id) }}">
        @csrf
        @method('PATCH')
        <button type="submit" class="post-menu__item">
            <x-site.icon :name="$daGhim ? 'pin-angle-fill' : 'pin-angle'" />
            {{ $daGhim ? 'Bỏ ghim' : 'Ghim lên trang cá nhân' }}
        </button>
    </form>
@endif

<form method="POST" action="{{ route('shop.community.owner.lock', $post->id) }}">
    @csrf
    @method('PATCH')
    <button type="submit" class="post-menu__item">
        <x-site.icon :name="$dangKhoa ? 'unlock' : 'lock'" />
        {{ $dangKhoa ? 'Mở lại bình luận' : 'Khoá bình luận' }}
    </button>
</form>
