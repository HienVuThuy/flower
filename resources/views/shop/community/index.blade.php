@extends('layouts.app')

@section('title', 'Góc cây của bạn')

@section('meta_description', 'Ảnh cây, ban công và góc xanh do chính khách hàng Angevil chia sẻ.')

@section('content')

@php
    $thuong = \App\Services\Points\CommunityReward::class;
    $cacTab = [
        'moi-nhat' => 'Mới nhất',
        'thich-nhieu' => 'Được thích nhiều',
    ];

    if (auth()->check()) {
        $cacTab['da-luu'] = 'Đã lưu';
        $cacTab['cua-toi'] = 'Bài của tôi';
    }
@endphp

<section class="section-sm">
    <div class="container-shop">

        <x-site.breadcrumb :items="[['label' => 'Góc cây của bạn']]" />

        <div class="section-header">
            <div>
                <h1 class="text-h1 section-header__title">Góc cây của bạn</h1>
                <p class="text-body-sm mt-2 mb-0" style="max-width: 62ch;">
                    Ảnh cây, ban công, góc bàn làm việc — do chính người mua chia sẻ.
                    Không có người theo dõi, không có bảng tin theo thuật toán; chỉ là chỗ khoe cây và hỏi nhau cách chăm.
                </p>
            </div>
        </div>

        <div class="row g-4 goc-cay">

            {{-- ---------- CỘT TRÁI: ĐIỀU HƯỚNG ---------- --}}
            <div class="col-lg-3 d-none d-lg-block">
                {{-- Cả cột là MỘT khối dính: từng thẻ dính riêng thì thẻ dưới trượt đè lên thẻ trên. --}}
                <div class="goc-cay__cot">
                <nav class="goc-cay-nav" aria-label="Mục Góc cây">
                    @foreach($cacTab as $ma => $nhan)
                        <a href="{{ route('shop.community.index', ['tab' => $ma]) }}"
                           class="goc-cay-nav__item {{ $tab === $ma ? 'is-active' : '' }}"
                           @if($tab === $ma) aria-current="page" @endif>
                            <span>{{ $nhan }}</span>
                            @if($ma === 'cua-toi' && $soChoDuyetCuaToi > 0)
                                <span class="goc-cay-nav__badge">{{ $soChoDuyetCuaToi }} chờ duyệt</span>
                            @endif
                        </a>
                    @endforeach
                </nav>

                <div class="surface-card p-3 mt-3 goc-cay-quytac">
                    <h2 class="text-h5 mb-2">Quy tắc ngắn</h2>
                    <ul class="mb-0">
                        <li>Ảnh cây của chính bạn, không lấy ảnh người khác.</li>
                        <li>Không quảng cáo, không rao bán, không số điện thoại.</li>
                        <li>Thấy nội dung xấu thì bấm <strong>Báo cáo</strong> ở menu "⋯".</li>
                    </ul>
                </div>
                </div>
            </div>

            {{-- ---------- GIỮA: BẢNG TIN ---------- --}}
            <div class="col-lg-6">

                @auth
                    <div class="composer-bar surface-card">
                        <span class="avatar" aria-hidden="true">{{ mb_substr(auth()->user()->name, 0, 1) }}</span>
                        <button type="button" class="composer-bar__o" data-bs-toggle="modal" data-bs-target="#hop-dang-bai">
                            {{ auth()->user()->name }} ơi, hôm nay cây thế nào?
                        </button>
                        <button type="button" class="composer-bar__nut" data-bs-toggle="modal" data-bs-target="#hop-dang-bai">
                            <x-site.icon name="image" /> <span>Ảnh / video</span>
                        </button>
                    </div>
                @else
                    <div class="surface-card p-3 mb-3 d-flex flex-wrap gap-2 align-items-center justify-content-between">
                        <span>Đăng nhập để khoe cây, bình luận và lưu bài.</span>
                        <a href="{{ route('login', ['redirect' => route('shop.community.index', [], false)]) }}"
                           class="btn btn-secondary-brand btn-sm">Đăng nhập</a>
                    </div>
                @endauth

                {{-- Điều hướng cho màn hình hẹp: cùng danh sách, nằm ngang. --}}
                <nav class="goc-cay-nav goc-cay-nav--ngang d-lg-none" aria-label="Mục Góc cây">
                    @foreach($cacTab as $ma => $nhan)
                        <a href="{{ route('shop.community.index', ['tab' => $ma]) }}"
                           class="goc-cay-nav__item {{ $tab === $ma ? 'is-active' : '' }}">{{ $nhan }}</a>
                    @endforeach
                </nav>

                @forelse($posts as $post)
                    @include('shop.community.partials.post-card', ['post' => $post])
                @empty
                    <x-site.empty-state
                        :title="match($tab) {
                            'da-luu' => 'Bạn chưa lưu bài nào',
                            'cua-toi' => 'Bạn chưa đăng bài nào',
                            default => 'Chưa có bài nào',
                        }"
                        :text="match($tab) {
                            'da-luu' => 'Bấm menu ⋯ trên một bài rồi chọn Lưu bài để xem lại ở đây.',
                            'cua-toi' => 'Bấm ô soạn bài ở trên để khoe cây của bạn.',
                            default => 'Chưa ai đăng gì ở đây. Bạn có thể là người đầu tiên.',
                        }" />
                @endforelse

                <div class="mt-4">{{ $posts->links() }}</div>
            </div>

            {{-- ---------- CỘT PHẢI: ĐIỂM THƯỞNG ---------- --}}
            <div class="col-lg-3 d-none d-lg-block">
                <div class="goc-cay__cot">
                <div class="surface-card p-3 goc-cay-thuong">
                    <h2 class="text-h5 mb-2">Đăng bài được điểm</h2>
                    {{-- Luật thưởng đọc từ đúng hằng số đang tính — không ghi tay con số. --}}
                    <p class="text-body-sm mb-2" data-luat-thuong>
                        Bài được duyệt: <strong>+{{ $thuong::CO_BAN }} điểm</strong>, có ảnh hoặc video thêm {{ $thuong::CO_ANH }},
                        bài nổi bật thêm {{ $thuong::NOI_BAT }}. Tối đa {{ $thuong::TOI_DA_MOI_TUAN }} bài được thưởng mỗi tuần.
                    </p>
                    <p class="text-body-sm mb-0">
                        Mỗi lượt thích người khác dành cho bài bạn: +{{ $thuong::LUOT_THICH }} điểm
                        (tối đa {{ $thuong::THICH_TOI_DA_MOI_TUAN }} điểm mỗi tuần).
                        <a href="{{ route('shop.profile.edit', ['muc' => 'diem-thuong']) }}">Đổi điểm lấy voucher</a>.
                    </p>
                </div>
                </div>
            </div>

        </div>
    </div>
</section>

@auth
    @include('shop.community.partials.composer-modal')
@endauth

@include('shop.community.partials.report-modal')

@endsection
