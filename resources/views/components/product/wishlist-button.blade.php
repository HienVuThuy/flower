@props([
    'product',
    'active' => false,
    'compact' => false,
])

{{--
    Nút thêm/bỏ yêu thích.

    Khách chưa đăng nhập KHÔNG bị ẩn nút — bấm vào sẽ tới trang đăng nhập.
    Ẩn hẳn thì họ không biết tính năng này tồn tại; hiện rồi bấm không ăn
    gì thì tệ hơn nữa.
--}}

@auth

    {{--
        data-wishlist: resources/js/wishlist.js gửi biểu mẫu này bằng
        fetch rồi đổi ngay trái tim tại chỗ, không tải lại trang.

        VÌ SAO ĐÁNG LÀM: nút này nằm trên từng thẻ sản phẩm giữa một
        trang danh sách dài. Tải lại trang để đổi một cái icon là ném
        khách về đầu trang và bắt họ cuộn lại tìm chỗ cũ — thích ba sản
        phẩm là ba lần cuộn lại.

        Không có JavaScript thì đây vẫn là biểu mẫu POST bình thường.
    --}}
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

    {{-- Nhớ đường dẫn hiện tại để đăng nhập xong quay lại đúng sản phẩm. --}}
    <a href="{{ route('login') }}"
       class="btn-wishlist @if($compact) btn-wishlist--sm @endif"
       title="Đăng nhập để lưu yêu thích">
        <x-site.icon name="heart" />
        <span class="visually-hidden">Đăng nhập để lưu yêu thích</span>
    </a>

@endauth
