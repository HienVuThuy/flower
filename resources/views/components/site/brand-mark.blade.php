@props(['size' => 24])

{{-- DẤU HIỆU THƯƠNG HIỆU — mầm cây trong khung tròn. --}}
<svg
    xmlns="http://www.w3.org/2000/svg"
    viewBox="0 0 48 48"
    fill="none"
    width="{{ $size }}"
    height="{{ $size }}"
    {{ $attributes }}
>
    <circle cx="24" cy="24" r="21" stroke="currentColor" stroke-width="2.5" opacity="0.35"/>

    <path
        d="M24 37C24 30 22.5 25 20 21"
        stroke="currentColor"
        stroke-width="2.6"
        stroke-linecap="round"
    />

    <path
        d="M24 26C24 26 25 14 35 11C35 11 36 23 24 26Z"
        fill="currentColor"
    />

    <path
        d="M22 30C22 30 20 22 13 21C13 21 13 30 22 30Z"
        fill="currentColor"
        opacity="0.75"
    />
</svg>
