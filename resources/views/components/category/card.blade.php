@props(['category'])

<a href="{{ route('shop.categories.show', $category) }}" class="category-card">

    @if($category->image)
        <x-site.image
            :path="$category->image"
            :alt="$category->name"
            class="category-card__image"
        />
    @else
        <div class="category-card__placeholder">
            <x-site.leaf-placeholder />
        </div>
    @endif

    <div class="category-card__content">
        <div class="category-card__title">{{ $category->name }}</div>

        @if(isset($category->products_count))
            <div class="category-card__meta">{{ $category->products_count }} sản phẩm</div>
        @endif
    </div>

</a>
