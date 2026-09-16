@props([
    'product',
    'active' => false,
    'compact' => false,
])

{{-- Nút thêm/bỏ yêu thích. --}}

@auth

    <form method="POST" action="{{ route('shop.wishlist.toggle', $product) }}" class="d-inline" data-wishlist>
        @csrf
        <button type="submit"
                class="btn-wishlist @if($active) is-active @endif @if($compact) btn-wishlist--sm @endif"
                title="{{ $active ? 'Bỏ khỏi yêu thích' : 'Thêm vào yêu thích' }}"
                aria-pressed="{{ $active ? 'true' : 'false' }}"
                data-wishlist-button>
            <x-site.icon :name="$active ? 'heart-fill' : 'heart'" />
            <span class="visually-hidden">
                {{ $active ? 'Bỏ khỏi yêu thích' : 'Thêm vào yêu thích' }}
            </span>
        </button>
    </form>

@else

    <a href="{{ route('login') }}"
       class="btn-wishlist @if($compact) btn-wishlist--sm @endif"
       title="Đăng nhập để lưu yêu thích">
        <x-site.icon name="heart" />
        <span class="visually-hidden">Đăng nhập để lưu yêu thích</span>
    </a>

@endauth
