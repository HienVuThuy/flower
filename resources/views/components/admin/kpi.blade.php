@props([
    'label',

    'note' => null,

    'now' => null,
    'before' => null,

    'invert' => false,

    'format' => 'so',

    'href' => null,
])

@php $tag = $href ? 'a' : 'div'; @endphp

<{{ $tag }} @if($href) data-admin-link href="{{ $href }}" @endif class="admin-kpi">

    <span class="admin-kpi__label">{{ $label }}</span>

    <span class="admin-kpi__value">{{ $slot }}</span>

    @if($now !== null)
        <x-admin.trend :now="$now" :before="$before" :invert="$invert" :format="$format" />
    @endif

    @if($note)
        <span class="admin-kpi__note">{{ $note }}</span>
    @endif

</{{ $tag }}>
