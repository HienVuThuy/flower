{{-- BA NÚT CHỦ BÀI TỰ QUẢN LÝ BÀI CỦA MÌNH — nằm trong menu "⋯". --}}
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
