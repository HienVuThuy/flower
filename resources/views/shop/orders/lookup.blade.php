@extends('layouts.app')

@section('title', 'Tra cứu đơn hàng')

@section('content')

<section class="section">
    <div class="container-shop">

        <div class="row g-4 g-lg-5 justify-content-center">

            <div class="col-lg-9 col-xl-8">

                <div class="text-center mb-4">
                    <span class="text-label d-block mb-2">Tra cứu đơn hàng</span>
                    <h1 class="text-h1 mb-3 text-balance">Đơn của bạn đang ở đâu?</h1>
                    <p class="mb-0">
                        Nhập mã đơn cùng số điện thoại bạn đã dùng khi đặt hàng.
                        Không cần tài khoản.
                    </p>
                </div>

                <div class="surface-card p-4 p-md-5">

                    <form action="{{ route('shop.orders.lookup.find') }}" method="POST">

                        @csrf

                        <div class="row g-3">

                            <div class="col-md-6">
                                <label class="text-label d-block mb-2" for="order_number">Mã đơn hàng</label>
                                <input
                                    type="text"
                                    id="order_number"
                                    name="order_number"
                                    value="{{ old('order_number') }}"
                                    class="form-control text-uppercase @error('order_number') is-invalid @enderror"
                                    placeholder="FP-260823-A7K2"
                                    autocomplete="off"
                                    required
                                >
                                <div class="form-text">Mã có trong email xác nhận và trên trang đặt hàng thành công.</div>
                                <x-form-error name="order_number"/>
                            </div>

                            <div class="col-md-6">
                                <label class="text-label d-block mb-2" for="contact">Số điện thoại hoặc email</label>
                                <input
                                    type="text"
                                    id="contact"
                                    name="contact"
                                    class="form-control @error('contact') is-invalid @enderror"
                                    placeholder="0901112223"
                                    autocomplete="off"
                                    required
                                >
                                <div class="form-text">Đúng thông tin người nhận đã điền lúc đặt.</div>
                                <x-form-error name="contact"/>
                            </div>

                        </div>

                        <button type="submit" class="btn btn-primary-brand w-100 mt-4">
                            Tra cứu đơn hàng
                        </button>

                    </form>

                    {{--
                        KHÔNG điền sẵn số điện thoại vào ô này cho khách đã
                        đăng nhập: họ có sẵn trang "Đơn hàng của tôi", còn
                        trang này để tra đơn đặt bằng số của người khác.
                    --}}
                    @auth
                        <p class="text-caption text-center mb-0 mt-3">
                            Bạn đang đăng nhập —
                            <a href="{{ route('shop.orders.index') }}">xem toàn bộ đơn hàng của mình</a>.
                        </p>
                    @endauth

                </div>

                <p class="text-caption text-center mt-3 mb-0">
                    Không nhớ mã đơn? Gọi
                    @php $hotline = \App\Models\Setting::get('site_hotline'); @endphp
                    @if($hotline)
                        <strong>{{ $hotline }}</strong> để được hỗ trợ.
                    @else
                        cho cửa hàng để được hỗ trợ.
                    @endif
                </p>

            </div>

        </div>

    </div>
</section>

@endsection
