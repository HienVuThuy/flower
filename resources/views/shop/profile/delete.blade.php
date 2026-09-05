@extends('layouts.app')

@section('title', 'Xoá tài khoản')

@section('content')

<section class="section-sm">
    <div class="container-shop" style="max-width: 44rem;">

        <h1 class="text-h3 mb-2">Xoá tài khoản</h1>
        <p class="text-caption mb-4">
            Đây là bước cuối. Sau khi xác nhận, tài khoản
            <strong>{{ $user->email }}</strong> sẽ bị xoá vĩnh viễn và không khôi phục được.
        </p>

        <div class="surface-card p-4">

            {{--
                NÓI ĐÚNG CON SỐ, KHÔNG NÓI CHUNG CHUNG.

                "Bạn sẽ mất dữ liệu cá nhân" là câu không giúp ai quyết
                định gì. "Xoá 4 đánh giá, 2 địa chỉ; giữ lại 15 đơn hàng"
                thì có — người đọc biết chính xác mình đang đánh đổi cái
                gì, và có thể dừng lại nếu con số lớn hơn họ tưởng.
            --}}
            <h2 class="text-h4 mb-3">Những gì sẽ bị xoá</h2>

            <ul class="mb-4">
                <li>Hồ sơ, email và mật khẩu của bạn.</li>
                <li>
                    <strong>{{ $summary['danhGiaXoa'] }}</strong> đánh giá bạn đã viết
                    &mdash; chúng sẽ biến mất khỏi trang sản phẩm và điểm trung bình
                    của sản phẩm sẽ đổi theo.
                </li>
                <li><strong>{{ $summary['diaChiXoa'] }}</strong> địa chỉ trong sổ địa chỉ.</li>
                <li><strong>{{ $summary['yeuThichXoa'] }}</strong> sản phẩm yêu thích.</li>
                <li>Mã giảm giá đang có trong ví, và lịch nhắc chăm cây.</li>
            </ul>

            <h2 class="text-h4 mb-3">Những gì được giữ lại</h2>

            <p class="mb-4">
                <strong>{{ $summary['donGiuLai'] }}</strong> đơn hàng của bạn vẫn nằm trong sổ sách
                của cửa hàng, nhưng <strong>không còn gắn với tài khoản nào</strong>. Đó là chứng từ
                mua bán mà cửa hàng phải giữ để đối soát &mdash; xem
                <a href="{{ route('shop.pages.show', 'chinh-sach-bao-mat') }}">Chính sách bảo mật</a>.
                Bạn sẽ không xem lại được chúng sau khi xoá.
            </p>

            <hr class="my-4">

            {{--
                BẮT GÕ MỘT DÒNG, KHÔNG CHỈ BẤM NÚT.

                Hộp thoại "Bạn có chắc không?" thì người ta bấm theo phản
                xạ — đó là cú bấm thứ hai trong cùng một nhịp tay. Phải
                gõ một dòng thì buộc dừng lại, đọc, và làm một việc khác
                hẳn. Với thao tác không có đường lùi thì cái khựng lại đó
                chính là thứ cần.
            --}}
            <form action="{{ route('shop.profile.delete', $user) }}?{{ $signedQuery }}" method="POST">
                @csrf
                @method('DELETE')

                <label for="xac_nhan" class="form-label">
                    Gõ <code>{{ $cauXacNhan }}</code> để xác nhận
                </label>

                <input type="text"
                       name="xac_nhan"
                       id="xac_nhan"
                       class="form-control mb-2 @error('xac_nhan') is-invalid @enderror"
                       placeholder="{{ $cauXacNhan }}"
                       autocomplete="off"
                       autocapitalize="characters"
                       required>

                <x-form-error name="xac_nhan" />

                <div class="d-flex flex-wrap gap-2 mt-4">
                    <button type="submit" class="btn btn-danger">
                        Xoá vĩnh viễn tài khoản
                    </button>

                    {{--
                        ĐƯỜNG LUI ĐẶT NGAY CẠNH, không bắt bấm Back.

                        Người tới được đây có thể chỉ đang xem thử điều gì
                        sẽ xảy ra. Không có lối quay lại rõ ràng thì lựa
                        chọn duy nhất trông thấy được là cái nút đỏ.
                    --}}
                    <a href="{{ route('shop.profile.edit') }}" class="btn btn-secondary-brand">
                        Không, giữ tài khoản của tôi
                    </a>
                </div>
            </form>

        </div>

    </div>
</section>

@endsection
