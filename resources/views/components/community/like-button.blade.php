@props(['post', 'liked' => false, 'count' => 0])

{{--
    NÚT THÍCH — một biểu mẫu thật, chạy được không cần JavaScript.

    Có JavaScript thì community.js gửi bằng fetch và đổi trạng thái tại chỗ
    (data-toggle-json): bấm thích giữa bảng tin không còn tải lại trang và
    nhảy về đầu danh sách. Khách chưa đăng nhập thấy số lượt và được đưa tới
    trang đăng nhập.
--}}
@auth
    <form method="POST" action="{{ route('shop.community.like', $post->id) }}" class="d-inline"
          data-toggle-json data-loai="thich">
        @csrf
        <button type="submit" class="post-action {{ $liked ? 'is-on' : '' }}"
                aria-pressed="{{ $liked ? 'true' : 'false' }}" data-thich="{{ $post->id }}">
            <x-site.icon :name="$liked ? 'heart-fill' : 'heart'" data-icon-thich />
            <span data-so-thich>{{ $count }}</span>
            <span class="post-action__nhan">Thích</span>
        </button>
    </form>
@else
    <a href="{{ route('login') }}" class="post-action" data-thich="{{ $post->id }}">
        <x-site.icon name="heart" />
        <span data-so-thich>{{ $count }}</span>
        <span class="post-action__nhan">Thích</span>
    </a>
@endauth
