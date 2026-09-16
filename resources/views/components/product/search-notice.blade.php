@props(['terms', 'relaxed' => false, 'total' => null])

{{-- Nói cho khách biết hệ thống đã làm gì với từ khoá của họ. --}}

@if($terms->isNotEmpty())

    @if($terms->wasCorrected())

        <div class="search-notice search-notice--corrected">
            <x-site.icon name="search" class="search-notice__icon" />
            <p class="search-notice__text">
                Không có sản phẩm nào tên
                <strong>&laquo;{{ $terms->original }}&raquo;</strong>.
                Đang hiển thị kết quả cho
                <strong>&laquo;{{ $terms->suggestion() }}&raquo;</strong>.
            </p>
        </div>

    @elseif($relaxed)

        <div class="search-notice search-notice--relaxed">
            <x-site.icon name="search" class="search-notice__icon" />
            @php
                $quotedTokens = collect($terms->tokens)
                    ->map(fn (string $token) => '<strong>&laquo;'.e($token).'&raquo;</strong>')
                    ->implode(' và ');
            @endphp

            <p class="search-notice__text">
                Không có sản phẩm nào khớp đồng thời {!! $quotedTokens !!}.
                Dưới đây là những sản phẩm khớp một phần từ khoá.
            </p>
        </div>

    @elseif($terms->alternative)

        <div class="search-notice search-notice--hint">
            <x-site.icon name="search" class="search-notice__icon" />
            <p class="search-notice__text">
                @if($total !== null)
                    {{ $total }} kết quả cho
                    <strong>&laquo;{{ $terms->original }}&raquo;</strong>.
                @endif
                Bạn có muốn tìm
                <a href="{{ request()->fullUrlWithQuery(['q' => $terms->alternative, 'page' => null]) }}">&laquo;{{ $terms->alternative }}&raquo;</a>?
            </p>
        </div>

    @endif

@endif
