    <div class="surface-card p-4">

        <h2 class="text-h4 mb-3">Tài khoản</h2>

        <dl class="profile-summary">
            <div class="profile-summary__row">
                <dt>Tham gia từ</dt>
                <dd><x-site.time :at="$user->created_at" format="d/m/Y" /></dd>
            </div>
            <div class="profile-summary__row">
                <dt>Đơn hàng</dt>
                <dd>{{ $orderCount }}</dd>
            </div>
            <div class="profile-summary__row">
                <dt>Sản phẩm yêu thích</dt>
                <dd>{{ $wishlistCount }}</dd>
            </div>
            <div class="profile-summary__row">
                <dt>Đánh giá đã viết</dt>
                <dd>{{ $reviewCount }}</dd>
            </div>
        </dl>

        {{--
            MỌI CON SỐ Ở TRÊN PHẢI CÓ ĐƯỜNG ĐI TỚI.

            Trước bản này, "Đánh giá đã viết: 4" là ngõ cụt —
            ba con số kia bấm được, riêng nó thì không. Trang
            Chính sách bảo mật hứa người dùng gỡ được đánh giá
            của mình, và điều đó đúng, nhưng nút gỡ chỉ nằm
            trên TRANG SẢN PHẨM: ai viết bốn bài cho bốn sản
            phẩm phải nhớ ra đủ bốn rồi mở từng trang.

            Ví voucher và Lịch chăm cây cũng thuộc khu tài
            khoản và cũng đã có trang riêng, nhưng chưa được
            nhắc tới ở đây — người dùng chỉ tới được chúng
            qua menu, tức là phải biết trước là chúng tồn tại.
        --}}
        <div class="profile-summary__links">
            @if(config('features.cart'))
                <a href="{{ route('shop.orders.index') }}" class="btn btn-ghost w-100">
                    <x-site.icon name="bag" /> Đơn hàng của tôi
                </a>
                <a href="{{ route('shop.addresses.index') }}" class="btn btn-ghost w-100 mt-2">
                    <x-site.icon name="geo-alt" /> Sổ địa chỉ
                </a>
                <a href="{{ route('shop.vouchers.index') }}" class="btn btn-ghost w-100 mt-2">
                    <x-site.icon name="tags" /> Ví voucher
                </a>
            @endif
            <a href="{{ route('shop.wishlist.index') }}" class="btn btn-ghost w-100 mt-2">
                <x-site.icon name="heart" /> Sản phẩm yêu thích
            </a>
            <a href="{{ route('shop.reviews.mine') }}" class="btn btn-ghost w-100 mt-2">
                <x-site.icon name="star" /> Đánh giá của tôi
            </a>
            <a href="{{ route('shop.care.index') }}" class="btn btn-ghost w-100 mt-2">
                <x-site.icon name="droplet" /> Lịch chăm cây
            </a>
        </div>

    </div>
