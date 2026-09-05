{{-- ---------- Nền sáng / tối ---------- --}}
<div class="surface-card p-4 mt-4">

    <h2 class="text-h4 mb-1">Nền sáng / tối</h2>
    <p class="text-caption mb-4">
        Lựa chọn này chỉ áp dụng cho <strong>trình duyệt và thiết bị này</strong>,
        không đồng bộ sang máy khác — cùng một người hoàn toàn có thể muốn nền tối
        trên điện thoại ban đêm và nền sáng trên máy tính ban ngày.
    </p>

    {{--
        BA LỰA CHỌN, khác với nút hai trạng thái trên thanh
        header. Chỗ này người ta vào để chỉnh tuỳ chọn, nên
        "Theo hệ thống" mới có nghĩa; trên thanh header thì
        một cái nút bấm ba lần mới về chỗ cũ là không đoán
        nổi.

        Ba nút gửi cùng một biểu mẫu bằng thuộc tính `value`
        của chính nút bấm — không cần radio ẩn, không cần
        JavaScript, và trình duyệt chỉ gửi đúng nút được bấm.
    --}}
    <form action="{{ route('shop.display-scheme') }}" method="POST"
          class="d-flex flex-wrap gap-2">
        @csrf

        @php($cheDoHienTai = \App\Services\Shop\DisplayScheme::current())

        @foreach(\App\Services\Shop\DisplayScheme::choices() as $cheDo)
            <button type="submit" name="che_do" value="{{ $cheDo }}"
                    class="btn btn-sm {{ $cheDoHienTai === $cheDo ? 'btn-primary-brand' : 'btn-secondary-brand' }}"
                    @if($cheDoHienTai === $cheDo) aria-current="true" @endif>
                {{ \App\Services\Shop\DisplayScheme::label($cheDo) }}
            </button>
        @endforeach
    </form>

    <p class="text-caption mt-3 mb-0">
        {{--
            Nói thẳng cái đánh đổi, thay vì để người dùng
            tự phát hiện bằng cách thấy nó không hoạt động.
        --}}
        &ldquo;Theo hệ thống&rdquo; đọc cài đặt sáng/tối của máy bạn, nên cần JavaScript.
        Tắt JavaScript thì hãy chọn thẳng Nền sáng hoặc Nền tối &mdash; hai lựa chọn đó
        do máy chủ xử lý và luôn hoạt động.
    </p>

</div>
