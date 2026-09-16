@props(['comment', 'camXuc' => null, 'count' => 0, 'tomTat' => []])

{{--
    CẢM XÚC DƯỚI MỘT BÌNH LUẬN — bản nhỏ của nút dưới bài.

    Cùng cách làm: biểu mẫu thật (chạy không cần JavaScript), bảng chọn trong
    <details> mở được bằng chuột, phím và chạm; có JavaScript thì rê chuột vào
    là bảng tự hiện và bấm không tải lại trang.
--}}
@php
    $hienTai = $camXuc ? \App\Enums\CommunityReaction::tryFrom($camXuc) : null;
@endphp

@auth
    <span class="cam-xuc cam-xuc--nho">
        <form method="POST" action="{{ route('shop.community.comment.react', $comment->id) }}" data-toggle-json data-loai="thich">
            @csrf
            <input type="hidden" name="cam_xuc" value="{{ $hienTai?->value ?? \App\Enums\CommunityReaction::macDinh()->value }}" data-cam-xuc-hien-tai>

            <button type="submit" class="comment__tool {{ $hienTai ? 'is-on ' . $hienTai->mau() : '' }}"
                    aria-pressed="{{ $hienTai ? 'true' : 'false' }}" data-thich="bl-{{ $comment->id }}">
                <span data-nhan-thich>{{ $hienTai?->label() ?? 'Thích' }}</span>
                <span class="visually-hidden" data-so-thich>{{ $count }}</span>
            </button>
        </form>

        <details class="cam-xuc-chon cam-xuc-chon--nho">
            <summary class="cam-xuc-chon__nut" title="Chọn cảm xúc">
                <x-site.icon name="chevron-down" label="Chọn cảm xúc cho bình luận" />
            </summary>

            <div class="cam-xuc-chon__panel">
                @foreach(\App\Enums\CommunityReaction::cases() as $cx)
                    <form method="POST" action="{{ route('shop.community.comment.react', $comment->id) }}" data-toggle-json data-loai="thich">
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
    </span>
@endauth

{{-- Tổng cảm xúc của bình luận: ẩn hẳn khi chưa có ai bày tỏ, để dòng công cụ không rối. --}}
<span class="cam-xuc-tomtat cam-xuc-tomtat--nho" data-tom-tat-thich @if($count < 1) hidden @endif>
    @foreach(collect($tomTat)->take(3) as $ct)
        @php($cx = \App\Enums\CommunityReaction::tryFrom($ct['loai']))
        @if($cx)
            <span class="cam-xuc-tomtat__icon {{ $cx->mau() }}" title="{{ $cx->label() }}: {{ $ct['so'] }}">
                <x-site.icon :name="$cx->icon()" />
            </span>
        @endif
    @endforeach
    <span data-so-cam-xuc>{{ $count }}</span>
</span>
