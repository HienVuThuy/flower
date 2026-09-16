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
            <div class="profile-summary__row">
                <dt>Điểm thưởng</dt>
                <dd><a href="{{ route('shop.profile.edit', ['muc' => 'diem-thuong']) }}">{{ number_format($diemSoDu, 0, ',', '.') }}</a></dd>
            </div>
        </dl>

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
