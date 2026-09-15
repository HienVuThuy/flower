@extends('layouts.app')

@section('title', 'Hồ sơ tài khoản')

@section('content')

<section class="section-sm">
    <div class="container-shop">

        <x-site.breadcrumb :items="[['label' => 'Hồ sơ tài khoản']]" />

        <div class="section-header">
            <div>
                <span class="text-label section-header__eyebrow d-block">Tài khoản</span>
                <h1 class="text-h2 section-header__title">Hồ sơ của bạn</h1>
            </div>
        </div>

        {{--
            CHIA THÀNH BA MỤC, KHÔNG XẾP CHỒNG BẢY KHỐI.
            ============================================================
            Đo trước khi sửa ở khổ 1440×900: trang cao 3748px — hơn BỐN
            MÀN HÌNH cuộn cho một trang cài đặt. Muốn đổi tuỳ chọn nhận
            thư thì phải cuộn qua toàn bộ biểu mẫu đổi mật khẩu và danh
            sách thiết bị, mỗi lần.

            Dài không phải là vấn đề duy nhất. Bảy khối ngang hàng nhau
            thì không khối nào nói được nó quan trọng tới đâu: ô đổi tên
            và nút xoá vĩnh viễn tài khoản trông giống hệt nhau.

            MỤC LÀ LIÊN KẾT, KHÔNG PHẢI TAB JAVASCRIPT.

            Mỗi mục là một địa chỉ riêng (?muc=bao-mat), nên:
              - chạy được khi không có JavaScript;
              - gửi được đường dẫn cho người khác, lưu được dấu trang;
              - bấm Back ra đúng mục vừa xem;
              - biểu mẫu lỗi thì back() đưa về ĐÚNG mục đó, vì địa chỉ
                trước đó đã mang sẵn tham số.

            Tab bằng JavaScript thì mất cả bốn điều trên, đổi lại chỉ
            tránh được một lần tải trang.
        --}}
        <nav class="profile-tabs" aria-label="Mục hồ sơ">
            @foreach($cacMuc as $ma => $nhan)
                <a href="{{ route('shop.profile.edit', ['muc' => $ma]) }}"
                   class="profile-tabs__item {{ $muc === $ma ? 'is-active' : '' }}"
                   @if($muc === $ma) aria-current="page" @endif>
                    {{ $nhan }}
                </a>
            @endforeach
        </nav>

        <div class="row g-4">

            {{--
                CỘT TRÁI: nội dung của mục đang chọn.

                Rộng hơn bản cũ (8/12 thay vì 7/12) vì giờ nó chỉ chứa
                một hoặc hai khối — biểu mẫu có chỗ thở, và cột phải
                không cần rộng bằng khi nó chỉ là danh sách liên kết.
            --}}
            <div class="col-lg-8">

                @if($muc === 'thong-tin')

                    @include('shop.profile.partials.thong-tin')

                @elseif($muc === 'diem-thuong')

                    @include('shop.profile.partials.diem-thuong')

                @elseif($muc === 'hang-thanh-vien')

                    @include('shop.profile.partials.hang-thanh-vien')

                @elseif($muc === 'tra-gop')

                    @include('shop.profile.partials.tra-gop')

                @elseif($muc === 'bao-mat')

                    {{--
                        BA KHỐI BẢO MẬT ĐI CÙNG NHAU, theo đúng thứ tự
                        một người xử lý khi nghi tài khoản bị chiếm:
                        đổi mật khẩu → xem ai đang đăng nhập → nếu tệ
                        quá thì xoá hẳn tài khoản.

                        Khối xoá đứng CUỐI, sau một khoảng cách rõ ràng:
                        nó là việc không có đường lùi, không nên nằm
                        ngay cạnh những nút bấm hằng ngày.
                    --}}
                    @include('shop.profile.partials.doi-mat-khau')

                    <div class="mt-4">
                        @include('shop.profile.partials.thiet-bi')
                    </div>

                    <div class="mt-5">
                        @include('shop.profile.partials.xoa-tai-khoan')
                    </div>

                @else

                    @include('shop.profile.partials.nen-sang-toi')

                    <div class="mt-4">
                        @include('shop.profile.partials.thu-thong-bao')
                    </div>

                @endif

            </div>

            {{--
                CỘT PHẢI: tóm tắt và liên kết nhanh, HIỆN Ở MỌI MỤC.

                Đây là thứ người ta thật sự tới trang này để dùng —
                đường sang đơn hàng, sổ địa chỉ, ví voucher. Nhét nó vào
                một mục riêng là bắt bấm thêm một lần cho việc phổ biến
                nhất, để đổi lấy chỗ trống cho những việc hiếm hơn.
            --}}
            <div class="col-lg-4">
                @include('shop.profile.partials.tom-tat')
            </div>

        </div>

    </div>
</section>

@endsection
