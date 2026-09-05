@props(['icon', 'value', 'label', 'href' => null])

@php $tag = $href ? 'a' : 'div'; @endphp

<{{ $tag }} @if($href) href="{{ $href }}" @endif class="stat-card text-decoration-none">
    <span class="stat-card__figure"><x-site.icon :name="$icon" /></span>
    <span>
        <span class="stat-card__value d-block">{{ $value }}</span>
        <span class="stat-card__label">{{ $label }}</span>
    </span>
</{{ $tag }}>
