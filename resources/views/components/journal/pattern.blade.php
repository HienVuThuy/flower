@props(['pattern'])

@php
@endphp

<svg class="journal-pattern" aria-hidden="true" focusable="false"
     width="100%" height="100%" preserveAspectRatio="none">
    <defs>
        @switch($pattern)
            @case('leaves')
                <pattern id="jp-leaves" width="46" height="46" patternUnits="userSpaceOnUse"
                         patternTransform="rotate(12)">
                    <path d="M12 26c0-6 3.6-10.4 9-11.6.4 6.2-3 10.8-9 11.6Z"
                          fill="none" stroke="currentColor" stroke-width="1" />
                    <path d="M12 26c3-2.6 5.9-6 9-11.6"
                          fill="none" stroke="currentColor" stroke-width="1" />
                </pattern>
                @break

            @case('arches')
                <pattern id="jp-arches" width="40" height="26" patternUnits="userSpaceOnUse">
                    <path d="M0 26a10 10 0 0 1 20 0M20 26a10 10 0 0 1 20 0"
                          fill="none" stroke="currentColor" stroke-width="1" />
                </pattern>
                @break

            @case('dots')
                <pattern id="jp-dots" width="22" height="22" patternUnits="userSpaceOnUse">
                    <circle cx="4" cy="4" r="1.3" fill="currentColor" />
                    <circle cx="15" cy="15" r="1.3" fill="currentColor" />
                </pattern>
                @break

            @case('grain')
                <pattern id="jp-grain" width="28" height="28" patternUnits="userSpaceOnUse"
                         patternTransform="rotate(-20)">
                    <path d="M2 6h7M14 13h9M5 20h6M18 25h6" stroke="currentColor"
                          stroke-width="1" stroke-linecap="round" />
                </pattern>
                @break

            @case('pebbles')
                <pattern id="jp-pebbles" width="44" height="34" patternUnits="userSpaceOnUse">
                    <ellipse cx="11" cy="10" rx="6" ry="4.4" fill="none"
                             stroke="currentColor" stroke-width="1" />
                    <ellipse cx="31" cy="24" rx="7.5" ry="5" fill="none"
                             stroke="currentColor" stroke-width="1" />
                </pattern>
                @break
        @endswitch
    </defs>

    <rect width="100%" height="100%" fill="url(#jp-{{ $pattern }})" />
</svg>
