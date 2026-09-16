@props(['target'])

{{--
    BẢNG BIỂU TƯỢNG CẢM XÚC cho ô soạn bài / bình luận.

    Emoji là NỘI DUNG khách gõ, không phải icon giao diện (icon giao diện vẫn
    là SVG). <details> nên mở được không cần JavaScript; JS chỉ lo việc chèn
    vào đúng vị trí con trỏ — không có JS thì khách vẫn gõ emoji bằng bàn phím.
--}}
@php
    $nhom = [
        'Cây & hoa' => ['🌱', '🌿', '🍀', '🪴', '🌵', '🌴', '🌳', '🌸', '🌼', '🌻', '🌹', '🌷', '💐', '🍃', '🍂'],
        'Cảm xúc' => ['😀', '😄', '😊', '🥰', '😍', '🤩', '😂', '😎', '🤔', '😢', '😮', '🙂'],
        'Khác' => ['👍', '👏', '🙏', '❤️', '💚', '✨', '🔥', '🎉', '☀️', '💧', '🌧️', '🪟'],
    ];
@endphp

<details class="emoji-picker" data-emoji-picker data-target="{{ $target }}">
    <summary class="emoji-picker__toggle" title="Chèn biểu tượng cảm xúc">
        <x-site.icon name="emoji-smile" label="Chèn biểu tượng cảm xúc" />
    </summary>

    <div class="emoji-picker__panel">
        @foreach($nhom as $ten => $ds)
            <p class="emoji-picker__group">{{ $ten }}</p>
            <div class="emoji-picker__grid">
                @foreach($ds as $e)
                    <button type="button" class="emoji-picker__item" data-emoji="{{ $e }}" aria-label="Chèn {{ $e }}">{{ $e }}</button>
                @endforeach
            </div>
        @endforeach
    </div>
</details>
