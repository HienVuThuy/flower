@extends('layouts.app')

@section('title', 'Thử lại sau ít phút')

@section('content')

{{--
    429 xuất hiện khi khách bấm tra cứu đơn quá nhiều lần trong một phút.
    Trang mặc định của Laravel là "Too Many Requests" bằng tiếng Anh —
    khách gõ nhầm số điện thoại vài lần rồi gặp câu đó sẽ tưởng website
    hỏng. Ở đây nói rõ chuyện gì đang xảy ra và phải làm gì.
--}}

<div class="auth-shell">

    <div class="surface-card auth-card text-center">

        <div class="auth-card__mark d-flex align-items-center justify-content-center" style="color: var(--semantic-warning);">
            <x-site.icon name="arrow-repeat" style="font-size: 2rem;" />
        </div>

        <h1 class="text-h3 mb-2">Bạn thao tác hơi nhanh</h1>

        <p class="text-body-sm text-muted mb-4">
            Website tạm khoá thao tác này trong ít phút để bảo vệ thông tin
            đơn hàng của khách. Vui lòng chờ một lát rồi thử lại.
        </p>

        <a href="{{ route('welcome') }}" class="btn btn-primary-brand">
            Về trang chủ
        </a>

    </div>

</div>

@endsection
