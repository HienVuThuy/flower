@extends('layouts.app')

@section('title', 'Lịch chăm cây')

@section('content')

<section class="section-sm">
    <div class="container-shop">

        <x-site.breadcrumb :items="[['label' => 'Lịch chăm cây']]" />

        <div class="section-header">
            <div>
                <span class="text-label section-header__eyebrow d-block">Sau khi mua</span>
                <h1 class="text-h2 section-header__title">Lịch chăm cây của bạn</h1>
                <p class="mb-0">
                    Lịch tự tạo khi đơn hàng được giao, theo chu kỳ của từng loại cây.
                </p>
            </div>
        </div>

        @if(! $notifyEnabled)
            <div class="search-notice search-notice--corrected">
                <x-site.icon name="envelope" class="search-notice__icon" />
                <p class="search-notice__text">
                    Bạn đang <strong>tắt toàn bộ thư nhắc chăm cây</strong>, nên những lịch
                    dưới đây sẽ không gửi thư nào.
                    <a href="{{ route('shop.profile.edit') }}">Bật lại trong Hồ sơ tài khoản</a>.
                </p>
            </div>
        @endif

        @if($reminders->isEmpty())

            <div class="surface-card empty-state">
                <p class="empty-state__title">Chưa có lịch chăm nào.</p>
                <p class="mb-0">
                    Lịch được tạo khi đơn hàng chuyển sang <strong>đã giao</strong>, và chỉ với
                    những cây mà cửa hàng có khai chu kỳ tưới/bón. Hoa cắt cành không có
                    lịch chăm định kỳ.
                </p>
            </div>

        @else

            <div class="care-list">
                @foreach($reminders as $reminder)
                    @php($days = $reminder->daysUntilDue())

                    <div class="care-item {{ $reminder->is_active ? '' : 'is-off' }}">

                        <x-site.icon :name="$reminder->kind->icon()" class="care-item__icon" />

                        <div class="care-item__body">
                            <p class="care-item__title">
                                {{ $reminder->kind->label() }} &middot; {{ $reminder->product->name }}
                            </p>

                            <p class="care-item__meta">
                                @if(! $reminder->is_active)
                                    Đang tắt
                                @elseif($days < 0)
                                    <span class="care-item__late">Quá hạn {{ abs($days) }} ngày</span>
                                @elseif($days === 0)
                                    <span class="care-item__today">Hôm nay</span>
                                @else
                                    Còn {{ $days }} ngày
                                @endif

                                &middot; mỗi {{ $reminder->interval_days }} ngày
                                &middot; <x-site.time :at="$reminder->next_due_at" format="d/m/Y" />
                            </p>
                        </div>

                        <div class="care-item__actions">
                            @if($reminder->is_active)
                                <form method="POST" action="{{ route('shop.care.done', $reminder) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-secondary-brand btn-sm">
                                        Vừa làm xong
                                    </button>
                                </form>
                            @endif

                            <form method="POST" action="{{ route('shop.care.toggle', $reminder) }}">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="btn btn-ghost btn-sm">
                                    {{ $reminder->is_active ? 'Tắt' : 'Bật lại' }}
                                </button>
                            </form>
                        </div>

                    </div>
                @endforeach
            </div>

            <p class="text-caption mt-4 mb-0">
                Thư nhắc gửi lúc 8 giờ sáng ngày tới hạn. Muốn tắt hết một lần thì
                dùng công tắc trong <a href="{{ route('shop.profile.edit') }}">Hồ sơ tài khoản</a>.
            </p>

        @endif

    </div>
</section>

@endsection
