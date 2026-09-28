{{--
    TRANG CHUYỂN TIẾP SANG CỔNG THANH TOÁN.

    Vì sao cần trang này: nút "Đặt hàng" (và nút trả tiền phiếu chăm cây hộ) là BIỂU MẪU.
    Chính sách bảo mật của trang đặt form-action 'self', nên trình duyệt chặn lượt chuyển
    hướng đi thẳng từ biểu mẫu ra tên miền của cổng thanh toán — bấm xong màn hình đứng im.
    Trả về trang này (cùng tên miền) thì lượt đi từ biểu mẫu kết thúc hợp lệ, rồi trang tự
    chuyển sang cổng như một lượt đi mới.
--}}

@extends('layouts.app')

@section('title', 'Đang chuyển tới ' . $cong)

@push('head')
    <meta http-equiv="refresh" content="0;url={{ $url }}">
@endpush

@section('content')

<section class="section">
    <div class="container-shop">

        <div class="row justify-content-center">
            <div class="col-lg-7 col-xl-6">

                <div class="checkout-panel text-center" data-chuyen-cong-thanh-toan>

                    <h1 class="text-h2 mb-3">Đang chuyển tới {{ $cong }}…</h1>

                    <p class="mb-4">
                        Vui lòng chờ vài giây. Đơn hàng của bạn đã được ghi nhận,
                        chưa trừ tiền cho tới khi bạn xác nhận trên {{ $cong }}.
                    </p>

                    <a href="{{ $url }}" rel="nofollow" class="btn btn-primary-brand btn-lg">
                        Bấm vào đây nếu trang không tự chuyển
                    </a>

                    @isset($quayLai)
                        <p class="mt-4 mb-0">
                            <a href="{{ $quayLai }}" class="text-muted">Quay lại</a>
                        </p>
                    @endisset

                </div>

            </div>
        </div>

    </div>
</section>

<script>
    /* Chuyển ngay bằng JavaScript; thẻ meta refresh ở trên lo trường hợp tắt JavaScript. */
    window.location.replace(@json($url));
</script>

@endsection
