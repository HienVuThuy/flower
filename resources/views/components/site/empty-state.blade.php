@props(['title', 'text' => null])

<div class="empty-state">
    <x-site.leaf-placeholder class="empty-state__figure" />
    <div class="empty-state__title">{{ $title }}</div>
    @if($text)
        <p class="text-body-sm mb-0">{{ $text }}</p>
    @endif

    @if(isset($actions))
        <div class="empty-state__actions">{{ $actions }}</div>
    @endif
</div>
