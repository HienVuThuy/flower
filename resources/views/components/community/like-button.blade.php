@props(['post', 'camXuc' => null, 'count' => 0])

{{--
    NÚT CẢM XÚC — biểu mẫu thật, chạy được không cần JavaScript.

    Nút chính bấm phát là "Thích" (hoặc bỏ cảm xúc đang có); mũi tên bên cạnh mở
    bảng năm cảm xúc. Facebook dùng hover để mở bảng — hover thì trên điện thoại
    không có, và bàn phím cũng không tới được; <details> thì cả ba cách dùng đều
    mở được.

    Có JavaScript thì community.js gửi bằng fetch và đổi tại chỗ: bấm giữa bảng
    tin không tải lại trang rồi nhảy về đầu danh sách.
--}}
@php
    $hienTai = $camXuc ? \App\Enums\CommunityReaction::tryFrom($camXuc) : null;
@endphp

@auth
    <div class="cam-xuc">
        <form method="POST" action="{{ route('shop.community.like', $post->id) }}" data-toggle-json data-loai="thich">
            @csrf
            {{-- Bấm lại đúng cảm xúc đang có = bỏ cảm xúc; chưa có gì thì mặc định là "Thích". --}}
            <input type="hidden" name="cam_xuc" value="{{ $hienTai?->value ?? \App\Enums\CommunityReaction::macDinh()->value }}" data-cam-xuc-hien-tai>

            <button type="submit" class="post-action {{ $hienTai ? 'is-on ' . $hienTai->mau() : '' }}"
                    aria-pressed="{{ $hienTai ? 'true' : 'false' }}" data-thich="{{ $post->id }}">
                <x-site.icon :name="$hienTai?->icon() ?? 'hand-thumbs-up'" data-icon-thich />
                <span data-so-thich>{{ $count }}</span>
                <span class="post-action__nhan" data-nhan-thich>{{ $hienTai?->label() ?? 'Thích' }}</span>
            </button>
        </form>

        <details class="cam-xuc-chon">
            <summary class="cam-xuc-chon__nut" title="Chọn cảm xúc">
                <x-site.icon name="chevron-down" label="Chọn cảm xúc" />
            </summary>

            <div class="cam-xuc-chon__panel">
                @foreach(\App\Enums\CommunityReaction::cases() as $cx)
                    <form method="POST" action="{{ route('shop.community.like', $post->id) }}" data-toggle-json data-loai="thich">
                        @csrf
                        <input type="hidden" name="cam_xuc" value="{{ $cx->value }}">
                        <button type="submit" class="cam-xuc-chon__item {{ $cx->mau() }} {{ $hienTai === $cx ? 'is-on' : '' }}"
                                title="{{ $cx->label() }}" data-chon-cam-xuc="{{ $cx->value }}">
                            <x-site.icon :name="$cx->icon()" :label="$cx->label()" />
                        </button>
                    </form>
                @endforeach
            </div>
        </details>
    </div>
@else
    <a href="{{ route('login') }}" class="post-action" data-thich="{{ $post->id }}">
        <x-site.icon name="hand-thumbs-up" />
        <span data-so-thich>{{ $count }}</span>
        <span class="post-action__nhan">Thích</span>
    </a>
@endauth
