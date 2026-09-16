@props(['name', 'label' => null])

{{-- Icon chức năng. --}}
{{-- ⚠️ PHẢI DÙNG $attributes->merge(), KHÔNG ĐƯỢC in {{ $attributes }} trần. --}}
<svg
    {{ $attributes->merge(['class' => 'icon']) }}
    width="1em"
    height="1em"
    fill="currentColor"
    @if($label)
        role="img" aria-label="{{ $label }}"
    @else
        aria-hidden="true" focusable="false"
    @endif
><use href="#i-{{ $name }}"></use></svg>
