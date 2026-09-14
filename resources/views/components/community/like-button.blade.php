@props(['post', 'liked' => false, 'count' => 0])

{{--
    NÚT THÍCH — một biểu mẫu thật, chạy được không cần JavaScript.
    Khách chưa đăng nhập thấy số lượt và được đưa tới trang đăng nhập.
--}}
@auth
    <form method="POST" action="{{ route('shop.community.like', $post->id) }}" class="d-inline">
        @csrf
        <button type="submit" class="community-like {{ $liked ? 'is-liked' : '' }}"
                aria-pressed="{{ $liked ? 'true' : 'false' }}" data-thich="{{ $post->id }}">
            <x-site.icon :name="$liked ? 'heart-fill' : 'heart'" />
            <span data-so-thich>{{ $count }}</span>
            <span class="visually-hidden">{{ $liked ? 'Bỏ thích' : 'Thích' }}</span>
        </button>
    </form>
@else
    <a href="{{ route('login') }}" class="community-like" data-thich="{{ $post->id }}">
        <x-site.icon name="heart" />
        <span data-so-thich>{{ $count }}</span>
        <span class="visually-hidden">Đăng nhập để thích</span>
    </a>
@endauth
