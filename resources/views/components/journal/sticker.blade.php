@props(['sticker', 'size' => 24, 'decorative' => false])

@php
    $s = $sticker instanceof \App\Enums\JournalSticker
        ? $sticker
        : \App\Enums\JournalSticker::tryFrom((string) $sticker);
@endphp

@if($s)
    <svg class="journal-sticker" width="{{ $size }}" height="{{ $size }}"
         viewBox="0 0 24 24" fill="none" stroke="currentColor"
         stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"
         @if($decorative)
             aria-hidden="true" focusable="false"
         @else
             role="img" aria-label="{{ $s->meaning() }}"
         @endif
         {{ $attributes }}>

        @switch($s)
            @case(\App\Enums\JournalSticker::Sprout)
                <path d="M12 21V11" />
                <path d="M12 13c0-2.5-1.6-4.4-4-5 0 2.6 1.5 4.6 4 5Z" />
                <path d="M12 11c0-2.9 1.9-5.1 4.6-5.8.2 3-1.7 5.4-4.6 5.8Z" />
                <path d="M5 21h14" />
                @break

            @case(\App\Enums\JournalSticker::Bloom)
                <circle cx="12" cy="9" r="2.2" />
                <path d="M12 6.8c.6-2 .2-3.4-1.2-4.3-1.1 1.4-1 2.9.2 4.3" />
                <path d="M14.1 8c1.8-1.1 2.4-2.4 1.9-4-1.7.4-2.6 1.6-2.7 3.4" />
                <path d="M13.6 11c2 .5 3.4.1 4.2-1.3-1.4-1-2.9-1-4.2.2" />
                <path d="M10.4 11c-2 .5-3.4.1-4.2-1.3 1.4-1 2.9-1 4.2.2" />
                <path d="M9.9 8C8.1 6.9 7.5 5.6 8 4c1.7.4 2.6 1.6 2.7 3.4" />
                <path d="M12 11.2V21" />
                <path d="M12 16c1.8 0 3-1 3.4-2.8-1.9-.3-3.1.6-3.4 2.8Z" />
                @break

            @case(\App\Enums\JournalSticker::Water)
                <path d="M12 3c2.6 3.1 4 5.4 4 7a4 4 0 0 1-8 0c0-1.6 1.4-3.9 4-7Z" />
                <path d="M4 18c3-1.4 5.7-1.4 8 0 2.3-1.4 5-1.4 8 0" />
                <path d="M4 21c3-1.4 5.7-1.4 8 0 2.3-1.4 5-1.4 8 0" />
                @break

            @case(\App\Enums\JournalSticker::Sun)
                <circle cx="12" cy="12" r="4" />
                <path d="M12 2v2.5M12 19.5V22M2 12h2.5M19.5 12H22" />
                <path d="M4.9 4.9 6.7 6.7M17.3 17.3l1.8 1.8M19.1 4.9l-1.8 1.8M6.7 17.3l-1.8 1.8" />
                @break

            @case(\App\Enums\JournalSticker::Fertilise)
                <path d="M7 4h10l-1.2 6.5H8.2Z" />
                <path d="M9.5 4V2.5h5V4" />
                <circle cx="8.5" cy="15" r=".9" />
                <circle cx="12" cy="17.5" r=".9" />
                <circle cx="15.5" cy="14.5" r=".9" />
                <circle cx="10.5" cy="20" r=".9" />
                <circle cx="14" cy="20.5" r=".9" />
                @break

            @case(\App\Enums\JournalSticker::Repot)
                <path d="M5 12h14l-1.6 8.5H6.6Z" />
                <path d="M4 9.5h16V12H4Z" />
                <path d="M9 6.5a3.5 3.5 0 0 1 6-2.2" />
                <path d="M15.2 2v2.4h-2.4" />
                @break

            @case(\App\Enums\JournalSticker::Prune)
                <circle cx="6.5" cy="18" r="2.5" />
                <circle cx="17.5" cy="18" r="2.5" />
                <path d="M8.3 16.2 18 3.5" />
                <path d="M15.7 16.2 6 3.5" />
                @break

            @case(\App\Enums\JournalSticker::Pest)
                <ellipse cx="12" cy="13.5" rx="4" ry="5" />
                <path d="M12 8.5V18.5" />
                <path d="M9.6 9.4 7.4 7.2M14.4 9.4l2.2-2.2" />
                <path d="M8 13H5M16 13h3M8.4 17l-2.2 2M15.6 17l2.2 2" />
                <path d="M10.2 6.6a2 2 0 0 1 3.6 0" />
                @break

            @case(\App\Enums\JournalSticker::Wilt)
                <path d="M8 21c0-6 1-10 4-13" />
                <path d="M12 8c2.6-.6 4.4.3 5.4 2.7-2.5 1-4.3.2-5.4-2.7Z" />
                <path d="M10 14c-2.3-.9-3.3-2.5-3-4.8 2.4.5 3.4 2.1 3 4.8Z" />
                <path d="M5 21h14" />
                @break

            @case(\App\Enums\JournalSticker::Heart)
                <path d="M12 20.3 4.9 13c-2-2-2-5.2 0-7.2s5.2-2 7.1 0c2-2 5.2-2 7.1 0s2 5.2 0 7.2Z" />
                @break

            @case(\App\Enums\JournalSticker::Star)
                <path d="m12 3 2.7 5.6 6.1.9-4.4 4.3 1 6.1-5.4-2.9-5.4 2.9 1-6.1L3.2 9.5l6.1-.9Z" />
                @break

            @case(\App\Enums\JournalSticker::Note)
                <path d="M6 3h12v18l-6-4.2L6 21Z" />
                <path d="M12 7v4" />
                <circle cx="12" cy="13.4" r=".7" fill="currentColor" stroke="none" />
                @break
        @endswitch
    </svg>
@endif
