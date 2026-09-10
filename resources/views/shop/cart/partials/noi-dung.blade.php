{{--
    RUỘT CỦA TRANG GIỎ HÀNG — tách riêng để VẼ LẠI ĐƯỢC.
    ============================================================
    VÌ SAO TÁCH: sửa số lượng, xoá một món hay bỏ tích một dòng đều làm
    đổi rất nhiều thứ cùng lúc — thành tiền của dòng, tạm tính, giảm giá,
    phí giao, tổng cộng, số món đang chọn, nút Thanh toán bật hay tắt, và
    cả danh sách phụ kiện mua kèm.

    Để JavaScript tự tính lại từng con số đó là chép lại toàn bộ luật
    tính tiền sang trình duyệt — đúng thứ tuyệt đối không được làm. Máy
    chủ vẽ lại khối này rồi trả về HTML; trình duyệt chỉ việc thay chỗ
    cũ. Một nơi tính tiền, y như khi tải lại cả trang.

    Không có JavaScript thì tệp này vẫn được vẽ đúng một lần cùng trang,
    và các biểu mẫu bên trong chuyển hướng như thường.
--}}
@if($cart->isEmpty())

    <x-site.empty-state
        title="Giỏ hàng đang trống"
        text="Chọn vài bó hoa hoặc chậu cây bạn thích, rồi quay lại đây."
    >
        <x-slot:actions>
            <a href="{{ route('shop.products.index') }}" class="btn btn-primary-brand">
                Xem sản phẩm
            </a>
        </x-slot:actions>
    </x-site.empty-state>

@else

    <div class="row g-4">

        <div class="col-lg-8">

            {{--
                BIỂU MẪU CHỌN MÓN — đặt NGOÀI các dòng giỏ hàng.

                Mỗi dòng đã có <form> sửa số lượng và <form> xoá, mà HTML
                không cho lồng form. Biểu mẫu này đứng riêng, các ô đánh
                dấu trong từng dòng nối vào bằng thuộc tính form="" — đúng
                cách đã dùng cho ô nhập mã giảm giá ở bước thanh toán.

                CHẠY ĐƯỢC KHI TẮT JAVASCRIPT: tích xong bấm "Cập nhật lựa
                chọn". Có JavaScript thì nút tự ẩn và biểu mẫu gửi ngay
                khi tích (resources/js/cart-live.js).
            --}}
            <div class="cart-select">

                <form
                    id="cart-select"
                    method="POST"
                    action="{{ route('shop.cart.select') }}"
                    class="cart-select__form"
                    data-cart-select
                    data-cart-form
                >
                    @csrf

                    <label class="cart-select__all">
                        <input
                            type="checkbox"
                            class="form-check-input"
                            data-cart-pick-all
                            @checked($selectedCount === $cart->items->count())
                        >
                        <span>Chọn tất cả</span>
                    </label>

                    <span class="cart-select__count">
                        Đang chọn <strong>{{ $selectedCount }}</strong>/{{ $cart->items->count() }} món
                    </span>

                    <button type="submit" class="btn btn-ghost btn-sm" data-cart-select-submit>
                        Cập nhật lựa chọn
                    </button>
                </form>

                {{--
                    XOÁ TẤT CẢ — biểu mẫu RIÊNG, không lồng trong biểu mẫu
                    chọn món (HTML không cho lồng form).

                    KHÔNG mang `data-cart-form`, nên nó gửi kiểu thường và
                    tải lại trang. Có chủ ý: sau khi giỏ trống thì cả khối
                    đổi hẳn — không còn danh sách, không còn bảng tiền, và
                    khối "Có thể bạn cần thêm" cũng mất căn cứ để gợi ý.
                    Tải lại một lần rẻ hơn là vá từng mảnh.

                    HỎI LẠI TRƯỚC KHI XOÁ. Không có bước hoàn tác: bấm
                    nhầm là mất sạch thứ khách đã chọn cả buổi.
                --}}
                <form
                    method="POST"
                    action="{{ route('shop.cart.clear') }}"
                    class="cart-select__clear"
                    onsubmit="return confirm('Xoá tất cả sản phẩm khỏi giỏ hàng? Thao tác này không hoàn tác được.');"
                >
                    @csrf
                    @method('DELETE')

                    <button type="submit" class="btn btn-ghost btn-sm cart-select__clear-btn">
                        <x-site.icon name="trash" />
                        Xoá tất cả
                    </button>
                </form>

            </div>

            <div class="cart-lines">
                @foreach($cart->items as $item)
                    <x-cart.line :item="$item" />
                @endforeach
            </div>

            {{--
                MUA KÈM — đặt DƯỚI danh sách hàng, không đặt cạnh
                khối tính tiền.

                Chỗ này là điểm khách đã quyết định mua nhưng chưa
                trả tiền, và đang nhìn đúng danh sách hàng của
                mình — thời điểm hợp lý nhất để nhắc "cây này cần
                thêm đĩa hứng nước". Đặt cạnh nút Thanh toán thì
                thành ra chen ngang lúc họ sắp bấm.
            --}}
            <x-product.cross-sell
                :items="$accessories"
                title="Có thể bạn cần thêm"
                note="Phụ kiện hợp với hàng đang có trong giỏ."
                source="cart" />

        </div>

        <div class="col-lg-4">
            <x-cart.summary :basket="$basket" :show-action="true" />
        </div>

    </div>

@endif
