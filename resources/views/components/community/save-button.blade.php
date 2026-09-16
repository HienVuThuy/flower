@props(['post', 'saved' => false])

{{--
    LƯU BÀI để xem lại ở mục "Đã lưu". Cùng lối với nút thích: biểu mẫu thật,
    có JavaScript thì đổi tại chỗ.
--}}
@auth
    <form method="POST" action="{{ route('shop.community.save', $post->id) }}" class="d-inline"
          data-toggle-json data-loai="luu">
        @csrf
        <button type="submit" class="post-action {{ $saved ? 'is-on' : '' }}"
                aria-pressed="{{ $saved ? 'true' : 'false' }}" data-luu="{{ $post->id }}">
            <x-site.icon :name="$saved ? 'bookmark-fill' : 'bookmark'" data-icon-luu />
            <span class="post-action__nhan" data-nhan-luu>{{ $saved ? 'Đã lưu' : 'Lưu' }}</span>
        </button>
    </form>
@else
    <a href="{{ route('login') }}" class="post-action">
        <x-site.icon name="bookmark" />
        <span class="post-action__nhan">Lưu</span>
    </a>
@endauth
