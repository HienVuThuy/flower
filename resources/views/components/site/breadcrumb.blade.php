@props(['items' => []])

@php
    $home = ['label' => 'Trang chủ', 'url' => route('welcome')];

    $first = $items[0]['label'] ?? null;
    $crumbs = $first === 'Trang chủ' ? $items : array_merge([$home], $items);
@endphp

<x-site.json-ld :data="[
    '@context' => 'https://schema.org',
    '@type' => 'BreadcrumbList',
    'itemListElement' => collect($crumbs)->values()->map(fn ($c, $i) => array_filter([
        '@type' => 'ListItem',
        'position' => $i + 1,
        'name' => $c['label'],
        'item' => $c['url'] ?? null,
    ]))->all(),
]" />

<nav class="shop-breadcrumb" aria-label="breadcrumb">
    @foreach($crumbs as $item)
        @if(!$loop->last && ($item['url'] ?? null))
            <a href="{{ $item['url'] }}">{{ $item['label'] }}</a>
        @else
            <span aria-current="page">{{ $item['label'] }}</span>
        @endif

        @unless($loop->last)
            <span class="shop-breadcrumb__sep">/</span>
        @endunless
    @endforeach
</nav>
